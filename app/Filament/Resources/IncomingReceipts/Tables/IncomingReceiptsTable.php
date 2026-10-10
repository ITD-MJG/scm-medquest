<?php

namespace App\Filament\Resources\IncomingReceipts\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class IncomingReceiptsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->header(view('filament.tables.top-bottom-pagination-tables', [
                'table' => $table,
            ]))
            ->modifyQueryUsing(fn ($query) => $query->orderByDesc('created_at'))
            ->columns([
                TextColumn::make('receipt_no')
                    ->label('Receipt No.')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('location.location_name')
                    ->label('Location')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('receipt_date')
                    ->label('Receipt Date')
                    ->date()
                    ->sortable(),
                TextColumn::make('incoming_type')
                    ->label('Incoming Type')
                    ->badge(),
                TextColumn::make('product_status')
                    ->label('Product Status')
                    ->badge(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
