<?php

namespace App\Filament\Resources\IncomingReceiptGrifols\Schemas;

use App\Enums\IncomingType;
use App\Enums\ProductStatus;
use App\Enums\StorageRequirement;
use App\Models\Product;
use App\Models\Room;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class GrifolsReceiptForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Receipt Identity')
                    ->schema([
                        TextInput::make('receipt_no')
                            ->label('Receipt No.')
                            ->required()
                            ->unique(table: 'incoming_receipts', ignoreRecord: true),
                        Select::make('location_id')
                            ->label('Location')
                            ->relationship('location', 'location_name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        DatePicker::make('receipt_date')
                            ->label('Receipt Date')
                            ->required(),
                        TimePicker::make('receipt_time')
                            ->label('Receipt Time'),
                        Select::make('incoming_type')
                            ->label('Incoming Type')
                            ->options(IncomingType::class)
                            ->required(),
                        TextInput::make('purchase_order_no')
                            ->label('Purchase Order No.'),
                    ])
                    ->columns(2),

                Section::make('Parties')
                    ->schema([
                        Select::make('supplier_id')
                            ->label('Supplier')
                            ->relationship('supplier', 'name')
                            ->searchable()
                            ->preload(),
                        Select::make('carrier_id')
                            ->label('Carrier')
                            ->relationship('carrier', 'name')
                            ->searchable()
                            ->preload(),
                        TextInput::make('shipper_address')
                            ->label('Shipper Address'),
                    ])
                    ->columns(2),

                Section::make('Inspection')
                    ->schema([
                        Select::make('product_status')
                            ->label('Product Status')
                            ->options(ProductStatus::class)
                            ->required(),
                        Toggle::make('measured_by_device')
                            ->label('Measured by Device'),
                        TextInput::make('product_temp_result')
                            ->label('Product Temp. Result')
                            ->numeric(),
                        TextInput::make('product_temp_record_ref')
                            ->label('Product Temp. Record Ref.'),
                        Select::make('storage_requirement')
                            ->label('Storage Requirement')
                            ->options(StorageRequirement::class),
                        TextInput::make('remark'),
                        Select::make('disposition')
                            ->label('Disposition')
                            ->options([
                                'Released' => 'Released',
                                'Quarantine' => 'Quarantine',
                            ])
                            ->nullable()
                            ->default(null)
                            ->visible(fn () => auth()->user()->hasAnyRole(['Supply Chain Manager', 'Super Admin'])),
                    ])
                    ->columns(2),

                Section::make('Unpacking')
                    ->schema([
                        TimePicker::make('unpacking_start_at')
                            ->label('Unpacking Start'),
                        TimePicker::make('unpacking_finish_at')
                            ->label('Unpacking Finish'),
                    ])
                    ->columns(2),

                Repeater::make('lines')
                    ->label('Line Items')
                    ->reorderable()
                    ->minItems(1)
                    ->addActionLabel('Add line')
                    ->schema([
                        Select::make('product_id')
                            ->label('Product')
                            ->options(fn () => Product::query()->orderBy('internal_code')->pluck('internal_code', 'id'))
                            ->searchable()
                            ->required()
                            ->afterStateUpdated(function ($state, $set) {
                                if (! $state) {
                                    return;
                                }
                                $product = Product::find($state);
                                $set('internal_code', $product?->internal_code);
                                $set('product_description', $product?->description);
                                $set('uom', $product?->uom);
                            }),
                        TextInput::make('internal_code')
                            ->label('Internal Code'),
                        TextInput::make('product_description')
                            ->label('Product Description'),
                        TextInput::make('lot_no')
                            ->label('Lot No.'),
                        DatePicker::make('expiry_date')
                            ->label('Expiry Date'),
                        TextInput::make('quantity')
                            ->label('Quantity')
                            ->numeric()
                            ->required(),
                        TextInput::make('uom')
                            ->label('UoM')
                            ->required(),
                        TextInput::make('physical_packing_condition')
                            ->label('Physical Packing Condition'),
                        TextInput::make('registration_label_no')
                            ->label('Registration Label No.'),
                        Select::make('registration_label_qa_by')
                            ->label('Registration Label QA By')
                            ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id'))
                            ->searchable(),
                        TextInput::make('function_test_result')
                            ->label('Function Test Result'),
                        TextInput::make('function_test_by')
                            ->label('Function Test By'),
                        Select::make('room_id')
                            ->label('Room')
                            ->options(fn () => Room::query()->orderBy('room_name')->pluck('room_name', 'id'))
                            ->searchable(),
                        TextInput::make('part_no')
                            ->label('Part No.'),
                        TextInput::make('serial_no')
                            ->label('Serial No.'),
                        TextInput::make('no_of_cases')
                            ->label('No. of Cases')
                            ->numeric(),
                        Textarea::make('packaging_observation')
                            ->label('Packaging Observation')
                            ->columnSpanFull(),
                    ])
                    ->columns(3),
            ])
            ->columns(1);
    }
}
