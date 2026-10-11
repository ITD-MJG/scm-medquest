<?php

namespace App\Filament\Resources\IncomingReceiptGrifols;

use App\Filament\Resources\IncomingReceiptGrifols\Pages\CreateGrifolsReceipt;
use App\Filament\Resources\IncomingReceiptGrifols\Pages\EditGrifolsReceipt;
use App\Filament\Resources\IncomingReceiptGrifols\Pages\ListGrifolsReceipts;
use App\Filament\Resources\IncomingReceiptGrifols\Pages\ViewGrifolsReceipt;
use App\Filament\Resources\IncomingReceiptGrifols\Schemas\GrifolsReceiptForm;
use App\Filament\Resources\IncomingReceiptGrifols\Tables\GrifolsReceiptsTable;
use App\Models\IncomingReceipt;
use App\Traits\HasLocationBasedAccess;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class GrifolsReceiptResource extends Resource
{
    use HasLocationBasedAccess;

    protected static ?string $model = IncomingReceipt::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Incoming';

    protected static ?string $navigationLabel = 'Incoming Receipts Grifols';

    protected static ?string $slug = 'incoming-receipts-grifols';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-beaker';

    protected static ?string $modelLabel = 'Incoming Receipt Grifols';

    public static function getEloquentQuery(): Builder
    {
        return static::applyLocationFilter(
            parent::getEloquentQuery()->where('variant', 'grifols')
        );
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->hasRole(['Super Admin', 'Admin', 'Supply Chain Manager', 'Supply Chain Officer', 'QA Manager', 'QA Supervisor', 'QA Staff']);
    }

    public static function form(Schema $schema): Schema
    {
        return GrifolsReceiptForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GrifolsReceiptsTable::configure($table);
    }

    public static function canEdit(Model $record): bool
    {
        return $record->signoffs()->count() === 0 || auth()->user()->hasRole('Super Admin');
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()->hasRole('Super Admin');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGrifolsReceipts::route('/'),
            'create' => CreateGrifolsReceipt::route('/create'),
            'view' => ViewGrifolsReceipt::route('/{record}'),
            'edit' => EditGrifolsReceipt::route('/{record}/edit'),
        ];
    }
}
