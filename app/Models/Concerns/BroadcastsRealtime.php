<?php

namespace App\Models\Concerns;

use Illuminate\Broadcasting\BroadcastException;
use Marcusvbda\FilamentRealtimeDriver\RealtimeEvent;

trait BroadcastsRealtime
{
    /**
     * Realtime is best-effort: an unreachable broadcaster must never fail a write.
     *
     * @param  array<string, mixed>  $payload
     */
    protected static function broadcastRealtime(string $channel, string $event, array $payload): void
    {
        try {
            RealtimeEvent::dispatch($channel, $event, $payload);
        } catch (BroadcastException $e) {
            report($e);
        }
    }
}
