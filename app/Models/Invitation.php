<?php

namespace App\Models;

use App\Enums\PlanKey;
use App\Models\Concerns\BroadcastsRealtime;
use App\Plans\PlanCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $token_hash
 * @property string|null $email
 * @property string|null $note
 * @property PlanKey|null $plan_key
 * @property int $created_by
 * @property int|null $used_by
 * @property CarbonImmutable|null $used_at
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $revoked_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $creator
 * @property-read User|null $usedBy
 */
#[Fillable(['token_hash', 'email', 'note', 'plan_key', 'created_by', 'used_by', 'used_at', 'expires_at', 'revoked_at'])]
class Invitation extends Model
{
    use BroadcastsRealtime;

    protected static function booted(): void
    {
        static::saved(function (Invitation $invitation): void {
            static::broadcastUpdated($invitation);
        });

        static::deleted(function (Invitation $invitation): void {
            static::broadcastUpdated($invitation);
        });
    }

    protected static function broadcastUpdated(Invitation $invitation): void
    {
        static::broadcastRealtime('invitations', 'InvitationsUpdated', ['id' => $invitation->id]);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'plan_key' => PlanKey::class,
            'used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isUsable(): bool
    {
        return ! $this->isUsed() && ! $this->isRevoked() && ! $this->isExpired();
    }

    /**
     * The invitation's plan, or the configured default plan when none is set.
     */
    public function planKeyOrDefault(): PlanKey
    {
        return $this->plan_key ?? app(PlanCatalog::class)->get((string) config('talent.plans.default'))->key;
    }

    /**
     * Invitations that are not used, not revoked and not expired.
     *
     * @param  Builder<Invitation>  $query
     */
    #[Scope]
    protected function usable(Builder $query): void
    {
        $query
            ->whereNull('used_at')
            ->whereNull('revoked_at')
            ->where(function (Builder $query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function usedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'used_by');
    }
}
