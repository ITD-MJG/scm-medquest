<?php

namespace App\Filament\Resources\SapConnections\Pages;

use App\Filament\Resources\SapConnections\SapConnectionResource;
use App\Models\SapConnection;
use App\Services\Sap\SapConnectionTester;
use Filament\Actions\Action;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewSapConnection extends ViewRecord
{
    protected static string $resource = SapConnectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('testConnection')
                ->label('Test Connection')
                ->icon('heroicon-o-signal')
                ->color('info')
                ->action(function (SapConnection $record) {
                    try {
                        $message = app(SapConnectionTester::class)->test($record);

                        $this->successNotification(
                            Notification::make()
                                ->title('Connection successful')
                                ->body($message)
                                ->success()
                        );
                    } catch (\Throwable $e) {
                        $this->failureNotification(
                            Notification::make()
                                ->title('Connection failed')
                                ->body($e->getMessage())
                                ->danger()
                        );
                    }

                    $this->refreshFormData(['last_test_status', 'last_tested_at', 'last_test_message']);
                }),
            Action::make('edit')
                ->url(fn () => SapConnectionResource::getUrl('edit', ['record' => $this->record])),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Connection Details')
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('driver')
                            ->badge(),
                        TextEntry::make('host'),
                        TextEntry::make('port'),
                        TextEntry::make('database'),
                        TextEntry::make('username'),
                        TextEntry::make('password')
                            ->formatStateUsing(fn () => '••••••••'),
                        IconEntry::make('is_active')
                            ->label('Active')
                            ->boolean(),
                    ])
                    ->columns(3),

                Section::make('Last Connection Test')
                    ->schema([
                        TextEntry::make('last_test_status')
                            ->badge()
                            ->color(fn (?string $state): string => match ($state) {
                                'success' => 'success',
                                'failed' => 'danger',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn (?string $state) => $state ?? 'never tested'),
                        TextEntry::make('last_tested_at')
                            ->dateTime(),
                        TextEntry::make('last_test_message')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }
}
