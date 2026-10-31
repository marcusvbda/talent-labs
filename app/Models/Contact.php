<?php

namespace App\Models;

use App\Enums\ContactConfidence;
use App\Enums\ContactSource;
use App\Enums\ContactTier;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $company_id
 * @property ContactSource $source
 * @property int|null $job_posting_id
 * @property string $email
 * @property string $local_part
 * @property string|null $person_name
 * @property string|null $person_title
 * @property ContactConfidence $confidence
 * @property CarbonImmutable $checked_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Company $company
 * @property-read JobPosting|null $jobPosting
 */
#[Fillable(['company_id', 'source', 'job_posting_id', 'email', 'local_part', 'person_name', 'person_title', 'confidence', 'checked_at'])]
class Contact extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => ContactSource::class,
            'confidence' => ContactConfidence::class,
            'checked_at' => 'datetime',
        ];
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
     * The derived tier (never stored): A for an email published in a stored posting and not
     * rejected, B for a named person verified by SMTP, C for a role alias verified by SMTP.
     */
    public function tier(): ?ContactTier
    {
        return match (true) {
            $this->source === ContactSource::Posting
                && $this->job_posting_id !== null
                && $this->confidence !== ContactConfidence::Rejected => ContactTier::A,
            $this->source === ContactSource::Named
                && $this->confidence === ContactConfidence::SmtpVerified => ContactTier::B,
            $this->source === ContactSource::AliasProbe
                && $this->confidence === ContactConfidence::SmtpVerified => ContactTier::C,
            default => null,
        };
    }
}
