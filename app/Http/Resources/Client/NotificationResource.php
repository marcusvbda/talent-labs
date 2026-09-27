<?php

namespace App\Http\Resources\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;

/**
 * The `NotificationItem` contract. `data` only carries flat scalars: nested values and
 * anything that looks like a URL, domain or email address are dropped.
 *
 * @property DatabaseNotification $resource
 */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $notification = $this->resource;
        $data = $notification->data;

        $type = $data['type'] ?? null;

        return [
            'id' => (string) $notification->id,
            'type' => is_string($type) && $type !== ''
                ? $type
                : Str::snake(class_basename($notification->type)),
            'data' => self::safeData($data),
            'readAt' => $notification->read_at?->toIso8601String(),
            'createdAt' => $notification->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<string, string|int|null>
     */
    private static function safeData(array $data): array
    {
        $safe = [];

        foreach ($data as $key => $value) {
            if ($key === 'type') {
                continue;
            }

            $value = match (true) {
                $value === null => null,
                is_int($value) => $value,
                is_bool($value) => (int) $value,
                is_float($value), is_string($value) => (string) $value,
                default => false,
            };

            if ($value === false || (is_string($value) && self::looksLikeLinkOrAddress($value))) {
                continue;
            }

            $safe[(string) $key] = $value;
        }

        return $safe;
    }

    private static function looksLikeLinkOrAddress(string $value): bool
    {
        return str_contains($value, '@')
            || stripos($value, 'http') !== false
            || str_contains($value, '://')
            || stripos($value, 'www.') !== false
            || preg_match('/[a-z0-9-]+\.[a-z]{2,}(?![a-z])/i', $value) === 1;
    }
}
