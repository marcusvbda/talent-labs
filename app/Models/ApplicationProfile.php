<?php

namespace App\Models;

use App\Enums\ApplicationLanguage;
use App\Events\Client\Concerns\DispatchesClientEvent;
use App\Outreach\Actions\CanSendApplications;
use App\Outreach\Support\ApplicationTemplateRenderer;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $user_id
 * @property ApplicationLanguage $language
 * @property bool $is_active
 * @property string|null $cv_path
 * @property string|null $cv_original_name
 * @property int|null $cv_size_bytes
 * @property CarbonImmutable|null $cv_uploaded_at
 * @property string $email_subject
 * @property string $email_body
 * @property string|null $cover_letter
 * @property list<array{label: string, url: string}>|null $links
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 */
#[Fillable(['user_id', 'language', 'is_active', 'cv_path', 'cv_original_name', 'cv_size_bytes', 'cv_uploaded_at', 'email_subject', 'email_body', 'cover_letter', 'links'])]
class ApplicationProfile extends Model
{
    use DispatchesClientEvent;

    protected static function booted(): void
    {
        static::saved(function (ApplicationProfile $profile): void {
            static::dispatchAccountStatusUpdated($profile->user_id);
        });

        static::deleted(function (ApplicationProfile $profile): void {
            static::dispatchAccountStatusUpdated($profile->user_id);
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
            'language' => ApplicationLanguage::class,
            'is_active' => 'bool',
            'cv_size_bytes' => 'int',
            'cv_uploaded_at' => 'immutable_datetime',
            'links' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The CV must live under the owner's own cvs/{userId}/ folder.
     */
    public static function isOwnCvPath(string $path, int $userId): bool
    {
        return str_starts_with($path, 'cvs/'.$userId.'/')
            && ! str_contains($path, '\\')
            && ! str_contains($path, "\0")
            && ! in_array('..', explode('/', $path), true);
    }

    public function hasOwnedCv(): bool
    {
        return filled($this->cv_path)
            && self::isOwnCvPath($this->cv_path, $this->user_id)
            && Storage::disk('local')->exists($this->cv_path);
    }

    /**
     * What still blocks this profile from sending.
     *
     * @return list<'cv'|'subject'|'body'>
     */
    public function missing(): array
    {
        $missing = [];

        if (! $this->hasOwnedCv()) {
            $missing[] = 'cv';
        }

        if (! self::isValidTemplate($this->email_subject, CanSendApplications::MAX_SUBJECT_LENGTH)) {
            $missing[] = 'subject';
        }

        if (! self::isValidTemplate($this->email_body, CanSendApplications::MAX_BODY_LENGTH)) {
            $missing[] = 'body';
        }

        return $missing;
    }

    public function isComplete(): bool
    {
        return $this->missing() === [];
    }

    /**
     * Languages of the user's active and complete profiles, in canonical (en, pt) order.
     *
     * @return list<string>
     */
    public static function activeCompleteLanguagesFor(User $user): array
    {
        $complete = $user->applicationProfiles()
            ->where('is_active', true)
            ->get()
            ->filter(fn (ApplicationProfile $profile): bool => $profile->isComplete())
            ->map(fn (ApplicationProfile $profile): string => $profile->language->value)
            ->all();

        return array_values(array_filter(
            array_map(fn (ApplicationLanguage $language): string => $language->value, ApplicationLanguage::cases()),
            fn (string $language): bool => in_array($language, $complete, true),
        ));
    }

    private static function isValidTemplate(?string $text, int $maxLength): bool
    {
        $text = trim((string) $text);

        return $text !== ''
            && mb_strlen($text) <= $maxLength
            && ApplicationTemplateRenderer::unknownVariables($text) === [];
    }
}
