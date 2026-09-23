<?php

namespace App\Models;

use App\Enums\SourceAdapter;
use App\Enums\SourceRunStatus;
use App\Models\Concerns\BroadcastsRealtime;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property SourceAdapter $adapter
 * @property string|null $identifier
 * @property array<string, mixed>|null $settings
 * @property int $interval_minutes
 * @property bool $is_active
 * @property CarbonImmutable|null $last_run_at
 * @property SourceRunStatus|null $last_run_status
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, SourceRun> $sourceRuns
 * @property-read Collection<int, JobPosting> $jobPostings
 */
#[Fillable(['name', 'adapter', 'identifier', 'settings', 'interval_minutes', 'is_active', 'last_run_at', 'last_run_status'])]
class Source extends Model
{
    use BroadcastsRealtime;

    protected static function booted(): void
    {
        static::saved(function (Source $source): void {
            static::broadcastUpdated($source);
        });

        static::deleted(function (Source $source): void {
            static::broadcastUpdated($source);
        });
    }

    protected static function broadcastUpdated(Source $source): void
    {
        static::broadcastRealtime('sources', 'SourceUpdated', ['id' => $source->id]);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'adapter' => SourceAdapter::class,
            'settings' => 'array',
            'is_active' => 'boolean',
            'interval_minutes' => 'integer',
            'last_run_at' => 'datetime',
            'last_run_status' => SourceRunStatus::class,
        ];
    }

    /**
     * @return HasMany<SourceRun, $this>
     */
    public function sourceRuns(): HasMany
    {
        return $this->hasMany(SourceRun::class);
    }

    /**
     * @return HasMany<JobPosting, $this>
     */
    public function jobPostings(): HasMany
    {
        return $this->hasMany(JobPosting::class);
    }

    /**
     * Whether the source has run or collected postings (and so cannot be deleted).
     */
    public function hasHistory(): bool
    {
        return $this->sourceRuns()->exists() || $this->jobPostings()->exists();
    }

    /**
     * @param  Builder<Source>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
