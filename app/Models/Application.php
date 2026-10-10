<?php

namespace App\Models;

use App\Enums\ApplicationOrigin;
use App\Enums\ApplicationStatus;
use App\Enums\SendStage;
use App\Enums\SendSubStep;
use App\Events\Client\ApplicationProgressed;
use App\Events\Client\Concerns\DispatchesClientEvent;
use App\Models\Concerns\BroadcastsRealtime;
use App\Outreach\OutreachLimits;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $company_id
 * @property int|null $job_posting_id
 * @property int|null $contact_id
 * @property int|null $application_profile_id
 * @property string $recipient_email
 * @property string $subject
 * @property string $body
 * @property ApplicationOrigin $origin
 * @property string|null $language
 * @property ApplicationStatus $status
 * @property SendStage|null $stage
 * @property SendSubStep|null $sub_step
 * @property array<int, array{stage: string, at: string}> $stage_log
 * @property int $attempts
 * @property string|null $provider_message_id
 * @property string|null $last_error
 * @property CarbonImmutable|null $queued_at
 * @property CarbonImmutable|null $scheduled_for
 * @property CarbonImmutable|null $sent_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 * @property-read Company $company
 * @property-read JobPosting|null $jobPosting
 * @property-read Contact|null $contact
 * @property-read ApplicationProfile|null $applicationProfile
 */
#[Fillable(['user_id', 'company_id', 'job_posting_id', 'contact_id', 'application_profile_id', 'recipient_email', 'subject', 'body', 'origin', 'language', 'status', 'attempts', 'provider_message_id', 'last_error', 'queued_at', 'scheduled_for', 'sent_at'])]
class Application extends Model
{
    use BroadcastsRealtime;
    use DispatchesClientEvent;

    protected static function booted(): void
    {
        static::saved(function (Application $application): void {
            static::broadcastUpdated($application);

            static::dispatchClientEvent(new ApplicationProgressed($application->id, $application->user_id));

            if ($application->wasChanged('status')) {
                static::dispatchAccountStatusUpdated($application->user_id);
            }
        });

        static::deleted(function (Application $application): void {
            static::broadcastUpdated($application);
        });
    }

    protected static function broadcastUpdated(Application $application): void
    {
        static::broadcastRealtime('applications', 'ApplicationsUpdated', ['id' => $application->id]);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'origin' => ApplicationOrigin::class,
            'status' => ApplicationStatus::class,
            'stage' => SendStage::class,
            'sub_step' => SendSubStep::class,
            'stage_log' => 'array',
            'attempts' => 'integer',
            'queued_at' => 'datetime',
            'scheduled_for' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * Applications that consume today's send quota: status in
     * (queued, sending, sent, ambiguous) and queued_at within today in the
     * app timezone. Single definition shared by CanSendApplications and the
     * admin Users "sent today" column (e.g. `withCount(['applications as
     * sent_today_count' => fn ($q) => $q->countedToday()])`).
     *
     * @param  Builder<Application>  $query
     */
    #[Scope]
    protected function countedToday(Builder $query): void
    {
        $query
            ->whereIn('status', ApplicationStatus::countedTowardsQuota())
            ->whereBetween('queued_at', [now()->startOfDay(), now()->endOfDay()]);
    }

    /**
     * Destination type: 'generic' when the recipient local part is a shared
     * mailbox (OutreachLimits::GENERIC_LOCAL_PARTS), otherwise 'named'.
     */
    public function recipientKind(): string
    {
        $localPart = strtolower(explode('@', (string) $this->recipient_email)[0]);

        return in_array($localPart, OutreachLimits::GENERIC_LOCAL_PARTS, true) ? 'generic' : 'named';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<JobPosting, $this>
     */
    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class);
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @return BelongsTo<ApplicationProfile, $this>
     */
    public function applicationProfile(): BelongsTo
    {
        return $this->belongsTo(ApplicationProfile::class);
    }
}
