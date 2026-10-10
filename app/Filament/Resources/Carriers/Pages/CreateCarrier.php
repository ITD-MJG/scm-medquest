<?php

namespace App\Filament\Resources\Carriers\Pages;

use App\Filament\Resources\Carriers\CarrierResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateCarrier extends CreateRecord
{
    protected static string $resource = CarrierResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['carrierId'] = Str::orderedUuid();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotification(): Notification
    {
        return Notification::make()
            ->title('Carrier successfully created');
    }
}
