<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\PlanKey;
use App\Enums\Region;
use App\Enums\SendingPauseReason;
use App\Enums\UserStatus;
use App\Events\Client\Concerns\DispatchesClientEvent;
use App\Notifications\Client\ResetPassword;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property bool $is_admin
 * @property UserStatus $status
 * @property string $locale
 * @property string|null $timezone
 * @property string|null $country
 * @property Region $region
 * @property PlanKey $plan_key
 * @property Carbon|null $sending_paused_at
 * @property SendingPauseReason|null $sending_pause_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read JobPreference|null $jobPreference
 * @property-read Collection<int, ConnectedIntegration> $connectedIntegrations
 * @property-read ConnectedIntegration|null $gmailIntegration
 * @property-read Collection<int, Application> $applications
 * @property-read Collection<int, ApplicationProfile> $applicationProfiles
 */
#[Fillable(['name', 'email', 'password', 'is_admin', 'status', 'locale', 'timezone', 'country', 'region', 'plan_key'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasLocalePreference
{
    use DispatchesClientEvent;

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected static function booted(): void
    {
        static::saved(function (User $user): void {
            if ($user->wasChanged(['plan_key', 'sending_paused_at', 'sending_pause_reason'])) {
                static::dispatchAccountStatusUpdated($user->id);
            }
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
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'status' => UserStatus::class,
            'region' => Region::class,
            'plan_key' => PlanKey::class,
            'sending_paused_at' => 'datetime',
            'sending_pause_reason' => SendingPauseReason::class,
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'admin' => $this->is_admin && $this->status === UserStatus::Active,
            default => false,
        };
    }

    /**
     * @return HasOne<JobPreference, $this>
     */
    public function jobPreference(): HasOne
    {
        return $this->hasOne(JobPreference::class);
    }

    /**
     * @return HasMany<ConnectedIntegration, $this>
     */
    public function connectedIntegrations(): HasMany
    {
        return $this->hasMany(ConnectedIntegration::class);
    }

    /**
     * @return HasOne<ConnectedIntegration, $this>
     */
    public function gmailIntegration(): HasOne
    {
        return $this->hasOne(ConnectedIntegration::class)->where('plugin_key', 'gmail');
    }

    /**
     * @return HasMany<Application, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    /**
     * @return HasMany<ApplicationProfile, $this>
     */
    public function applicationProfiles(): HasMany
    {
        return $this->hasMany(ApplicationProfile::class);
    }

    public function preferredLocale(): ?string
    {
        return $this->locale;
    }

    /**
     * Untyped to stay compatible with the CanResetPassword contract signature.
     *
     * @param  string  $token
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPassword($token));
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function isSendingPaused(): bool
    {
        return $this->sending_paused_at !== null;
    }

    /**
     * First letter of the first and last name words, uppercased ("Ada Lovelace" -> "AL").
     */
    public function initials(): string
    {
        $words = preg_split('/\s+/u', trim($this->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($words === []) {
            return '';
        }

        $first = mb_substr($words[0], 0, 1);
        $last = count($words) > 1 ? mb_substr($words[count($words) - 1], 0, 1) : '';

        return mb_strtoupper($first.$last);
    }
}
