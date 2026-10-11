<?php

namespace App\Filament\Resources\SapConnections\Pages;

use App\Filament\Resources\SapConnections\SapConnectionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSapConnection extends EditRecord
{
    protected static string $resource = SapConnectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
