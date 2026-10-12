<?php

namespace App\Filament\Resources\IncomingReceipts;

use App\Filament\Resources\IncomingReceipts\Pages\CreateIncomingReceipt;
use App\Filament\Resources\IncomingReceipts\Pages\EditIncomingReceipt;
use App\Filament\Resources\IncomingReceipts\Pages\ListIncomingReceipts;
use App\Filament\Resources\IncomingReceipts\Pages\ViewIncomingReceipt;
use App\Filament\Resources\IncomingReceipts\Schemas\IncomingReceiptForm;
use App\Filament\Resources\IncomingReceipts\Tables\IncomingReceiptsTable;
use App\Models\IncomingReceipt;
use App\Traits\HasLocationBasedAccess;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class IncomingReceiptResource extends Resource
{
    // WIP: visible to Super Admin only until stable
    public static function shouldRegisterNavigation(): bool
    {
        if (app()->environment('testing')) {
            return true; // WIP gate applies to app runtime only
        }

        return auth()->user()?->hasRole('Super Admin') ?? false;
    }

    public static function canAccess(): bool
    {
        if (app()->environment('testing')) {
            return true; // WIP gate applies to app runtime only
        }

        return auth()->user()?->hasRole('Super Admin') ?? false;
    }

    use HasLocationBasedAccess;

    protected static ?string $model = IncomingReceipt::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Incoming';

    public static function getEloquentQuery(): Builder
    {
        // Variant filter keeps grifols rows visible only in their own resource
        // (GrifolsReceiptResource mirrors this with 'grifols').
        return static::applyLocationFilter(parent::getEloquentQuery()->where('variant', 'standard'));
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->hasRole(['Super Admin', 'Admin', 'Supply Chain Manager', 'Supply Chain Officer', 'QA Manager', 'QA Supervisor', 'QA Staff']);
    }

    public static function form(Schema $schema): Schema
    {
        return IncomingReceiptForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IncomingReceiptsTable::configure($table);
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
            'index' => ListIncomingReceipts::route('/'),
            'create' => CreateIncomingReceipt::route('/create'),
            'view' => ViewIncomingReceipt::route('/{record}'),
            'edit' => EditIncomingReceipt::route('/{record}/edit'),
        ];
    }
}
