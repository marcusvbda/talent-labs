<?php

namespace App\Events\Client\Concerns;

use App\Events\Client\AccountStatusUpdated;
use Illuminate\Support\Facades\Cache;
use Throwable;

trait DispatchesClientEvent
{
    /**
     * Client realtime is best-effort: nothing that happens while dispatching (an
     * unreachable broadcaster, or building the payload in a synchronous
     * `broadcastWith()`) may fail the write that triggered it.
     */
    protected static function dispatchClientEvent(object $event): void
    {
        try {
            event($event);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * At most one `account.updated` per user per second. The lock is taken without
     * blocking; when it is already held the dispatch is skipped.
     */
    protected static function dispatchAccountStatusUpdated(int $userId): void
    {
        try {
            $acquired = Cache::lock("account-updated:{$userId}", 1)->get();
        } catch (Throwable $e) {
            report($e);

            return;
        }

        if ($acquired) {
            self::dispatchClientEvent(new AccountStatusUpdated($userId));
        }
    }
}
