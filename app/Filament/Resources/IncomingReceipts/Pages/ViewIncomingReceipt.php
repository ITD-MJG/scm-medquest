<?php

namespace App\Filament\Resources\IncomingReceipts\Pages;

use App\Exceptions\SignOffException;
use App\Filament\Resources\IncomingReceipts\IncomingReceiptResource;
use App\Services\SignOffService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewIncomingReceipt extends ViewRecord
{
    protected static string $resource = IncomingReceiptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(fn (): bool => $this->getRecord()->signoffs()->count() === 0
                    || auth()->user()->hasRole('Super Admin')),
            Action::make('preview_pdf')
                ->label('Preview PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->url(fn (): string => route('incoming-receipts.print', ['record' => $this->getRecord()]))
                ->openUrlInNewTab(),
            ...$this->getSignOffActions(),
        ];
    }

    protected function getSignOffActions(): array
    {
        $service = app(SignOffService::class);

        return collect(config('documents.signoff_steps.incoming_receipt', []))
            ->map(fn (array $config, string $step): Action => Action::make("sign_{$step}")
                ->label($config['label'])
                ->requiresConfirmation()
                ->visible(fn (): bool => $service->canSign($this->getRecord(), $step, auth()->user()))
                ->action(function () use ($service, $step): void {
                    try {
                        $service->record($this->getRecord(), $step);
                    } catch (SignOffException $e) {
                        Notification::make()
                            ->danger()
                            ->title($e->getMessage())
                            ->send();
                    }
                }))
            ->all();
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Receipt Identity')
                    ->schema([
                        TextEntry::make('receipt_no')->label('Receipt No.'),
                        TextEntry::make('location.location_name')->label('Location'),
                        TextEntry::make('receipt_date')->formatStateUsing(fn ($state) => strtoupper(\Carbon\Carbon::parse($state)->format('d M Y')))->label('Receipt Date'),
                        TextEntry::make('receipt_time')->label('Receipt Time'),
                        TextEntry::make('incoming_type')->badge()->label('Incoming Type')->formatStateUsing(fn ($state) => $state->label()),
                        TextEntry::make('purchase_order_no')->label('Purchase Order No.'),
                        TextEntry::make('supplier.name')->label('Supplier'),
                        TextEntry::make('carrier.name')->label('Carrier'),
                        TextEntry::make('shipper_address')->label('Shipper Address'),
                    ])
                    ->columns(2),

                Section::make('Inspection')
                    ->schema([
                        TextEntry::make('product_status')->badge()->label('Product Status')->formatStateUsing(fn ($state) => $state->label()),
                        TextEntry::make('measured_by_device')
                            ->label('Measured by Device')
                            ->formatStateUsing(fn ($state) => $state ? 'Yes' : 'No'),
                        TextEntry::make('product_temp_result')->label('Product Temp. Result'),
                        TextEntry::make('product_temp_record_ref')->label('Product Temp. Record Ref.'),
                        TextEntry::make('storage_requirement')->label('Storage Requirement')->formatStateUsing(fn ($state) => $state->label()),
                        TextEntry::make('remark')->label('Remark'),
                        TextEntry::make('unpacking_start_at')->formatStateUsing(fn ($state) => strtoupper(\Carbon\Carbon::parse($state)->format('d M Y H:i')))->label('Unpacking Start'),
                        TextEntry::make('unpacking_finish_at')->formatStateUsing(fn ($state) => strtoupper(\Carbon\Carbon::parse($state)->format('d M Y H:i')))->label('Unpacking Finish'),
                    ])
                    ->columns(2),

                Section::make('Line Items')
                    ->schema([
                        RepeatableEntry::make('lines')
                            ->label('')
                            ->schema([
                                TextEntry::make('product.internal_code')->label('Internal Code'),
                                TextEntry::make('product.description')->label('Description'),
                                TextEntry::make('lot_no')->label('Lot No.'),
                                TextEntry::make('expiry_date')->formatStateUsing(fn ($state) => strtoupper(\Carbon\Carbon::parse($state)->format('d M Y')))->label('Expiry Date'),
                                TextEntry::make('quantity')->label('Quantity'),
                                TextEntry::make('uom')->label('UoM'),
                                TextEntry::make('physical_packing_condition')->label('Packing Condition'),
                            ])
                            ->columns(3),
                    ]),

                Section::make('Sign-offs')
                    ->schema([
                        RepeatableEntry::make('signoffs')
                            ->label('')
                            ->schema([
                                TextEntry::make('step')->label('Step'),
                                TextEntry::make('signer_name')->label('Signed by'),
                                TextEntry::make('signed_at')->formatStateUsing(fn ($state) => strtoupper(\Carbon\Carbon::parse($state)->format('d M Y H:i')))->label('Signed at'),
                            ])
                            ->columns(3)
                            ->visible(fn ($record): bool => $record->signoffs()->exists()),
                    ]),
            ]);
    }
}
