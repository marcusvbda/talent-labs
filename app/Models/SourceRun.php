<?php

namespace App\Models;

use App\Enums\SourceRunStatus;
use App\Models\Concerns\BroadcastsRealtime;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $collection_run_id
 * @property int $source_id
 * @property SourceRunStatus $status
 * @property int $jobs_fetched
 * @property int $jobs_new
 * @property string|null $error_message
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $finished_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['collection_run_id', 'source_id', 'status', 'jobs_fetched', 'jobs_new', 'error_message', 'started_at', 'finished_at'])]
class SourceRun extends Model
{
    use BroadcastsRealtime;

    protected static function booted(): void
    {
        static::saved(function (SourceRun $sourceRun): void {
            static::broadcastRealtime(
                'collection_run_'.$sourceRun->collection_run_id,
                'CollectionRunUpdated',
                ['id' => $sourceRun->collection_run_id],
            );
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SourceRunStatus::class,
            'collection_run_id' => 'integer',
            'source_id' => 'integer',
            'jobs_fetched' => 'integer',
            'jobs_new' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CollectionRun, $this>
     */
    public function collectionRun(): BelongsTo
    {
        return $this->belongsTo(CollectionRun::class);
    }

    /**
     * @return BelongsTo<Source, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }
}
