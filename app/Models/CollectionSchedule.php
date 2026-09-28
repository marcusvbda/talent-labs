<?php

namespace App\Models;

use App\Models\Concerns\BroadcastsRealtime;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Singleton (id 1) holding the automatic collection schedule.
 *
 * @property int $id
 * @property bool $enabled
 * @property list<string> $times
 * @property string $timezone
 * @property string|null $last_slot_key
 * @property CarbonImmutable|null $last_dispatched_at
 * @property string|null $last_result
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['enabled', 'times', 'timezone', 'last_slot_key', 'last_dispatched_at', 'last_result', 'updated_by'])]
class CollectionSchedule extends Model
{
    use BroadcastsRealtime;

    private static ?self $current = null;

    protected static function booted(): void
    {
        static::saved(function (CollectionSchedule $schedule): void {
            static::forgetCurrent();
            static::broadcastRealtime('collection_schedule', 'CollectionScheduleUpdated', ['id' => $schedule->id]);
        });

        static::deleted(function (CollectionSchedule $schedule): void {
            static::broadcastRealtime('collection_schedule', 'CollectionScheduleUpdated', ['id' => $schedule->id]);
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
            'enabled' => 'boolean',
            'times' => 'array',
            'last_dispatched_at' => 'datetime',
            'updated_by' => 'integer',
        ];
    }

    /**
     * @return array{enabled: bool, times: list<string>, timezone: string}
     */
    public static function defaults(): array
    {
        return [
            'enabled' => true,
            'times' => ['06:00', '18:00'],
            'timezone' => (string) config('app.timezone'),
        ];
    }

    /**
     * The singleton schedule row (id 1), created with defaults when missing.
     */
    public static function current(): self
    {
        return self::$current ??= static::unguarded(
            fn (): self => static::query()->firstOrCreate(['id' => 1], static::defaults()),
        );
    }

    public static function forgetCurrent(): void
    {
        self::$current = null;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Next configured slot strictly after $now, in the schedule timezone.
     */
    public function nextRunAt(?CarbonImmutable $now = null): ?CarbonImmutable
    {
        $times = array_values(array_filter($this->times ?? [], 'is_string'));

        if (! $this->enabled || $times === []) {
            return null;
        }

        sort($times);

        $now = ($now ?? CarbonImmutable::now())->setTimezone($this->timezone)->startOfMinute();
        $current = $now->format('H:i');

        foreach ($times as $time) {
            if ($time > $current) {
                return $this->atTime($now, $time);
            }
        }

        return $this->atTime($now->addDay(), $times[0]);
    }

    private function atTime(CarbonImmutable $day, string $time): CarbonImmutable
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));

        return $day->setTime($hour, $minute);
    }
}
