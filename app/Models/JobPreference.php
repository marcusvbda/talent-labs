<?php

namespace App\Models;

use App\Enums\RemoteMode;
use App\Events\Client\Concerns\DispatchesClientEvent;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property list<string> $titles
 * @property list<string> $seniorities
 * @property list<string> $stack
 * @property list<string> $locations
 * @property RemoteMode $remote_mode
 * @property list<string> $exclude_words
 * @property CarbonImmutable|null $saved_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 */
#[Fillable(['user_id', 'titles', 'seniorities', 'stack', 'locations', 'remote_mode', 'exclude_words', 'saved_at'])]
class JobPreference extends Model
{
    use DispatchesClientEvent;

    /**
     * Mirrors the column defaults so a freshly created row is complete without a refresh.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'titles' => '[]',
        'seniorities' => '[]',
        'stack' => '[]',
        'locations' => '[]',
        'remote_mode' => 'remote_or_locations',
        'exclude_words' => '[]',
    ];

    protected static function booted(): void
    {
        static::saved(function (JobPreference $preference): void {
            if ($preference->wasChanged('saved_at')) {
                static::dispatchAccountStatusUpdated($preference->user_id);
            }
        });
    }

    /**
     * The user's preference row, created when missing.
     * Never marks the preferences as saved.
     */
    public static function forUser(User $user): self
    {
        return self::firstOrCreate(['user_id' => $user->id]);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'titles' => 'array',
            'seniorities' => 'array',
            'stack' => 'array',
            'locations' => 'array',
            'remote_mode' => RemoteMode::class,
            'exclude_words' => 'array',
            'saved_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
