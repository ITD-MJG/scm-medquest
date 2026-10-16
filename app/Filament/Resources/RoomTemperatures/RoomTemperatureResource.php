<?php

namespace App\Filament\Resources\RoomTemperatures;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\RoomTemperatures\Pages\ListRoomTemperatures;
use App\Filament\Resources\RoomTemperatures\Pages\CreateRoomTemperature;
use App\Filament\Resources\RoomTemperatures\Pages\EditRoomTemperature;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\RoomTemperature;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\RoomTemperatureResource\Pages;
use App\Filament\Resources\RoomTemperatureResource\RelationManagers;

class RoomTemperatureResource extends Resource
{
    protected static ?string $model = RoomTemperature::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-fire';

    protected static string|\UnitEnum|null $navigationGroup = 'Master Data';
    protected static ?int $navigationSort = 8;
    public static function canViewAny(): bool
    {
        return auth()->user()->hasRole(['Super Admin', 'Admin']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Room Temperature')
                    ->columns(3)
                    ->schema([
                        Select::make('room_id')
                            ->relationship('room', 'room_name')
                            ->label('Room')
                            ->required(),
                        TextInput::make('temperature_start')
                            ->label('Temperature Start (°C)')
                            ->numeric()
                            ->required(),
                        TextInput::make('temperature_end')
                            ->label('Temperature End (°C)')
                            ->numeric()
                            ->required(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->header(view('filament.tables.top-bottom-pagination-tables', [
                'table' => $table,
            ]))
            ->columns([
                TextColumn::make('room.room_name')
                    ->label('Room')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('temperature_start')
                    ->label('Temperature Range')
                    ->sortable()
                    ->searchable()
                    ->getStateUsing(function($record) {
                        return $record->temperature_start . '°C to ' . $record->temperature_end . '°C';
                    }),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => ListRoomTemperatures::route('/'),
            'create' => CreateRoomTemperature::route('/create'),
            'edit' => EditRoomTemperature::route('/{record}/edit'),
        ];
    }
}
