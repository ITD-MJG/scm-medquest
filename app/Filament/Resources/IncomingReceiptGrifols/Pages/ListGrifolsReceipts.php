<?php

namespace App\Filament\Resources\IncomingReceiptGrifols\Pages;

use App\Filament\Resources\IncomingReceiptGrifols\GrifolsReceiptResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGrifolsReceipts extends ListRecords
{
    protected static string $resource = GrifolsReceiptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
