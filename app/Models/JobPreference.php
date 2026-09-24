<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property list<string> $titles
 * @property list<string> $keywords
 * @property list<string> $stack
 * @property list<string> $locations
 * @property bool $accepts_remote
 * @property string|null $cv_path
 * @property string|null $cv_original_name
 * @property string|null $email_subject
 * @property string|null $email_body
 * @property bool $auto_send_enabled
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 */
#[Fillable(['user_id', 'titles', 'keywords', 'stack', 'locations', 'accepts_remote', 'cv_path', 'cv_original_name', 'email_subject', 'email_body', 'auto_send_enabled'])]
class JobPreference extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'titles' => 'array',
            'keywords' => 'array',
            'stack' => 'array',
            'locations' => 'array',
            'accepts_remote' => 'boolean',
            'auto_send_enabled' => 'boolean',
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
