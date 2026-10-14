<?php

namespace App\Filament\Resources\Principals\Pages;

use App\Filament\Resources\Principals\PrincipalResource;
use App\Services\Sap\ProductImportService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Throwable;

class ListPrincipal extends ListRecords
{
    protected static string $resource = PrincipalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pull_from_sap')
                ->label('Pull from SAP')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->modalDescription('Import principals and their products from the active SAP HANA connection. Existing manual records are kept.')
                ->action(function (): void {
                    try {
                        $result = app(ProductImportService::class)->importPrincipals();
                    } catch (Throwable $e) {
                        Notification::make()
                            ->danger()
                            ->title('SAP import failed')
                            ->body($e->getMessage())
                            ->send();

                        return;
                    }

                    if ($result->isEmpty()) {
                        Notification::make()
                            ->warning()
                            ->title('Nothing imported')
                            ->body('SAP import is disabled or returned no rows.')
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->success()
                        ->title('SAP import finished')
                        ->body("{$result->created} created, {$result->updated} updated.")
                        ->send();
                }),
            CreateAction::make(),
        ];
    }
}
