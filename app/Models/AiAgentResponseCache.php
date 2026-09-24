<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $agent
 * @property string $model
 * @property string $request_hash
 * @property array<string, mixed> $response
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['agent', 'model', 'request_hash', 'response'])]
class AiAgentResponseCache extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'response' => 'array',
        ];
    }

    /**
     * Look up a previously cached agent response for the exact same request.
     *
     * @return array<string, mixed>|null
     */
    public static function lookup(string $agent, string $model, string $fingerprint): ?array
    {
        $cached = static::query()
            ->where('agent', $agent)
            ->where('request_hash', self::hash($model, $fingerprint))
            ->first();

        return $cached?->response;
    }

    /**
     * Persist an agent response so an identical future request can be served from cache.
     *
     * @param  array<string, mixed>  $response
     */
    public static function remember(string $agent, string $model, string $fingerprint, array $response): void
    {
        static::query()->firstOrCreate([
            'agent' => $agent,
            'request_hash' => self::hash($model, $fingerprint),
        ], [
            'model' => $model,
            'response' => $response,
        ]);
    }

    private static function hash(string $model, string $fingerprint): string
    {
        return hash('sha256', $model."\n".$fingerprint);
    }
}
