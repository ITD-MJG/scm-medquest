<?php

namespace App\Filament\Resources\IncomingReceipts\Schemas;

use App\Enums\IncomingType;
use App\Enums\ProductStatus;
use App\Enums\ReceiptCategory;
use App\Enums\StorageRequirement;
use App\Models\Department;
use App\Models\Product;
use App\Models\Room;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class IncomingReceiptForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Receipt Identity & Parties')
                    ->schema([
                        TextInput::make('receipt_no')
                            ->label('Receipt No.')
                            ->hidden()
                            ->dehydrated(false),
                        DatePicker::make('receipt_date')
                            ->label('Receipt Date')
                            ->required(),
                        TimePicker::make('receipt_time')
                            ->label('Receipt Time')
                            ->hoursStep(1)
                            ->minutesStep(1)
                            ->seconds(false)
                            ->format('H:i'),
                        Select::make('incoming_type')
                            ->label('Incoming Type')
                            ->options(fn () => collect(IncomingType::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()]))
                            ->native(false)
                            ->required(),
                        Radio::make('category')
                            ->label('Category')
                            ->options(fn () => collect(ReceiptCategory::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()]))
                            ->columns(2),
                        Select::make('department_id')
                            ->label('Department')
                            ->options(fn () => Department::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->preload(),
                        TextInput::make('purchase_order_no')
                            ->label('Purchase Order No.'),
                        Select::make('location_id')
                            ->label('Location')
                            ->relationship('location', 'location_name')
                            ->searchable()
                            ->preload()
                            ->required(),
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
                    ->columns(5),

                Grid::make(2)
                    ->schema([
                        Section::make('Inspection')
                            ->schema([
                                Radio::make('product_status')
                                    ->label('Product Status')
                                    ->options(ProductStatus::class)
                                    ->columns(2)
                                    ->required(),
                                Toggle::make('measured_by_device')
                                    ->label('Measured by Device')
                                    ->live(),
                                TextInput::make('product_temp_result')
                                    ->label('Product Temp. Result')
                                    ->numeric()
                                    ->visible(fn (Get $get) => (bool) $get('measured_by_device')),
                                TextInput::make('product_temp_record_ref')
                                    ->label('Product Temp. Record Ref.')
                                    ->visible(fn (Get $get) => (bool) $get('measured_by_device')),
                                Radio::make('storage_requirement')
                                    ->label('Storage Requirement')
                                    ->options(fn () => collect(StorageRequirement::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                                    ->columns(2),
                                TextInput::make('remark'),
                            ])
                            ->columns(2),

                        Section::make('Unpacking')
                            ->schema([
                                TimePicker::make('unpacking_start_at')
                                    ->label('Unpacking Start')
                                    ->hoursStep(1)
                                    ->minutesStep(1)
                                    ->seconds(false)
                                    ->format('H:i'),
                                TimePicker::make('unpacking_finish_at')
                                    ->label('Unpacking Finish')
                                    ->hoursStep(1)
                                    ->minutesStep(1)
                                    ->seconds(false)
                                    ->format('H:i'),
                            ])
                            ->columns(2),
                    ]),

                Repeater::make('lines')
                    ->label('Line Items')
                    ->reorderable()
                    ->minItems(1)
                    ->addActionLabel('Add line')
                    ->schema([
                        Select::make('product_id')
                            ->label('Product')
                            ->options(fn () => Product::query()->orderBy('description')->pluck('description', 'id'))
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
                            ->label('Internal Code')
                            ->readOnly(),
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
                            ->required()
                            ->readOnly(),
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
                    ])
                    ->columns(3),
            ])
            ->columns(1);
    }
}
