<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ApplicationStatus: string implements HasColor, HasLabel
{
    case Queued = 'queued';
    case Sending = 'sending';
    case Sent = 'sent';
    case Failed = 'failed';
    case Ambiguous = 'ambiguous';
    case Cancelled = 'cancelled';

    /**
     * Statuses that consume the daily send quota (everything except failed and cancelled).
     *
     * @return list<self>
     */
    public static function countedTowardsQuota(): array
    {
        return [self::Queued, self::Sending, self::Sent, self::Ambiguous];
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Queued => 'Queued',
            self::Sending => 'Sending',
            self::Sent => 'Sent',
            self::Failed => 'Failed',
            self::Ambiguous => 'Needs review',
            self::Cancelled => 'Cancelled',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Queued => 'gray',
            self::Sending => 'info',
            self::Sent => 'success',
            self::Failed => 'danger',
            self::Ambiguous => 'warning',
            self::Cancelled => 'gray',
        };
    }
}
