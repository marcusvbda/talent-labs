<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\PlanSource;
use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['plan_source'] = PlanSource::Manual->value;

        return $data;
    }
}
