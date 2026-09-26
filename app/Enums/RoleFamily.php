<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum RoleFamily: string implements HasLabel
{
    case Backend = 'backend';
    case Frontend = 'frontend';
    case Fullstack = 'fullstack';
    case Software = 'software';
    case Mobile = 'mobile';
    case Devops = 'devops';
    case Qa = 'qa';
    case Support = 'support';
    case CustomerService = 'customer_service';
    case Product = 'product';

    public function getLabel(): string
    {
        return match ($this) {
            self::Backend => 'Backend',
            self::Frontend => 'Frontend',
            self::Fullstack => 'Full stack',
            self::Software => 'Software (other)',
            self::Mobile => 'Mobile',
            self::Devops => 'DevOps / SRE',
            self::Qa => 'QA',
            self::Support => 'Support',
            self::CustomerService => 'Customer service',
            self::Product => 'Product',
        };
    }
}
