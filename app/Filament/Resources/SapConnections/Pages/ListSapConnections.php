<?php

namespace App\Filament\Resources\SapConnections\Pages;

use App\Filament\Resources\SapConnections\SapConnectionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSapConnections extends ListRecords
{
    protected static string $resource = SapConnectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
