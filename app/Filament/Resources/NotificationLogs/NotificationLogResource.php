<?php

namespace App\Filament\Resources\NotificationLogs;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Actions\ViewAction;
use App\Filament\Resources\NotificationLogs\Pages\ListNotificationLogs;
use App\Filament\Resources\NotificationLogs\Pages\ViewNotificationLog;
use Filament\Tables\Table;
use App\Models\NotificationLog;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\NotificationLogResource\Pages;

class NotificationLogResource extends Resource
{
    protected static ?string $model = NotificationLog::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-envelope';
    protected static string | \UnitEnum | null $navigationGroup = 'Admin Settings';
    protected static ?int $navigationSort = 6;
    public static function canViewAny(): bool
    {
        return Auth::user()->hasRole(['Super Admin', 'Admin']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Notification Details')
                    ->schema([
                        TextInput::make('notification_type')
                            ->label('Notification Type')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('mailable_class')
                            ->label('Mailable Class')
                            ->maxLength(255),

                        TextInput::make('recipient_email')
                            ->label('Recipient Email')
                            ->email()
                            ->maxLength(255),

                        Toggle::make('was_successful')
                            ->label('Was Successful')
                            ->inline(false),

                        Textarea::make('error_message')
                            ->label('Error Message')
                            ->rows(3)
                            ->maxLength(65535),

                        TextInput::make('sent_at')
                            ->label('Sent At'),

                        Textarea::make('data')
                            ->label('Data Payload')
                            ->rows(5),

                        TextInput::make('notifiable_type')
                            ->label('Notifiable Type')
                            ->maxLength(255),

                        TextInput::make('notifiable_id')
                            ->label('Notifiable ID')
                            ->numeric(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn($query) => $query->orderByDesc('created_at'))
            ->columns([

                TextColumn::make('notification_type')
                    ->label('Notification Type')
                    ->sortable()
                    ->searchable()
                    ->formatStateUsing(fn($record) => ucwords(str_replace('_', ' ', $record->notification_type))),

                TextColumn::make('mailable_class')
                    ->label('Mailable Class')
                    ->searchable(),

                TextColumn::make('recipient_email')
                    ->label('Recipient Email')
                    ->searchable()
                    ->limit(40),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'sent' => 'success',
                        'failed' => 'danger',
                        'pending' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('sent_at')
                    ->label('Sent At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('notification_type')
                    ->options([
                        'temperature_deviation' => 'Temperature Deviation',
                        'high_priority_deviation' => 'High Priority Deviation',
                        'inventory_alert' => 'Inventory Alert',
                    ]),

                SelectFilter::make('status')
                    ->options([
                        'sent' => 'Sent',
                        'failed' => 'Failed',
                        'pending' => 'Pending',
                    ]),

                SelectFilter::make('was_successful')
                    ->label('Success Status')
                    ->options([
                        '1' => 'Successful',
                        '0' => 'Failed',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                // Bulk actions can be added here if needed
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNotificationLogs::route('/'),
            'view' => ViewNotificationLog::route('/{record}'),
        ];
    }
}