<?php

namespace App\Models;

use App\Enums\AiUsageStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $agent
 * @property string $model
 * @property int|null $job_posting_id
 * @property int $input_tokens
 * @property int $output_tokens
 * @property bool $cache_hit
 * @property int|null $duration_ms
 * @property AiUsageStatus $status
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read JobPosting|null $jobPosting
 */
#[Fillable(['agent', 'model', 'job_posting_id', 'input_tokens', 'output_tokens', 'cache_hit', 'duration_ms', 'status'])]
class AiUsageRecord extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AiUsageStatus::class,
            'cache_hit' => 'boolean',
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
