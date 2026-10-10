<?php

namespace App\Filament\Resources\IncomingReceipts\Pages;

use App\Filament\Resources\IncomingReceipts\IncomingReceiptResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditIncomingReceipt extends EditRecord
{
    protected static string $resource = IncomingReceiptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
