<?php

namespace App\Filament\Resources\Principals\Pages;

use App\Filament\Resources\Principals\PrincipalResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPrincipal extends EditRecord
{
    protected static string $resource = PrincipalResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotification(): Notification
    {
        return Notification::make()
            ->title('Principal successfully updated');
    }
}
