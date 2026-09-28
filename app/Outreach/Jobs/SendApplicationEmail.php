<?php

namespace App\Outreach\Jobs;

use App\Enums\ApplicationStatus;
use App\Enums\ConnectedIntegrationStatus;
use App\Enums\ContactConfidence;
use App\Enums\OutreachStatus;
use App\Enums\SendingPauseReason;
use App\Enums\SendStage;
use App\Enums\SendSubStep;
use App\Events\Client\SendingUpdated;
use App\Exceptions\ConnectedIntegrationReauthorizationRequired;
use App\Models\Application;
use App\Models\ApplicationProfile;
use App\Models\ConnectedIntegration;
use App\Models\User;
use App\Notifications\Client\ApplicationFailed;
use App\Notifications\Client\ClientNotification;
use App\Notifications\Client\SendingAutoPaused;
use App\Outreach\Actions\CanSendApplications;
use App\Outreach\Contracts\SendsGmailMessages;
use App\Outreach\Support\ApplicationStageRecorder;
use App\Outreach\Support\ApplicationTemplateRenderer;
use App\Outreach\Support\OutreachConfig;
use App\Outreach\Support\SendScheduler;
use App\Plans\PlanCatalog;
use App\Services\ConnectedIntegrationTokenManager;
use Carbon\CarbonImmutable;
use Closure;
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

    private const int MAX_CV_BYTES = 5 * 1024 * 1024;

    public int $tries = 3;

    /**
     * Must stay below the database queue's retry_after (90).
     */
    public int $timeout = 60;

    /**
     * A null $expectedScheduledFor (jobs serialized before spaced queueing)
     * skips the stale check.
     */
    public function __construct(public int $applicationId, public ?CarbonImmutable $expectedScheduledFor = null)
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

    public function handle(
        SendsGmailMessages $sender,
        ConnectedIntegrationTokenManager $tokens,
        SendScheduler $scheduler,
        ApplicationStageRecorder $recorder,
        PlanCatalog $plans,
    ): void {
        if ($this->stepsAside($scheduler)) {
            return;
        }

        $checked = null;
        $candidate = Application::query()->find($this->applicationId);

        // Only a queued row runs the pre-checks; any other status goes straight to beginAttempt().
        if ($candidate !== null && $candidate->status === ApplicationStatus::Queued) {
            $checked = $this->preCheck($candidate, $tokens, $recorder);

            if ($checked === null) {
                return;
            }
        }

        $prepared = DB::transaction(fn (): ?array => $this->beginAttempt($scheduler, $recorder, $plans, $checked));

        if ($prepared === null) {
            return;
        }

        [$application, $integration, $rawMime] = $prepared;

        $recorder->record($application, SendStage::Sending, SendSubStep::Delivering);

        try {
            $messageId = $sender->send($integration, $rawMime);
        } catch (ConnectionException $exception) {
            // The message may or may not have left: never retry automatically.
            $this->finish($application, ApplicationStatus::Ambiguous, $exception);
            $this->afterTerminal($application);
            Log::warning('Application send ended ambiguous after a connection error.', [
                'application_id' => $application->id,
                'user_id' => $application->user_id,
            ]);

            return;
        } catch (ConnectedIntegrationReauthorizationRequired $exception) {
            $this->finish($application, ApplicationStatus::Failed, $exception);
            $recorder->record($application, SendStage::Failed, SendSubStep::Delivering);
            $this->afterTerminal($application, isReauthorization: true);

            return;
        } catch (RequestException $exception) {
            if ($this->isAuthorizationError($exception)) {
                try {
                    $tokens->requireReauthorization($application->user, $integration->plugin_key, $exception);
                } catch (ConnectedIntegrationReauthorizationRequired) {
                    // Expected: the integration is now marked for reauthorization.
                }

                $this->finish($application, ApplicationStatus::Failed, $exception);
                $recorder->record($application, SendStage::Failed, SendSubStep::Delivering);
                $this->afterTerminal($application, isReauthorization: true);

                return;
            }

            if ($this->attempts() >= $this->tries) {
                $this->finish($application, ApplicationStatus::Failed, $exception);
                $recorder->record($application, SendStage::Failed, SendSubStep::Delivering);
                $this->afterTerminal($application);

                return;
            }

            $this->finish($application, ApplicationStatus::Queued, $exception);
            $recorder->reset($application);

            throw $exception;
        } catch (Throwable $exception) {
            // Unknown outcome after the send started: never send twice.
            $this->finish($application, ApplicationStatus::Ambiguous, $exception);
            $this->afterTerminal($application);
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

        $recorder->record($application, SendStage::Sent, null);
        $this->afterTerminal($application);
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

        app(ApplicationStageRecorder::class)
            ->record($application, SendStage::Failed, $application->sub_step ?? SendSubStep::Delivering);
        $this->afterTerminal($application);
    }

    public static function errorMessage(Throwable $exception): string
    {
        return class_basename($exception).': '.Str::limit($exception->getMessage(), 180);
    }

    /**
     * Start guards, outside any lock, for a queued row only: a stale job exits,
     * a paused user parks the row, outside the window it is re-slotted.
     * Any other case falls through to beginAttempt().
     */
    private function stepsAside(SendScheduler $scheduler): bool
    {
        $application = Application::query()->with('user')->find($this->applicationId);

        if ($application === null || $application->status !== ApplicationStatus::Queued) {
            return false;
        }

        // Stale: a newer job for this application has taken over its slot.
        if ($this->expectedScheduledFor !== null
            && $application->scheduled_for?->getTimestamp() !== $this->expectedScheduledFor->getTimestamp()) {
            return true;
        }

        $user = $application->user;

        // Paused: parked (queued, no slot) until resume() re-slots it.
        if ($user->isSendingPaused()) {
            $application->forceFill(['scheduled_for' => null])->save();

            return true;
        }

        if (! $scheduler->isInsideWindow($user)) {
            $this->reslot($application, $user, $scheduler);

            return true;
        }

        return false;
    }

    private function reslot(Application $application, User $user, SendScheduler $scheduler): void
    {
        $slot = $scheduler->nextSlot($user);
        $application->forceFill(['scheduled_for' => $slot])->save();
        self::dispatch($application->id, $slot)->delay($slot)->afterCommit();
    }

    /**
     * Staged checks, without locks, for a queued row: each sub-step is recorded,
     * checked, then paced. A failed check marks the row failed (no retry) and
     * returns null. Any other error resets the stage and is rethrown: nothing
     * was sent, so the row stays queued for the normal retry.
     *
     * @return array{0: ConnectedIntegration, 1: string}|null
     */
    private function preCheck(Application $application, ConnectedIntegrationTokenManager $tokens, ApplicationStageRecorder $recorder): ?array
    {
        $application->load(['company', 'contact', 'user.gmailIntegration', 'applicationProfile']);
        $user = $application->user;
        $contact = $application->contact;
        $integration = $user->gmailIntegration;
        $profile = $application->applicationProfile;
        $cvPath = (string) $profile?->cv_path;
        $reauthorization = false;

        /** @var list<array{0: SendSubStep, 1: Closure(): ?string}> $checks */
        $checks = [
            [SendSubStep::CheckingCompany, fn (): ?string => $application->company->outreach_status !== OutreachStatus::Verified
                ? 'Company is no longer verified for outreach.'
                : null],
            [SendSubStep::ConfirmingRecipient, fn (): ?string => $contact === null
                || $contact->company_id !== $application->company_id
                || $contact->confidence !== ContactConfidence::SmtpVerified
                ? 'Recipient is not a verified contact of this company.'
                : null],
            [SendSubStep::CheckingGmail, function () use ($integration, $user, $tokens, &$reauthorization): ?string {
                if ($integration === null
                    || $integration->status !== ConnectedIntegrationStatus::Connected
                    || blank($integration->account_email)) {
                    return 'Gmail is not connected.';
                }

                try {
                    $tokens->accessToken($user, $integration->plugin_key);
                } catch (ConnectedIntegrationReauthorizationRequired) {
                    $reauthorization = true;

                    return 'Gmail is not connected.';
                }

                return null;
            }],
            [SendSubStep::FillingVariables, fn (): ?string => blank($application->subject)
                || blank($application->body)
                || mb_strlen((string) $application->subject) > CanSendApplications::MAX_SUBJECT_LENGTH
                || mb_strlen((string) $application->body) > CanSendApplications::MAX_BODY_LENGTH
                ? 'Email content is invalid.'
                : null],
            [SendSubStep::BuildingHtml, function () use ($application): ?string {
                try {
                    ApplicationTemplateRenderer::html($application->body);
                } catch (Throwable) {
                    return 'Email content is invalid.';
                }

                return null;
            }],
            [SendSubStep::OpeningCv, fn (): ?string => $profile === null
                || blank($profile->cv_path)
                || ! ApplicationProfile::isOwnCvPath($cvPath, $application->user_id)
                || ! Storage::disk('local')->exists($cvPath)
                ? 'CV file is missing.'
                : null],
            [SendSubStep::CheckingPdf, fn (): ?string => self::isValidPdf($cvPath) ? null : 'CV file is not a valid PDF.'],
        ];

        $failedAt = null;
        $reason = null;

        try {
            foreach ($checks as [$subStep, $check]) {
                $recorder->record($application, $subStep->stage(), $subStep);
                $reason = $check();

                if ($reason !== null) {
                    $failedAt = $subStep;

                    break;
                }

                $recorder->pace();
            }

            if ($failedAt === null) {
                if ($integration === null || $profile === null) {
                    throw new LogicException('Send pre-checks passed without an integration or a CV profile.');
                }

                $recorder->record($application, SendStage::AttachingCv, SendSubStep::AttachingFile);
                $rawMime = $this->buildMime($application, $user, $integration, $profile);
                $recorder->pace();
            }
        } catch (Throwable $exception) {
            $recorder->reset($application);

            throw $exception;
        }

        if ($failedAt !== null) {
            $application->forceFill([
                'status' => ApplicationStatus::Failed,
                'last_error' => $reason,
            ])->save();
            $recorder->record($application, SendStage::Failed, $failedAt);
            $this->afterTerminal($application, isReauthorization: $reauthorization);

            return null;
        }

        return [$integration, $rawMime];
    }

    /**
     * At most 5 MB and starting with the PDF signature. Only the first bytes are read.
     */
    private static function isValidPdf(string $path): bool
    {
        $disk = Storage::disk('local');

        if ($disk->size($path) > self::MAX_CV_BYTES) {
            return false;
        }

        $stream = $disk->readStream($path);

        if (! is_resource($stream)) {
            return false;
        }

        try {
            return fread($stream, 5) === '%PDF-';
        } finally {
            fclose($stream);
        }
    }

    private function buildMime(Application $application, User $user, ConnectedIntegration $integration, ApplicationProfile $profile): string
    {
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
                Storage::disk('local')->path((string) $profile->cv_path),
                self::attachmentName($profile->cv_original_name),
                'application/pdf',
            );

        $email->getHeaders()->addTextHeader(self::HEADER_APPLICATION_ID, (string) $application->id);

        if ($interceptTo !== null) {
            $email->getHeaders()->addTextHeader('X-TalentLabs-Intercepted', 'true');
        }

        return $email->toString();
    }

    /**
     * Locks the user then the application, runs the checks that need the lock
     * (overlap, pause, account, today's quota) and marks it sending. The MIME
     * message was built by preCheck(), before any status change.
     *
     * @param  array{0: ConnectedIntegration, 1: string}|null  $checked
     * @return array{0: Application, 1: ConnectedIntegration, 2: string}|null
     */
    private function beginAttempt(SendScheduler $scheduler, ApplicationStageRecorder $recorder, PlanCatalog $plans, ?array $checked): ?array
    {
        $userId = Application::query()->whereKey($this->applicationId)->value('user_id');

        if ($userId === null) {
            return null;
        }

        // User row first: serializes concurrent attempts for the same user.
        $user = User::query()->whereKey($userId)->lockForUpdate()->first();

        $application = Application::query()->lockForUpdate()->find($this->applicationId);

        if ($user === null || $application === null) {
            return null;
        }

        if ($application->status === ApplicationStatus::Sending) {
            // A previous worker died mid-send: the email may have left.
            $application->forceFill([
                'status' => ApplicationStatus::Ambiguous,
                'last_error' => 'Worker stopped mid-send.',
            ])->save();
            DB::afterCommit(fn () => $this->afterTerminal($application));

            return null;
        }

        // Not queued, or it became queued after this job's pre-check (another
        // worker's retryable failure; that worker's retry owns it).
        if ($application->status !== ApplicationStatus::Queued || $checked === null) {
            return null;
        }

        [$integration, $rawMime] = $checked;

        // Never overlap another send of the same user.
        $otherSending = Application::query()
            ->whereBelongsTo($user)
            ->where('status', ApplicationStatus::Sending)
            ->whereKeyNot($application->id)
            ->exists();

        if ($otherSending) {
            $this->reslot($application, $user, $scheduler);
            $recorder->reset($application);

            return null;
        }

        // Re-checked under the lock: the user may have paused since the start guards.
        if ($user->isSendingPaused()) {
            $application->forceFill(['scheduled_for' => null])->save();
            $recorder->reset($application);

            return null;
        }

        if (! $user->isActive()) {
            $application->forceFill([
                'status' => ApplicationStatus::Failed,
                'last_error' => 'Client account is not active.',
            ])->save();
            $recorder->record($application, SendStage::Failed, SendSubStep::ConnectingGmail);
            DB::afterCommit(fn () => $this->afterTerminal($application));

            return null;
        }

        // Today's limit may have been lowered after this row was queued: only rows
        // already spent count, and an over-limit row moves to the next day's window.
        $spent = Application::query()
            ->whereBelongsTo($user)
            ->countedToday()
            ->where('status', '!=', ApplicationStatus::Queued)
            ->count();

        if ($spent >= $plans->for($user)->dailyLimit) {
            $slot = $scheduler->nextWindowStart($user, CarbonImmutable::now()->addDay()->startOfDay());
            $application->forceFill(['scheduled_for' => $slot])->save();
            self::dispatch($application->id, $slot)->delay($slot)->afterCommit();
            $recorder->reset($application);

            return null;
        }

        $application->forceFill([
            'status' => ApplicationStatus::Sending,
            'attempts' => $application->attempts + 1,
        ])->save();
        $recorder->record($application, SendStage::Sending, SendSubStep::ConnectingGmail);
        $recorder->pace();

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

    /**
     * Effects of a terminal status (sent, failed, ambiguous): notify a failure,
     * auto-pause on a Gmail reauthorization or on repeated failures, and refresh
     * the live sending panel.
     */
    private function afterTerminal(Application $application, bool $isReauthorization = false): void
    {
        $user = $application->user;

        if ($application->status === ApplicationStatus::Failed) {
            $application->loadMissing('company');
            ClientNotification::send($user, new ApplicationFailed($application->company->name));
        }

        if ($isReauthorization) {
            $this->autoPause($user, SendingPauseReason::ReauthorizationRequired);
        } elseif ($this->hasRepeatedFailures($user)) {
            $this->autoPause($user, SendingPauseReason::RepeatedFailures);
        }

        SendingUpdated::broadcastFor($application->user_id);
    }

    private function autoPause(User $user, SendingPauseReason $reason): void
    {
        if (app(SendScheduler::class)->pause($user, $reason)) {
            ClientNotification::send($user, new SendingAutoPaused($reason->value));
        }
    }

    /**
     * Whether the user's last N terminal applications number exactly N and none was sent.
     */
    private function hasRepeatedFailures(User $user): bool
    {
        $threshold = OutreachConfig::autoPauseAfterFailures();

        if ($threshold < 1) {
            return false;
        }

        $statuses = Application::query()
            ->whereBelongsTo($user)
            ->whereIn('status', [ApplicationStatus::Sent, ApplicationStatus::Failed, ApplicationStatus::Ambiguous])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit($threshold)
            ->pluck('status');

        return $statuses->count() === $threshold
            && $statuses->doesntContain(ApplicationStatus::Sent);
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
