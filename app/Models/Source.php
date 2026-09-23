<?php

namespace App\Models;

use App\Enums\SourceAdapter;
use App\Enums\SourceRunStatus;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Marcusvbda\FilamentRealtimeDriver\RealtimeEvent;

/**
 * @property int $id
 * @property string $name
 * @property SourceAdapter $adapter
 * @property string|null $identifier
 * @property array<string, mixed>|null $settings
 * @property int $interval_minutes
 * @property bool $is_active
 * @property Carbon|null $last_run_at
 * @property SourceRunStatus|null $last_run_status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'adapter', 'identifier', 'settings', 'interval_minutes', 'is_active', 'last_run_at', 'last_run_status'])]
class Source extends Model
{
    protected static function booted(): void
    {
        static::saved(function (Source $source): void {
            static::broadcastUpdated($source);
        });

        static::deleted(function (Source $source): void {
            static::broadcastUpdated($source);
        });
    }

    /**
     * Realtime is best-effort: an unreachable broadcaster must never fail a write.
     */
    protected static function broadcastUpdated(Source $source): void
    {
        try {
            RealtimeEvent::dispatch('sources', 'SourceUpdated', ['id' => $source->id]);
        } catch (BroadcastException $e) {
            report($e);
        }
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
     * @param  Builder<Source>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
