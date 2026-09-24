<?php

namespace App\Outreach\Jobs;

use App\Enums\ApplicationStatus;
use App\Enums\ConnectedIntegrationStatus;
use App\Enums\ContactConfidence;
use App\Enums\OutreachStatus;
use App\Exceptions\ConnectedIntegrationReauthorizationRequired;
use App\Models\Application;
use App\Models\ConnectedIntegration;
use App\Outreach\Contracts\SendsGmailMessages;
use App\Outreach\Support\ApplicationTemplateRenderer;
use App\Services\ConnectedIntegrationTokenManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Throwable;

class SendApplicationEmail implements ShouldQueue
{
    use Queueable;

    public const string HEADER_APPLICATION_ID = 'X-TalentLabs-Application-Id';

    public int $tries = 3;

    /**
     * Must stay below the database queue's retry_after (90).
     */
    public int $timeout = 60;

    public function __construct(public int $applicationId)
    {
        $this->onQueue('outreach');
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(SendsGmailMessages $sender, ConnectedIntegrationTokenManager $tokens): void
    {
        $prepared = DB::transaction(fn (): ?array => $this->beginAttempt());

        if ($prepared === null) {
            return;
        }

        [$application, $integration, $rawMime] = $prepared;

        try {
            $messageId = $sender->send($integration, $rawMime);
        } catch (ConnectionException $exception) {
            // The message may or may not have left: never retry automatically.
            $this->finish($application, ApplicationStatus::Ambiguous, $exception);
            Log::warning('Application send ended ambiguous after a connection error.', [
                'application_id' => $application->id,
                'user_id' => $application->user_id,
            ]);

            return;
        } catch (ConnectedIntegrationReauthorizationRequired $exception) {
            $this->finish($application, ApplicationStatus::Failed, $exception);

            return;
        } catch (RequestException $exception) {
            if ($this->isAuthorizationError($exception)) {
                try {
                    $tokens->requireReauthorization($application->user, $integration->plugin_key, $exception);
                } catch (ConnectedIntegrationReauthorizationRequired) {
                    // Expected: the integration is now marked for reauthorization.
                }

                $this->finish($application, ApplicationStatus::Failed, $exception);

                return;
            }

            if ($this->attempts() >= $this->tries) {
                $this->finish($application, ApplicationStatus::Failed, $exception);

                return;
            }

            $this->finish($application, ApplicationStatus::Queued, $exception);

            throw $exception;
        } catch (Throwable $exception) {
            // Unknown outcome after the send started: never send twice.
            $this->finish($application, ApplicationStatus::Ambiguous, $exception);
            Log::warning('Application send ended ambiguous after an unexpected error.', [
                'application_id' => $application->id,
                'user_id' => $application->user_id,
            ]);

            return;
        }

        $application->forceFill([
            'status' => ApplicationStatus::Sent,
            'provider_message_id' => $messageId,
            'sent_at' => now(),
            'last_error' => null,
        ])->save();
    }

    public function failed(?Throwable $exception): void
    {
        $application = Application::query()->find($this->applicationId);

        if ($application?->status !== ApplicationStatus::Queued) {
            return;
        }

        $application->forceFill([
            'status' => ApplicationStatus::Failed,
            'last_error' => $exception === null ? 'Sending failed.' : self::errorMessage($exception),
        ])->save();
    }

    public static function errorMessage(Throwable $exception): string
    {
        return class_basename($exception).': '.Str::limit($exception->getMessage(), 180);
    }

    /**
     * Locks the application, runs the defensive checks and marks it sending.
     * The MIME message is built before the status changes so a build failure
     * leaves the row queued (normal retry) instead of stuck in sending.
     *
     * @return array{0: Application, 1: ConnectedIntegration, 2: string}|null
     */
    private function beginAttempt(): ?array
    {
        $application = Application::query()->lockForUpdate()->find($this->applicationId);

        if ($application === null) {
            return null;
        }

        if ($application->status === ApplicationStatus::Sending) {
            // A previous worker died mid-send: the email may have left.
            $application->forceFill([
                'status' => ApplicationStatus::Ambiguous,
                'last_error' => 'Worker stopped mid-send.',
            ])->save();

            return null;
        }

        if ($application->status !== ApplicationStatus::Queued) {
            return null;
        }

        $application->load(['company', 'contact', 'user.jobPreference', 'user.gmailIntegration']);
        $user = $application->user;
        $contact = $application->contact;
        $integration = $user->gmailIntegration;
        $preference = $user->jobPreference;

        $reason = match (true) {
            ! $user->isActive() => 'Client account is not active.',
            $application->company->outreach_status !== OutreachStatus::Verified => 'Company is no longer verified for outreach.',
            $contact === null,
            $contact->company_id !== $application->company_id,
            $contact->confidence !== ContactConfidence::SmtpVerified => 'Recipient is not a verified contact of this company.',
            $integration === null,
            $integration->status !== ConnectedIntegrationStatus::Connected,
            blank($integration->account_email) => 'Gmail is not connected.',
            $preference === null,
            blank($preference->cv_path),
            ! self::isOwnCvPath((string) $preference->cv_path, $application->user_id),
            ! Storage::disk('local')->exists((string) $preference->cv_path) => 'CV file is missing.',
            default => null,
        };

        if ($reason !== null) {
            $application->forceFill([
                'status' => ApplicationStatus::Failed,
                'last_error' => $reason,
            ])->save();

            return null;
        }

        // Test mode: with OUTREACH_INTERCEPT_TO set, nothing is delivered to the company.
        $interceptTo = self::interceptTo();

        $email = (new Email)
            ->from(new Address((string) $integration->account_email, $user->name))
            ->to($interceptTo ?? $application->recipient_email)
            ->subject($interceptTo === null
                ? $application->subject
                : '[TEST — would go to '.$application->recipient_email.'] '.$application->subject)
            ->text($application->body)
            ->html(ApplicationTemplateRenderer::html($application->body))
            ->attachFromPath(
                Storage::disk('local')->path((string) $preference->cv_path),
                self::attachmentName($preference->cv_original_name),
                'application/pdf',
            );

        $email->getHeaders()->addTextHeader(self::HEADER_APPLICATION_ID, (string) $application->id);

        if ($interceptTo !== null) {
            $email->getHeaders()->addTextHeader('X-TalentLabs-Intercepted', 'true');
        }

        $rawMime = $email->toString();

        $application->forceFill([
            'status' => ApplicationStatus::Sending,
            'attempts' => $application->attempts + 1,
        ])->save();

        return [$application, $integration, $rawMime];
    }

    /**
     * The address every email is redirected to in test mode, or null to send to the company.
     *
     * A configured but invalid address must never fall back to the real recipient.
     */
    private static function interceptTo(): ?string
    {
        $configured = trim((string) config('talent.outreach.intercept_to'));

        if ($configured === '') {
            return null;
        }

        if (filter_var($configured, FILTER_VALIDATE_EMAIL) === false) {
            throw new LogicException('OUTREACH_INTERCEPT_TO is set but is not a valid email address.');
        }

        return $configured;
    }

    /**
     * The CV must live under the application owner's own cvs/{userId}/ folder.
     */
    private static function isOwnCvPath(string $path, int $userId): bool
    {
        return str_starts_with($path, 'cvs/'.$userId.'/')
            && ! str_contains($path, '\\')
            && ! str_contains($path, "\0")
            && ! in_array('..', explode('/', $path), true);
    }

    /**
     * Safe attachment display name: basename, no control chars, quotes or backslashes.
     */
    private static function attachmentName(?string $name): string
    {
        $name = basename(str_replace('\\', '/', (string) $name));
        $name = trim((string) preg_replace('/[\x00-\x1F\x7F"\\\\]/u', '', $name));

        return $name === '' ? 'cv.pdf' : $name;
    }

    private function finish(Application $application, ApplicationStatus $status, Throwable $exception): void
    {
        $application->forceFill([
            'status' => $status,
            'last_error' => self::errorMessage($exception),
        ])->save();
    }

    private function isAuthorizationError(RequestException $exception): bool
    {
        $response = $exception->response;

        if ($response->status() === 401) {
            return true;
        }

        $json = $response->json();
        $reason = is_array($json) ? data_get($json, 'error.errors.0.reason') : null;
        $status = is_array($json) ? data_get($json, 'error.status') : null;

        return in_array($reason, ['authError', 'insufficientPermissions'], true)
            || $status === 'UNAUTHENTICATED';
    }
}
