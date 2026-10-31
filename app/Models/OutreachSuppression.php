<?php

namespace App\Models;

use App\Enums\SuppressionSource;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * A domain or a single address that outreach must never probe or write to. Exactly one of
 * `domain` and `email` is set; both are stored lowercase. Only confirmed rows take effect.
 *
 * @property int $id
 * @property string|null $domain
 * @property string|null $email
 * @property string|null $reason
 * @property SuppressionSource $source
 * @property CarbonImmutable|null $confirmed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['domain', 'email', 'reason', 'source', 'confirmed_at'])]
class OutreachSuppression extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => SuppressionSource::class,
            'confirmed_at' => 'datetime',
        ];
    }

    /**
     * @return Attribute<string|null, string|null>
     */
    protected function domain(): Attribute
    {
        return Attribute::make(set: fn (?string $value): ?string => self::normalize($value));
    }

    /**
     * @return Attribute<string|null, string|null>
     */
    protected function email(): Attribute
    {
        return Attribute::make(set: fn (?string $value): ?string => self::normalize($value));
    }

    private static function normalize(?string $value): ?string
    {
        $value = $value === null ? null : strtolower(trim($value));

        return $value === '' ? null : $value;
    }
}
