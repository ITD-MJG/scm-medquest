<?php

namespace App\Filament\Resources\SapConnections;

use App\Models\SapConnection;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class SapConnectionResource extends Resource
{
    protected static ?string $model = SapConnection::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-server-stack';

    protected static string|\UnitEnum|null $navigationGroup = 'Admin Settings';

    protected static ?string $navigationLabel = 'SAP Connections';

    protected static ?int $navigationSort = 4;

    public static function canViewAny(): bool
    {
        return Auth::user()->hasRole(['Super Admin', 'Admin']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Connection Details')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        Select::make('driver')
                            ->options([
                                'mysql' => 'MySQL / MariaDB',
                                'sqlsrv' => 'SQL Server',
                                'hana' => 'SAP HANA',
                            ])
                            ->default('mysql')
                            ->required()
                            ->live(),

                        TextInput::make('host')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('port')
                            ->numeric()
                            ->default(fn (callable $get) => match ($get('driver')) {
                                'sqlsrv' => 1433,
                                'hana' => 30015,
                                default => 3306,
                            })
                            ->required(),

                        TextInput::make('database')
                            ->nullable()
                            ->maxLength(255),

                        TextInput::make('username')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('password')
                            ->password()
                            ->revealable()
                            ->dehydrated(fn ($state) => filled($state))
                            ->maxLength(255),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->inline(false)
                            ->default(false),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('driver')
                    ->badge()
                    ->sortable(),

                TextColumn::make('host')
                    ->searchable(),

                TextColumn::make('database')
                    ->searchable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                TextColumn::make('last_test_status')
                    ->label('Last Test')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'success' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state) => $state ?? 'never tested'),

                TextColumn::make('last_tested_at')
                    ->label('Tested At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('testConnection')
                    ->label('Test')
                    ->icon('heroicon-o-signal')
                    ->color('info')
                    ->requiresConfirmation(false)
                    ->action(function (SapConnection $record) {
                        try {
                            app(SapConnectionTester::class)->test($record);

                            Notification::make()
                                ->title('Connection successful')
                                ->body($record->last_test_message)
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Connection failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSapConnections::route('/'),
            'create' => Pages\CreateSapConnection::route('/create'),
            'view' => Pages\ViewSapConnection::route('/{record}'),
            'edit' => Pages\EditSapConnection::route('/{record}/edit'),
        ];
    }
}
