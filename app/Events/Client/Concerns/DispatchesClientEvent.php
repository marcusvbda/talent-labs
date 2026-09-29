<?php

namespace App\Events\Client\Concerns;

use App\Events\Client\AccountStatusUpdated;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
     *
     * Runs after the surrounding transaction commits: the database cache lock does an
     * insert that fails when the key exists, and on PostgreSQL that failure would abort
     * the caller's transaction (SQLSTATE 25P02). It also lets clients read committed state.
     */
    protected static function dispatchAccountStatusUpdated(int $userId): void
    {
        DB::afterCommit(static fn () => self::acquireAndDispatchAccountStatusUpdated($userId));
    }

    private static function acquireAndDispatchAccountStatusUpdated(int $userId): void
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
