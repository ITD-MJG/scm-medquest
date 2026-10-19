<?php

namespace App\Filament\Resources\IncomingReceiptGrifols\Schemas;

use App\Enums\Disposition;
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
use Filament\Schemas\Components\Utilities\Get;
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
                        Select::make('registration_label_attached')
                            ->label('Registration Label Attached to Product')
                            ->boolean(),
                        TextInput::make('duration_of_transportation_hours')
                            ->label('Duration of Transportation (hours)')
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('remark'),
                        Select::make('disposition')
                            ->label('Disposition')
                            ->options(Disposition::class)
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
                                $set('part_no', $product?->part_no);
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
                            ->required()
                            ->live()
                            ->helperText(fn (Get $get): ?string => self::quantityHint($get)),
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
                        Select::make('labeled_by')
                            ->label('Labeled by')
                            ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id'))
                            ->searchable(),
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
                            ->numeric()
                            ->live(),
                        TextInput::make('unit_per_case')
                            ->label('Unit per Case')
                            ->integer()
                            ->minValue(1)
                            ->live(),
                        Toggle::make('labeled_correctly')
                            ->label('Labeled Correctly'),
                        Select::make('isn_or_packing_list')
                            ->label('ISN or Packing List')
                            ->boolean(),
                        TextInput::make('pallet_id')
                            ->label('Pallet/ID'),
                        Textarea::make('packaging_observation')
                            ->label('Packaging Observation')
                            ->columnSpanFull(),
                        TextInput::make('physical_movement_by')
                            ->label('Physical Movement by'),
                    ])
                    ->columns(3),
            ])
            ->columns(1);
    }

    /**
     * The paper's Quantity is No. of Cases x Unit per Case. Real sheets carry
     * partial cases and mixed units, so a mismatch is surfaced as a hint and
     * never blocks the save of a controlled document.
     */
    protected static function quantityHint(Get $get): ?string
    {
        $cases = $get('no_of_cases');
        $unitPerCase = $get('unit_per_case');

        if (blank($cases) || blank($unitPerCase)) {
            return null;
        }

        $expected = (float) $cases * (float) $unitPerCase;
        $quantity = $get('quantity');

        if (blank($quantity)) {
            return 'Expected '.self::trimNumber($expected).' from No. of Cases x Unit per Case.';
        }

        // The column is decimal:2, so compare at that precision instead of on raw
        // floats, which disagree on products like 7 x 1.1 and print "7.7 is not 7.7".
        return round($expected, 2) === round((float) $quantity, 2)
            ? 'Matches No. of Cases x Unit per Case ('.self::trimNumber($expected).').'
            : 'No. of Cases x Unit per Case = '.self::trimNumber($expected)
                .', but Quantity is '.self::trimNumber((float) $quantity).'. Check the paper form.';
    }

    protected static function trimNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
