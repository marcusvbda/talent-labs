<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SendSubStep: string implements HasLabel
{
    case CheckingCompany = 'checking_company';
    case ConfirmingRecipient = 'confirming_recipient';
    case CheckingGmail = 'checking_gmail';
    case FillingVariables = 'filling_variables';
    case BuildingHtml = 'building_html';
    case OpeningCv = 'opening_cv';
    case CheckingPdf = 'checking_pdf';
    case AttachingFile = 'attaching_file';
    case ConnectingGmail = 'connecting_gmail';
    case Delivering = 'delivering';

    public function getLabel(): string
    {
        return match ($this) {
            self::CheckingCompany => 'Checking company',
            self::ConfirmingRecipient => 'Confirming recipient',
            self::CheckingGmail => 'Checking Gmail',
            self::FillingVariables => 'Filling variables',
            self::BuildingHtml => 'Building HTML',
            self::OpeningCv => 'Opening CV',
            self::CheckingPdf => 'Checking PDF',
            self::AttachingFile => 'Attaching file',
            self::ConnectingGmail => 'Connecting to Gmail',
            self::Delivering => 'Delivering',
        };
    }

    public function stage(): SendStage
    {
        return match ($this) {
            self::CheckingCompany, self::ConfirmingRecipient, self::CheckingGmail => SendStage::ValidatingRecipient,
            self::FillingVariables, self::BuildingHtml => SendStage::AdaptingTemplate,
            self::OpeningCv, self::CheckingPdf, self::AttachingFile => SendStage::AttachingCv,
            self::ConnectingGmail, self::Delivering => SendStage::Sending,
        };
    }
}
