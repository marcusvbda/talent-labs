<?php

namespace App\Models;

use App\Enums\CollectionRunStatus;
use App\Models\Concerns\BroadcastsRealtime;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property CollectionRunStatus $status
 * @property int|null $triggered_by
 * @property string|null $batch_id
 * @property int $sources_total
 * @property int $sources_succeeded
 * @property int $sources_failed
 * @property int $jobs_fetched
 * @property int $jobs_new
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $finished_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read string $label
 */
#[Fillable(['status', 'triggered_by', 'batch_id', 'sources_total', 'sources_succeeded', 'sources_failed', 'jobs_fetched', 'jobs_new', 'started_at', 'finished_at'])]
class CollectionRun extends Model
{
    use BroadcastsRealtime;

    protected static function booted(): void
    {
        static::saved(function (CollectionRun $run): void {
            static::broadcastRunUpdated($run);
        });

        static::deleted(function (CollectionRun $run): void {
            static::broadcastRunUpdated($run);
        });
    }

    protected static function broadcastRunUpdated(CollectionRun $run): void
    {
        static::broadcastRealtime('collection_runs', 'CollectionRunUpdated', ['id' => $run->id]);
        static::broadcastRealtime('collection_run_'.$run->id, 'CollectionRunUpdated', ['id' => $run->id]);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CollectionRunStatus::class,
            'triggered_by' => 'integer',
            'sources_total' => 'integer',
            'sources_succeeded' => 'integer',
            'sources_failed' => 'integer',
            'jobs_fetched' => 'integer',
            'jobs_new' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
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
        return $this->hasMany(JobPosting::class, 'collection_run_id');
    }

    /**
     * @return Attribute<string, never>
     */
    protected function label(): Attribute
    {
        return Attribute::get(fn (): string => $this->formatLabel());
    }

    protected function formatLabel(): string
    {
        if ($this->started_at === null) {
            return "Run #{$this->id}";
        }

        return "Run #{$this->id} · ".$this->started_at->timezone(config('app.timezone'))->format('j M Y H:i');
    }

    /**
     * @param  Builder<CollectionRun>  $query
     */
    public function scopeStartedToday(Builder $query): void
    {
        $now = now(config('app.timezone'));

        $query->whereBetween('started_at', [$now->copy()->startOfDay(), $now->copy()->endOfDay()]);
    }

    /**
     * @param  Builder<CollectionRun>  $query
     */
    public function scopeInProgress(Builder $query): void
    {
        $query->whereIn('status', [CollectionRunStatus::Pending, CollectionRunStatus::Running]);
    }
}
