<?php

namespace App\Filament\Resources\IncomingReceipts;

use App\Filament\Resources\IncomingReceipts\Pages\CreateIncomingReceipt;
use App\Filament\Resources\IncomingReceipts\Pages\EditIncomingReceipt;
use App\Filament\Resources\IncomingReceipts\Pages\ListIncomingReceipts;
use App\Filament\Resources\IncomingReceipts\Pages\ViewIncomingReceipt;
use App\Filament\Resources\IncomingReceipts\Schemas\IncomingReceiptForm;
use App\Filament\Resources\IncomingReceipts\Tables\IncomingReceiptsTable;
use App\Models\IncomingReceipt;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class IncomingReceiptResource extends Resource
{
    protected static ?string $model = IncomingReceipt::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Incoming';

    public static function canViewAny(): bool
    {
        return auth()->user()->hasRole(['Super Admin', 'Admin', 'Supply Chain Manager', 'Supply Chain Officer']);
    }

    public static function form(Schema $schema): Schema
    {
        return IncomingReceiptForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IncomingReceiptsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIncomingReceipts::route('/'),
            'create' => CreateIncomingReceipt::route('/create'),
            'view' => ViewIncomingReceipt::route('/{record}'),
            'edit' => EditIncomingReceipt::route('/{record}/edit'),
        ];
    }
}
