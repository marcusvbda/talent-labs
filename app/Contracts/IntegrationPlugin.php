<?php

namespace App\Contracts;

use App\Models\ConnectedIntegration;

interface IntegrationPlugin
{
    public function key(): string;

    public function label(): string;

    public function description(): string;

    public function category(): string;

    public function icon(): string;

    /** @return list<string> */
    public function capabilities(): array;

    public function disconnect(ConnectedIntegration $integration): void;
}
