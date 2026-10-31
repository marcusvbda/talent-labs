<?php

namespace App\Events\Client;

use App\Ai\Support\CoverLetterQuota;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * The outcome of one cover letter generation for the review screen. `body` is built
 * by ReviewDraftPresenter::bodyFor() only: the job URL stays a token and no recipient,
 * tier or source ever travels here.
 */
final class CoverLetterUpdated implements ShouldBroadcastNow
{
    public const STATE_READY = 'ready';

    public const STATE_MANUAL = 'manual';

    public const REASON_QUOTA = 'quota';

    public const REASON_FAILED = 'failed';

    /**
     * @param  self::STATE_*  $state
     * @param  self::REASON_*|null  $reason
     */
    public function __construct(
        public int $userId,
        public int $jobId,
        public string $state,
        public ?string $reason = null,
        public ?string $body = null,
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
        return 'cover_letter.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $user = User::findOrFail($this->userId);

        return [
            'jobId' => $this->jobId,
            'state' => $this->state,
            'reason' => $this->reason,
            'body' => $this->body,
            'aiQuota' => [
                'used' => CoverLetterQuota::used($user),
                'limit' => CoverLetterQuota::limit($user),
            ],
        ];
    }
}
