<?php

namespace App\Filament\Resources\SerialNumbers;

use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\SerialNumbers\Pages\ListSerialNumbers;
use App\Filament\Resources\SerialNumbers\Pages\CreateSerialNumber;
use App\Filament\Resources\SerialNumbers\Pages\EditSerialNumber;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\SerialNumber;
use Filament\Resources\Resource;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use App\Filament\Resources\SerialNumberResource\Pages;

class SerialNumberResource extends Resource
{
    protected static ?string $model = SerialNumber::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-hashtag';

    protected static string | \UnitEnum | null $navigationGroup = 'Location Management';
    protected static ?int $navigationSort = 4;
    public static function canViewAny(): bool
    {
        return auth()->user()->hasRole(['Super Admin', 'Admin']);
    }
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('room_id')
                    ->relationship('room', 'room_name')
                    ->label('Room')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('serial_number')
                    ->label('Serial Number')
                    ->required()
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->header(view('filament.tables.top-bottom-pagination-tables', [
                'table' => $table,
            ]))
            ->modifyQueryUsing(fn ($query) => $query->orderByDesc('created_at'))
            ->columns([
                TextColumn::make('room.room_name')
                    ->label('Room')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('serial_number')
                    ->label('Serial Number')
                    ->sortable()
                    ->searchable(),
                
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
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
            'index' => ListSerialNumbers::route('/'),
            'create' => CreateSerialNumber::route('/create'),
            'edit' => EditSerialNumber::route('/{record}/edit'),
        ];
    }
}
