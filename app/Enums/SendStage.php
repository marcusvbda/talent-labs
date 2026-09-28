<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SendStage: string implements HasLabel
{
    case ValidatingRecipient = 'validating_recipient';
    case AdaptingTemplate = 'adapting_template';
    case AttachingCv = 'attaching_cv';
    case Sending = 'sending';
    case Sent = 'sent';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::ValidatingRecipient => 'Validating recipient',
            self::AdaptingTemplate => 'Adapting template',
            self::AttachingCv => 'Attaching CV',
            self::Sending => 'Sending',
            self::Sent => 'Sent',
            self::Failed => 'Failed',
        };
    }
}
