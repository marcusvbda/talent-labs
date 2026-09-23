<?php

namespace App\Filament\Resources\Companies\Pages;

use App\Filament\Resources\Companies\CompanyResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class ViewCompany extends ViewRecord
{
    protected static string $resource = CompanyResource::class;

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                View::make('filament.companies.realtime-listener')
                    ->viewData(['record' => $this->getRecord()]),
                $this->getInfolistContentComponent(),
                $this->getRelationManagersContentComponent(),
            ]);
    }
}
