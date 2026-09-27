<?php

namespace App\Events\Client;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Public channel: ids and counts only, never a title, company or URL.
 */
final class JobsCollected implements ShouldBroadcastNow
{
    public function __construct(
        public int $collectionRunId,
        public int $newJobs,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel('jobs')];
    }

    public function broadcastAs(): string
    {
        return 'jobs.collected';
    }

    /**
     * @return array{collectionRunId: int, newJobs: int}
     */
    public function broadcastWith(): array
    {
        return [
            'collectionRunId' => $this->collectionRunId,
            'newJobs' => $this->newJobs,
        ];
    }
}
