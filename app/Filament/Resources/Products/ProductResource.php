<?php

namespace App\Filament\Resources\Products;

use App\Enums\ProductSource;
use App\Enums\StorageRequirement;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProduct;
use App\Models\Product;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Master Data';

    public static function canViewAny(): bool
    {
        return auth()->user()->hasRole(['Super Admin', 'Admin']);
    }

    public static function canCreate(): bool
    {
        return auth()->user()->hasRole(['Super Admin', 'Admin']);
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()->hasRole(['Super Admin', 'Admin']);
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()->hasRole(['Super Admin', 'Admin']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('internal_code')
                    ->label('Internal Code')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->disabled(fn (string $operation) => $operation === 'edit')
                    ->dehydrated(fn (string $operation) => $operation !== 'edit'),
                Select::make('principal_id')
                    ->label('Principal')
                    ->relationship('principal', 'name')
                    ->searchable()
                    ->preload(),
                Textarea::make('description')
                    ->label('Description')
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('part_no')
                    ->label('Part No'),
                TextInput::make('uom')
                    ->label('UoM')
                    ->required(),
                Select::make('storage_requirement')
                    ->label('Storage Requirement')
                    ->options(StorageRequirement::class)
                    ->native(false),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
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
                TextColumn::make('internal_code')
                    ->label('Internal Code')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('description')
                    ->label('Description')
                    ->sortable()
                    ->searchable()
                    ->limit(50),
                TextColumn::make('principal.name')
                    ->label('Principal')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('uom')
                    ->label('UoM')
                    ->sortable(),
                TextColumn::make('source')
                    ->label('Source')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state->label())
                    ->color(fn ($state) => $state === ProductSource::SAP ? 'success' : 'gray'),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('source')
                    ->label('Source')
                    ->options(collect(ProductSource::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
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
            'index' => ListProduct::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }
}
