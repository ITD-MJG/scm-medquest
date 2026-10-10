<?php

namespace App\Filament\Resources\Principals\Pages;

use App\Filament\Resources\Principals\PrincipalResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreatePrincipal extends CreateRecord
{
    protected static string $resource = PrincipalResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['principalId'] = Str::orderedUuid();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotification(): Notification
    {
        return Notification::make()
            ->title('Principal successfully created');
    }
}
