<?php

use App\Enums\ApplicationOrigin;
use App\Enums\ApplicationStatus;
use App\Enums\ConnectedIntegrationStatus;
use App\Enums\ContactConfidence;
use App\Enums\OutreachStatus;
use App\Models\Application;
use App\Models\ApplicationProfile;
use App\Models\Company;
use App\Models\ConnectedIntegration;
use App\Models\Contact;
use App\Models\JobPosting;
use App\Models\User;
use App\Outreach\Actions\CanSendApplications;
use App\Outreach\Actions\QueueApplication;
use App\Outreach\Contracts\SendsGmailMessages;
use App\Outreach\Data\SendEligibility;
use App\Outreach\Jobs\SendApplicationEmail;
use App\Services\ConnectedIntegrationTokenManager;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/*
 * Sending guarantees over real rows (PostgreSQL `talent_labs_testing`): Gmail is
 * a recording fake, the queue is faked, HTTP is blocked and time is frozen.
 */

uses(RefreshDatabase::class);

final class RecordingGmailSender implements SendsGmailMessages
{
    public int $calls = 0;

    public function send(ConnectedIntegration $integration, string $rawMime): string
    {
        $this->calls++;

        return 'fake-message-id';
    }
}

beforeEach(function (): void {
    Queue::fake();
    Http::fake();
    Http::preventStrayRequests();
    Storage::fake('local');

    config([
        'talent.outreach.step_delay_ms' => 0,
        'talent.outreach.intercept_to' => null,
        'talent.outreach.interval_min_seconds' => 45,
        'talent.outreach.interval_max_seconds' => 120,
        'talent.outreach.window.enabled' => false,
    ]);

    $this->travelTo(CarbonImmutable::parse('2026-09-30 12:00:00'));

    $this->gmail = new RecordingGmailSender;
    $this->app->instance(SendsGmailMessages::class, $this->gmail);

    $this->user = User::factory()->create(['timezone' => 'UTC']);
});

function verifiedCompany(): Company
{
    $name = 'Acme '.Str::random(8);

    return Company::query()->forceCreate([
        'name' => $name,
        'normalized_name' => Str::lower($name),
        'outreach_status' => OutreachStatus::Verified,
    ]);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function applicationFor(User $user, array $attributes = [], ?Company $company = null): Application
{
    $company ??= verifiedCompany();

    return Application::query()->forceCreate([
        'user_id' => $user->id,
        'company_id' => $company->id,
        'recipient_email' => 'jobs@'.$company->normalized_name.'.test',
        'subject' => 'Application',
        'body' => 'Hello, please find my CV attached.',
        'origin' => ApplicationOrigin::Manual,
        'status' => ApplicationStatus::Queued,
        'attempts' => 0,
        'queued_at' => now(),
        'scheduled_for' => now(),
        ...$attributes,
    ])->refresh();
}

/**
 * A queued application that passes every pre-check (verified company and
 * contact, connected Gmail, valid PDF CV).
 */
function sendableApplication(User $user): Application
{
    $company = verifiedCompany();

    $contact = Contact::query()->forceCreate([
        'company_id' => $company->id,
        'email' => 'jobs@acme.test',
        'local_part' => 'jobs',
        'confidence' => ContactConfidence::SmtpVerified,
        'checked_at' => now(),
    ]);

    ConnectedIntegration::query()->forceCreate([
        'user_id' => $user->id,
        'plugin_key' => 'gmail',
        'status' => ConnectedIntegrationStatus::Connected,
        'account_email' => 'client@gmail.test',
        'access_token' => 'token',
    ]);

    $cvPath = 'cvs/'.$user->id.'/cv.pdf';
    Storage::disk('local')->put($cvPath, "%PDF-1.4\n%fake\n");

    $profile = ApplicationProfile::query()->forceCreate([
        'user_id' => $user->id,
        'language' => 'en',
        'cv_path' => $cvPath,
        'cv_original_name' => 'cv.pdf',
        'email_subject' => 'Application',
        'email_body' => 'Hello',
    ]);

    return applicationFor($user, [
        'contact_id' => $contact->id,
        'application_profile_id' => $profile->id,
        'recipient_email' => $contact->email,
        'language' => 'en',
    ], $company);
}

function runSendJob(Application $application, ?CarbonImmutable $expectedScheduledFor): void
{
    app()->call([new SendApplicationEmail($application->id, $expectedScheduledFor), 'handle']);
}

it('rejects a second queueing for the same company and the DB unique holds', function (): void {
    $this->mock(CanSendApplications::class)
        ->shouldReceive('check')
        ->andReturn(new SendEligibility([], 0, 10));

    $existing = applicationFor($this->user);
    $posting = (new JobPosting)->forceFill(['company_id' => $existing->company_id]);

    $action = app(QueueApplication::class);

    expect($action->handle($this->user, $posting, ApplicationOrigin::Manual))->toBeNull()
        ->and($action->rejectionReason())->toBe(QueueApplication::REJECT_ALREADY_APPLIED)
        ->and(Application::query()->whereBelongsTo($this->user)->count())->toBe(1);

    Queue::assertNothingPushed();

    expect(fn () => applicationFor($this->user, [], $existing->company))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('never re-sends a sent or ambiguous application', function (ApplicationStatus $status): void {
    $application = applicationFor($this->user, ['status' => $status, 'attempts' => 1]);
    $before = $application->only(['status', 'attempts', 'stage', 'stage_log', 'scheduled_for', 'updated_at']);

    runSendJob($application, $application->scheduled_for);
    runSendJob($application, null);

    expect($application->refresh()->only(array_keys($before)))->toEqual($before)
        ->and($this->gmail->calls)->toBe(0);

    Queue::assertNothingPushed();
})->with([
    'sent' => ApplicationStatus::Sent,
    'ambiguous' => ApplicationStatus::Ambiguous,
]);

it('exits a stale dispatch without side effects', function (): void {
    $application = sendableApplication($this->user);
    $before = $application->only(['status', 'attempts', 'stage', 'sub_step', 'stage_log', 'scheduled_for', 'updated_at']);

    runSendJob($application, $application->scheduled_for->addMinute());

    expect($application->refresh()->only(array_keys($before)))->toEqual($before)
        ->and($application->status)->toBe(ApplicationStatus::Queued)
        ->and($application->attempts)->toBe(0)
        ->and($application->stage_log)->toBe([])
        ->and($this->gmail->calls)->toBe(0);

    Queue::assertNothingPushed();
});

it('parks the application of a paused user: queued with no slot', function (): void {
    $application = sendableApplication($this->user);
    $this->user->forceFill(['sending_paused_at' => now()])->save();

    runSendJob($application, $application->scheduled_for);

    $application->refresh();

    expect($application->status)->toBe(ApplicationStatus::Queued)
        ->and($application->scheduled_for)->toBeNull()
        ->and($application->attempts)->toBe(0)
        ->and($this->gmail->calls)->toBe(0);

    Queue::assertNothingPushed();
});

it('re-slots instead of sending while another application of the user is sending', function (): void {
    $this->mock(ConnectedIntegrationTokenManager::class)
        ->shouldReceive('accessToken')
        ->andReturn('token');

    applicationFor($this->user, ['status' => ApplicationStatus::Sending, 'attempts' => 1]);
    $application = sendableApplication($this->user);
    $originalSlot = $application->scheduled_for;

    runSendJob($application, $originalSlot);

    $application->refresh();

    expect($application->status)->toBe(ApplicationStatus::Queued)
        ->and($application->attempts)->toBe(0)
        ->and($application->stage)->toBeNull()
        ->and($application->scheduled_for)->not->toBeNull()
        ->and($application->scheduled_for->greaterThan($originalSlot))->toBeTrue()
        ->and($this->gmail->calls)->toBe(0)
        ->and(Application::query()->whereBelongsTo($this->user)->where('status', ApplicationStatus::Sending)->count())->toBe(1);

    Queue::assertPushed(SendApplicationEmail::class, fn (SendApplicationEmail $job): bool => $job->applicationId === $application->id
        && $job->expectedScheduledFor?->getTimestamp() === $application->scheduled_for->getTimestamp());
});

it('counts today\'s queued, sending, sent and ambiguous rows only', function (): void {
    $counted = collect([
        ApplicationStatus::Queued,
        ApplicationStatus::Sending,
        ApplicationStatus::Sent,
        ApplicationStatus::Ambiguous,
    ])->map(fn (ApplicationStatus $status): int => applicationFor($this->user, ['status' => $status])->id);

    applicationFor($this->user, ['status' => ApplicationStatus::Failed]);
    applicationFor($this->user, ['status' => ApplicationStatus::Sent, 'queued_at' => now()->subDay()]);
    applicationFor($this->user, ['status' => ApplicationStatus::Queued, 'queued_at' => now()->startOfDay()->subSecond()]);
    applicationFor(User::factory()->create(), ['status' => ApplicationStatus::Sent]);

    $ids = Application::query()->whereBelongsTo($this->user)->countedToday()->orderBy('id')->pluck('id');

    expect($ids->all())->toBe($counted->all());
});
