<?php

namespace App\Filament\Resources\IncomingReceipts\Pages;

use App\Filament\Resources\IncomingReceipts\IncomingReceiptResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewIncomingReceipt extends ViewRecord
{
    protected static string $resource = IncomingReceiptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
