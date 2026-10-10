<?php

namespace App\Filament\Resources\IncomingReceipts\Pages;

use App\Filament\Resources\IncomingReceipts\IncomingReceiptResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListIncomingReceipts extends ListRecords
{
    protected static string $resource = IncomingReceiptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
