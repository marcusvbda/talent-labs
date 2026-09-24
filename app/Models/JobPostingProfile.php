<?php

namespace App\Models;

use App\Enums\ProfileStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $job_posting_id
 * @property ProfileStatus $status
 * @property string $schema_version
 * @property string|null $normalized_title
 * @property string|null $seniority
 * @property list<string> $stack
 * @property list<string> $locations
 * @property bool|null $is_remote
 * @property string|null $summary
 * @property CarbonImmutable|null $extracted_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read JobPosting $jobPosting
 */
#[Fillable(['job_posting_id', 'status', 'schema_version', 'normalized_title', 'seniority', 'stack', 'locations', 'is_remote', 'summary', 'extracted_at'])]
class JobPostingProfile extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProfileStatus::class,
            'stack' => 'array',
            'locations' => 'array',
            'is_remote' => 'boolean',
            'extracted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<JobPosting, $this>
     */
    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class);
    }
}
