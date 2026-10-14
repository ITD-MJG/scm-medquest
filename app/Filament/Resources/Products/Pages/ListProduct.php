<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Services\Sap\ProductImportService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Throwable;

class ListProduct extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pull_from_sap')
                ->label('Pull from SAP')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->modalDescription('Import products and principals from the active SAP HANA connection. Existing manual products are kept unless forced.')
                ->action(function (): void {
                    try {
                        $result = app(ProductImportService::class)->importProducts();
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
                        ->body("{$result->created} created, {$result->updated} updated, {$result->skipped} skipped.")
                        ->send();
                }),
            CreateAction::make(),
        ];
    }
}
