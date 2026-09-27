<?php

namespace App\Events\Client;

use App\Http\Resources\Client\ApplicationItemResource;
use App\Models\Application;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

final class ApplicationProgressed implements ShouldBroadcastNow
{
    public function __construct(
        public int $applicationId,
        public int $userId,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('App.Models.User.'.$this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'application.progressed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $application = Application::query()
            ->with(['company', 'jobPosting.profile', 'user'])
            ->findOrFail($this->applicationId);

        return ['application' => (new ApplicationItemResource($application))->resolve()];
    }
}
