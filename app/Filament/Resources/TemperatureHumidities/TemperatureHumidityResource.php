<?php

namespace App\Filament\Resources\TemperatureHumidities;

use App\Filament\Resources\TemperatureHumidities\Pages\AcknowledgedTemperatureHumidity;
use App\Filament\Resources\TemperatureHumidities\Pages\CreateTemperatureHumidity;
use App\Filament\Resources\TemperatureHumidities\Pages\EditTemperatureHumidity;
use App\Filament\Resources\TemperatureHumidities\Pages\ListTemperatureHumidity;
use App\Filament\Resources\TemperatureHumidities\Pages\ReviewedTemperatureHumidity;
use App\Filament\Resources\TemperatureHumidities\Pages\ViewTemperatureHumidity;
use App\Models\Location;
use App\Models\Room;
use App\Models\RoomTemperature;
use App\Models\SerialNumber;
use App\Models\TemperatureHumidity;
use App\Traits\HasLocationBasedAccess;
use Carbon\Carbon;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Navigation\NavigationItem;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Spatie\LaravelPdf\Enums\Format;

class TemperatureHumidityResource extends Resource
{
    use HasLocationBasedAccess;

    protected static ?string $model = TemperatureHumidity::class;

    protected static ?int $navigationSort = 0;

    protected static ?string $navigationLabel = 'All';

    protected static string|\UnitEnum|null $navigationGroup = 'Temperature & Humidity';

    protected static bool $shouldRegisterNavigation = false;

    public static function getHeading(): string
    {
        if (auth()->user()->hasRole(['Supply Chain Officer', 'Security'])) {
            return '*** Untuk update data: cari nama ruangan lalu scroll ke kanan, setelah itu klik tombol edit. Jangan membuat data baru untuk menghindari duplikasi data! ***';
        }

        return '';
    }

    public static function getEloquentQuery(): Builder
    {
        return static::applyLocationFilter(parent::getEloquentQuery());
    }

    // public static function canCreate(): bool
    // {
    //     return !TemperatureHumidity::whereDate('created_at', Carbon::today())->exists();
    // }
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Date & Period')
                    ->columns(2)
                    ->schema([
                        DatePicker::make('date')
                            ->label('Date')
                            ->default(Carbon::now())
                            ->required(),
                        DatePicker::make('period')
                            ->label('Period')
                            ->native(false)
                            ->displayFormat('M Y')
                            ->default(Carbon::now())
                            ->required(),
                    ]),
                Section::make('Location & Storage Temperature Standards')
                    ->columns(4)
                    ->schema([
                        Select::make('location_id')
                            ->label('Location')
                            ->relationship(
                                'location',
                                'location_name',
                                modifyQueryUsing: function (Builder $query) {
                                    $accessibleLocationIds = static::getAccessibleLocationIds();
                                    if (! empty($accessibleLocationIds)) {
                                        $query->whereIn('id', $accessibleLocationIds);
                                    }
                                }
                            )
                            ->preload()
                            ->searchable()
                            ->reactive()
                            ->required()
                            ->default(function () {
                                $user = Auth::user();
                                // If user has specific location and doesn't have admin-level roles, default to that
                                if ($user->location_id && ! $user->hasRole(['Super Admin', 'Admin', 'Supply Chain Manager', 'QA Manager'])) {
                                    return $user->location_id;
                                }

                                return null;
                            }),
                        Select::make('room_id')
                            ->label('Room')
                            ->relationship('room', 'room_name')
                            ->options(function (callable $get) {
                                $locationId = Auth::user()->location_id;
                                if (! $locationId) {
                                    return [];
                                }

                                return Room::where('location_id', $locationId)
                                    ->pluck('room_name', 'id');
                            })
                            ->searchable()
                            ->reactive()
                            ->preload()
                            ->required()
                            ->disabled(fn (callable $get) => ! $get('location_id'))
                            ->afterStateUpdated(function ($state, callable $set) {
                                $set('serial_number_id', null);
                                $exists = TemperatureHumidity::where('room_id', $state)
                                    ->whereDate('date', Carbon::today())
                                    ->exists();

                                if ($exists) {
                                    Notification::make()
                                        ->title('⚠️ A record for this location already exists today.')
                                        ->danger()
                                        ->send();
                                }
                            }),
                        Select::make('serial_number_id')
                            ->label('Serial Number')
                            ->options(function (callable $get) {
                                $roomId = $get('room_id');

                                if (! $roomId) {
                                    return [];
                                }

                                return SerialNumber::where('room_id', $roomId)
                                    ->pluck('serial_number', 'id');
                            })
                            ->searchable()
                            ->required()
                            ->disabled(fn (callable $get) => ! $get('room_id'))
                            ->preload()
                            ->required(),
                        Select::make('room_temperature_id')
                            ->label('Room Temperature Standards')
                            ->relationship('roomTemperature', 'temperature_start')
                            ->options(function (callable $get) {
                                $roomId = $get('room_id');
                                if (! $roomId) {
                                    return [];
                                }

                                return RoomTemperature::where('room_id', $roomId)
                                    ->get()
                                    ->mapWithKeys(function ($item) {
                                        $label = "{$item->temperature_start}°C to {$item->temperature_end}°C";

                                        return [$item->id => $label];
                                    })
                                    ->toArray();
                            })
                            ->searchable()
                            ->reactive()
                            ->preload()
                            ->required()
                            ->disabled(fn (callable $get) => ! $get('room_id'))
                            ->afterStateUpdated(function ($state, callable $set) {
                                $roomTemperature = RoomTemperature::find($state);
                                if ($roomTemperature) {
                                    $formatted = "{$roomTemperature->temperature_start}°C to {$roomTemperature->temperature_end}°C";
                                    $set('observed_temperature', $formatted);
                                    $set('temperature_start', $roomTemperature->temperature_start);
                                    $set('temperature_end', $roomTemperature->temperature_end);
                                }
                            }),
                        Hidden::make('temperature_start'),
                        Hidden::make('temperature_end'),
                    ]),
                Section::make('Time')
                    ->columns(1)
                    ->schema([
                        Section::make('0200')
                            ->columns(3)
                            ->schema([
                                TimePicker::make('time_0200')
                                    ->label('Time')
                                    ->seconds(false)
                                    ->readOnly(fn () => ! auth()->user()->hasRole('Super Admin') && (
                                        Carbon::now('Asia/Jakarta')->format('H:i:s') < '02:00:00' ||
                                        Carbon::now('Asia/Jakarta')->format('H:i:s') >= '02:30:59'
                                    )
                                    ),
                                // ->required(Auth::user()->hasRole('Supply Chain Officer')),
                                TextInput::make('temp_0200')
                                    ->label('Temperature')
                                    ->numeric()
                                    ->inputMode('decimal')
                                    ->suffix('°C')
                                    ->maxValue(100)
                                    ->readOnly(fn () => ! auth()->user()->hasRole('Super Admin') && (
                                        Carbon::now('Asia/Jakarta')->format('H:i:s') < '02:00:00' ||
                                        Carbon::now('Asia/Jakarta')->format('H:i:s') >= '02:30:59'
                                    )
                                    ),
                                // ->required(Auth::user()->hasRole('Supply Chain Officer')),
                                TextInput::make('rh_0200')
                                    ->label('Humidity')
                                    ->suffix('%')
                                    ->numeric()
                                    ->maxValue(100)
                                    ->readOnly(fn () => ! auth()->user()->hasRole('Super Admin') && (
                                        Carbon::now('Asia/Jakarta')->format('H:i:s') < '02:00:00' ||
                                        Carbon::now('Asia/Jakarta')->format('H:i:s') >= '02:30:59'
                                    )
                                    ),
                            ]),

                        // ->disabled(fn (string $operation) =>
                        //         $operation === 'create' && (
                        //                 Carbon::now('Asia/Jakarta')->format('H:i:s') < '02:00:00' ||
                        //                 Carbon::now('Asia/Jakarta')->format('H:i:s') >= '02:30:59'
                        //         )
                        // ),
                        Section::make('0500')
                            ->columns(3)
                            ->schema([
                                TimePicker::make('time_0500')
                                    ->label('Time')
                                    ->seconds(false)
                                    ->readOnly(fn () => ! auth()->user()->hasRole('Super Admin') && (
                                        Carbon::now('Asia/Jakarta')->format('H:i:s') < '05:00:00' ||
                                        Carbon::now('Asia/Jakarta')->format('H:i:s') >= '05:30:59'
                                    )
                                    ),
                                // ->required(Auth::user()->hasRole('Supply Chain Officer')),
                                TextInput::make('temp_0500')
                                    ->label('Temperature')
                                    ->numeric()
                                    ->inputMode('decimal')
                                    ->suffix('°C')
                                    ->maxValue(100)
                                    ->readOnly(fn () => ! auth()->user()->hasRole('Super Admin') && (
                                        Carbon::now('Asia/Jakarta')->format('H:i:s') < '05:00:00' ||
                                        Carbon::now('Asia/Jakarta')->format('H:i:s') >= '05:30:59'
                                    )
                                    ),
                                // ->required(Auth::user()->hasRole('Supply Chain Officer')),
                                TextInput::make('rh_0500')
                                    ->label('Humidity')
                                    ->suffix('%')
                                    ->numeric()
                                    ->maxValue(100)
                                    ->readOnly(fn () => ! auth()->user()->hasRole('Super Admin') && (
                                        Carbon::now('Asia/Jakarta')->format('H:i:s') < '05:00:00' ||
                                        Carbon::now('Asia/Jakarta')->format('H:i:s') >= '05:30:59'
                                    )
                                    ),
                            ]),

                        // ->disabled(fn (string $operation) =>
                        //         $operation === 'create' && (
                        //                 Carbon::now('Asia/Jakarta')->format('H:i:s') < '05:00:00' ||
                        //                 Carbon::now('Asia/Jakarta')->format('H:i:s') >= '05:30:59'
                        //         )
                        // ),
                        Section::make('0800')
                            ->columns(3)
                            ->schema([
                                TimePicker::make('time_0800')
                                    ->label('Time')
                                    ->seconds(false)
                                    ->readOnly(fn () => ! auth()->user()->hasRole('Super Admin') && (
                                        Carbon::now('Asia/Jakarta')->format('H:i:s') < '08:00:00' ||
                                        Carbon::now('Asia/Jakarta')->format('H:i:s') >= '08:30:59'
                                    )
                                    ),
                                // ->required(Auth::user()->hasRole('Supply Chain Officer')),
                                TextInput::make('temp_0800')
                                    ->label('Temperature')
                                    ->numeric()
                                    ->inputMode('decimal')
                                    ->step(0.1)
                                    ->suffix('°C')
                                    ->maxValue(100)
                                    ->readOnly(fn () => ! auth()->user()->hasRole('Super Admin') && (
                                        Carbon::now('Asia/Jakarta')->format('H:i:s') < '08:00:00' ||
                                        Carbon::now('Asia/Jakarta')->format('H:i:s') >= '08:30:59'
                                    )
                                    ),
                                // ->required(Auth::user()->hasRole('Supply Chain Officer')),
                                TextInput::make('rh_0800')
                                    ->label('Humidity')
                                    ->suffix('%')
                                    ->numeric()
                                    ->maxValue(100)
                                    ->readOnly(fn () => ! auth()->user()->hasRole('Super Admin') && (
                                        Carbon::now('Asia/Jakarta')->format('H:i:s') < '08:00:00' ||
                                        Carbon::now('Asia/Jakarta')->format('H:i:s') >= '08:30:59'
                                    )
                                    ),
                            ]),

                        // ->disabled(fn (string $operation) =>
                        //         $operation === 'create' && (
                        //             Carbon::now('Asia/Jakarta')->format('H:i:s') < '08:00:00' ||
                        //             Carbon::now('Asia/Jakarta')->format('H:i:s') >= '08:30:59'
                        //         )
                        // ),
                        Section::make('1100')
                            ->columns(3)
                            ->schema([
                                TimePicker::make('time_1100')
                                    ->label('Time')
                                    ->seconds(false)
                                    ->readOnly(fn () => ! auth()->user()->hasRole('Super Admin') && (
                                        Carbon::now('Asia/Jakarta')->format('H:i:s') < '11:00:00' ||
                                        Carbon::now('Asia/Jakarta')->format('H:i:s') >= '11:30:59'
                                    )
                                    ),
                                // ->required(Auth::user()->hasRole('Supply Chain Officer')),
                                TextInput::make('temp_1100')
                                    ->label('Temperature')
                                    ->numeric()
                                    ->inputMode('decimal')
                                    ->suffix('°C')
                                    ->maxValue(100)
                                    ->readOnly(fn () => ! auth()->user()->hasRole('Super Admin') && (
                                        Carbon::now('Asia/Jakarta')->format('H:i:s') < '11:00:00' ||
                                        Carbon::now('Asia/Jakarta')->format('H:i:s') >= '11:30:59'
                                    )
                                    ),
                                // ->required(Auth::user()->hasRole('Supply Chain Officer')),
                                TextInput::make('rh_1100')
                                    ->label('Humidity')
                                    ->suffix('%')
                                    ->numeric()
                                    ->maxValue(100)
                                    ->readOnly(fn () => ! auth()->user()->hasRole('Super Admin') && (
                                        Carbon::now('Asia/Jakarta')->format('H:i:s') < '11:00:00' ||
                                        Carbon::now('Asia/Jakarta')->format('H:i:s') >= '11:30:59'
                                    )
                                    ),
                            ]),

                        // ->disabled(fn (string $operation) =>
                        //         $operation === 'create' && (
                        //                 Carbon::now('Asia/Jakarta')->format('H:i:s') < '11:00:00' ||
                        //                 Carbon::now('Asia/Jakarta')->format('H:i:s') >= '11:30:59'
                        //         )
                        // ),
                        Section::make('1400')
                            ->columns(3)
                            ->schema([
                                TimePicker::make('time_1400')
                                    ->label('Time')
                                    ->seconds(false)
                                    ->readOnly(fn () => ! auth()->user()->hasRole('Super Admin') && (
                                        Carbon::now('Asia/Jakarta')->format('H:i:s') < '14:00:00' ||
                                        Carbon::now('Asia/Jakarta')->format('H:i:s') >= '14:30:59'
                                    )
                                    ),
                                // ->required(Auth::user()->hasRole('Supply Chain Officer')),
                                TextInput::make('temp_1400')
                                    ->label('Temperature')
                                    ->numeric()
                                    ->inputMode('decimal')
                                    ->suffix('°C')
                                    ->maxValue(100)
                                    ->readOnly(fn () => ! auth()->user()->hasRole('Super Admin') && (
                                        Carbon::now('Asia/Jakarta')->format('H:i:s') < '14:00:00' ||
                                        Carbon::now('Asia/Jakarta')->format('H:i:s') >= '14:30:59'
                                    )
                                    ),
                                // ->required(Auth::user()->hasRole('Supply Chain Officer')),
                                TextInput::make('rh_1400')
                                    ->label('Humidity')
                                    ->suffix('%')
                                    ->numeric()
                                    ->maxValue(100)
                                    ->readOnly(fn () => ! auth()->user()->hasRole('Super Admin') && (
                                        Carbon::now('Asia/Jakarta')->format('H:i:s') < '14:00:00' ||
                                        Carbon::now('Asia/Jakarta')->format('H:i:s') >= '14:30:59'
                                    )
                                    ),
                            ]),

                        // ->disabled(fn (string $operation) =>
                        //         $operation === 'create' && (
                        //                 Carbon::now('Asia/Jakarta')->format('H:i:s') < '14:00:00' ||
                        //                 Carbon::now('Asia/Jakarta')->format('H:i:s') >= '14:30:59'
                        //         )
                        // ),
                        Section::make('1700')
                            ->columns(3)
                            ->schema([
                                TimePicker::make('time_1700')
                                    ->label('Time')
                                    ->seconds(false)
                                    ->readOnly(fn () => ! auth()->user()->hasRole('Super Admin') && (
                                        Carbon::now('Asia/Jakarta')->format('H:i:s') < '17:00:00' ||
                                        Carbon::now('Asia/Jakarta')->format('H:i:s') >= '17:30:59'
                                    )
                                    ),
                                // ->required(Auth::user()->hasRole('Supply Chain Officer')),
                                TextInput::make('temp_1700')
                                    ->label('Temperature')
                                    ->numeric()
                                    ->inputMode('decimal')
                                    ->suffix('°C')
                                    ->maxValue(100)
                                    ->readOnly(fn () => ! auth()->user()->hasRole('Super Admin') && (
                                        Carbon::now('Asia/Jakarta')->format('H:i:s') < '17:00:00' ||
                                        Carbon::now('Asia/Jakarta')->format('H:i:s') >= '17:30:59'
                                    )
                                    ),
                                // ->required(Auth::user()->hasRole('Supply Chain Officer')),
                                TextInput::make('rh_1700')
                                    ->label('Humidity')
                                    ->suffix('%')
                                    ->numeric()
                                    ->maxValue(100)
                                    ->readOnly(fn () => ! auth()->user()->hasRole('Super Admin') && (
                                        Carbon::now('Asia/Jakarta')->format('H:i:s') < '17:00:00' ||
                                        Carbon::now('Asia/Jakarta')->format('H:i:s') >= '17:30:59'
                                    )
                                    ),
                            ]),

                        // ->disabled(fn (string $operation) =>
                        //         $operation === 'create' && (
                        //                 Carbon::now('Asia/Jakarta')->format('H:i:s') < '17:00:00' ||
                        //                 Carbon::now('Asia/Jakarta')->format('H:i:s') >= '17:30:59'
                        //         )
                        // ),
                        Section::make('2000')
                            ->columns(3)
                            ->schema([
                                TimePicker::make('time_2000')
                                    ->label('Time')
                                    ->seconds(false)
                                    ->readOnly(fn () => ! auth()->user()->hasRole('Super Admin') && (
                                        Carbon::now('Asia/Jakarta')->format('H:i:s') < '20:00:00' ||
                                        Carbon::now('Asia/Jakarta')->format('H:i:s') >= '20:30:59'
                                    )
                                    ),
                                // ->required(Auth::user()->hasRole('Supply Chain Officer')),
                                TextInput::make('temp_2000')
                                    ->label('Temperature')
                                    ->numeric()
                                    ->inputMode('decimal')
                                    ->suffix('°C')
                                    ->maxValue(100)
                                    ->readOnly(fn () => ! auth()->user()->hasRole('Super Admin') && (
                                        Carbon::now('Asia/Jakarta')->format('H:i:s') < '20:00:00' ||
                                        Carbon::now('Asia/Jakarta')->format('H:i:s') >= '20:30:59'
                                    )
                                    ),
                                // ->required(Auth::user()->hasRole('Supply Chain Officer')),
                                TextInput::make('rh_2000')
                                    ->label('Humidity')
                                    ->suffix('%')
                                    ->numeric()
                                    ->maxValue(100)
                                    ->readOnly(fn () => ! auth()->user()->hasRole('Super Admin') && (
                                        Carbon::now('Asia/Jakarta')->format('H:i:s') < '20:00:00' ||
                                        Carbon::now('Asia/Jakarta')->format('H:i:s') >= '20:30:59'
                                    )
                                    ),
                            ]),

                        // ->disabled(fn (string $operation) =>
                        //         $operation === 'create' && (
                        //                 Carbon::now('Asia/Jakarta')->format('H:i:s') < '20:00:00' ||
                        //                 Carbon::now('Asia/Jakarta')->format('H:i:s') >= '20:30:59'
                        //         )
                        // ),
                        Section::make('2300')
                            ->columns(3)
                            ->schema([
                                TimePicker::make('time_2300')
                                    ->label('Time')
                                    ->seconds(false)
                                    ->readOnly(fn () => ! auth()->user()->hasRole('Super Admin') && (
                                        Carbon::now('Asia/Jakarta')->format('H:i:s') < '23:00:00' ||
                                        Carbon::now('Asia/Jakarta')->format('H:i:s') >= '23:30:59'
                                    )
                                    ),
                                // ->required(Auth::user()->hasRole('Supply Chain Officer')),
                                TextInput::make('temp_2300')
                                    ->label('Temperature')
                                    ->numeric()
                                    ->inputMode('decimal')
                                    ->suffix('°C')
                                    ->maxValue(100)
                                    ->readOnly(fn () => ! auth()->user()->hasRole('Super Admin') && (
                                        Carbon::now('Asia/Jakarta')->format('H:i:s') < '23:00:00' ||
                                        Carbon::now('Asia/Jakarta')->format('H:i:s') >= '23:30:59'
                                    )
                                    ),
                                // ->required(Auth::user()->hasRole('Supply Chain Officer')),
                                TextInput::make('rh_2300')
                                    ->label('Humidity')
                                    ->suffix('%')
                                    ->numeric()
                                    ->maxValue(100)
                                    ->readOnly(fn () => ! auth()->user()->hasRole('Super Admin') && (
                                        Carbon::now('Asia/Jakarta')->format('H:i:s') < '23:00:00' ||
                                        Carbon::now('Asia/Jakarta')->format('H:i:s') >= '23:30:59'
                                    )
                                    ),
                            ]),
                        // ->disabled(fn (string $operation) =>
                        //         $operation === 'create' && (
                        //             Carbon::now('Asia/Jakarta')->format('H:i:s') < '23:00:00' ||
                        //             Carbon::now('Asia/Jakarta')->format('H:i:s') >= '23:30:59'
                        //         )
                        // ),
                    ]),
            ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->header(view('filament.tables.top-bottom-pagination-tables', [
                'table' => $table,
                'heading' => static::getHeading(),
            ]))
            ->modifyQueryUsing(function ($query) {
                return $query->with(['location', 'room', 'serialNumber', 'temperatureDeviations', 'roomTemperature'])
                    ->orderByDesc('date')->orderByDesc('created_at');
            })
            ->columns([
                TextColumn::make('date')
                    ->label('Date')
                    ->formatStateUsing(fn ($record) => Carbon::parse($record->date)->format('d'))
                    ->searchable(),
                TextColumn::make('period')
                    ->label('Period')
                    ->formatStateUsing(fn ($record) => strtoupper(Carbon::parse($record->period)->format('M')).'<br>'.Carbon::parse($record->period)->format('Y'))
                    ->html()
                    ->searchable(),
                TextColumn::make('location.location_name')
                    ->label('Location')
                    ->getStateUsing(function ($record) {
                        return $record->location->location_name.'<br>'.
                            $record->room->room_name.'<br>'.
                            $record->serialNumber->serial_number;
                    })
                    ->html(),
                TextColumn::make('room.room_name')
                    ->label('Room')
                    ->getStateUsing(function ($record) {
                        return $record->room->room_name;
                    })
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('serialNumber.serial_number')
                    ->label('Serial Number')
                    ->getStateUsing(function ($record) {
                        return $record->serialNumber->serial_number;
                    })
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('0200_data')
                    ->label('02:00')
                    ->getStateUsing(function ($record) {
                        $temp0200 = $record->temp_0200 ?? '-';
                        $time0200 = $record->time_0200 ? Carbon::parse($record->time_0200)->format('H:i') : '-';
                        $rh0200 = $record->rh_0200 ?? '-';
                        $pic0200 = $record->formatPicSignature('pic_0200');

                        $color = '';
                        $linkStart = '';
                        $linkEnd = '';
                        $deviation = null;

                        // Check if there's a deviation record matching the specific time and temperature
                        if ($temp0200 !== '-' && $record->time_0200) {
                            $deviation = $record->temperatureDeviations
                                ->where('time', $record->time_0200)
                                ->where('temperature_deviation', $temp0200)
                                ->first();
                        }

                        // Apply red styling only if temperature is out of range OR if there's a matching deviation record
                        $isOutOfRange = $temp0200 !== '-' &&
                            ($temp0200 < $record->roomTemperature->temperature_start || $temp0200 > $record->roomTemperature->temperature_end);

                        if ($isOutOfRange || $deviation) {
                            $color = 'color: red; font-weight: bold;';
                        }

                        if ($deviation) {
                            $deviationUrl = route('filament.dashboard.resources.temperature-deviations.view', ['record' => $deviation->id]);
                            $linkStart = "<a href='$deviationUrl' style='text-decoration: none; color: inherit;'>";
                            $linkEnd = '</a>';
                        }

                        if ($deviation) {
                            $deviationUrl = route('filament.dashboard.resources.temperature-deviations.view', ['record' => $deviation->id]);
                            $button = "<br><a href='$deviationUrl' class='inline-flex items-center px-2 py-1 bg-danger text-xs rounded hover:bg-red-600 mt-1' style='display:inline-block; margin-top:4px;'>View Deviation</a>";

                            return "$linkStart <div style='$color'>Time: $time0200 <br> Temp: $temp0200 °C <br> Humidity: $rh0200% <br> PIC: $pic0200 <br> $button</div> $linkEnd";
                        } else {
                            return "$linkStart <div style='$color'>Time: $time0200 <br> Temp: $temp0200 °C <br> Humidity: $rh0200% <br> PIC: $pic0200</div> $linkEnd";
                        }
                    })->html(),
                TextColumn::make('0500_data')
                    ->label('05:00')
                    ->getStateUsing(function ($record) {
                        $temp0500 = $record->temp_0500 ?? '-';
                        $time0500 = $record->time_0500 ? Carbon::parse($record->time_0500)->format('H:i') : '-';
                        $rh0500 = $record->rh_0500 ?? '-';
                        $pic0500 = $record->formatPicSignature('pic_0500');

                        $color = '';
                        $linkStart = '';
                        $linkEnd = '';
                        $deviation = null;

                        // Check if there's a deviation record matching the specific time and temperature
                        if ($temp0500 !== '-' && $record->time_0500) {
                            $deviation = $record->temperatureDeviations
                                ->where('time', $record->time_0500)
                                ->where('temperature_deviation', $temp0500)
                                ->first();
                        }

                        // Apply red styling only if temperature is out of range OR if there's a matching deviation record
                        $isOutOfRange = $temp0500 !== '-' &&
                            ($temp0500 < $record->roomTemperature->temperature_start || $temp0500 > $record->roomTemperature->temperature_end);

                        if ($isOutOfRange || $deviation) {
                            $color = 'color: red; font-weight: bold;';
                        }

                        if ($deviation) {
                            $deviationUrl = route('filament.dashboard.resources.temperature-deviations.view', ['record' => $deviation->id]);
                            $linkStart = "<a href='$deviationUrl' style='text-decoration: none; color: inherit;'>";
                            $linkEnd = '</a>';
                        }

                        if ($deviation) {
                            $deviationUrl = route('filament.dashboard.resources.temperature-deviations.view', ['record' => $deviation->id]);
                            $button = "<br><a href='$deviationUrl' class='inline-flex items-center px-2 py-1 bg-danger text-xs rounded hover:bg-red-600 mt-1' style='display:inline-block; margin-top:4px;'>View Deviation</a>";

                            return "$linkStart <div style='$color'>Time: $time0500 <br> Temp: $temp0500 °C <br> Humidity: $rh0500% <br> PIC: $pic0500 <br> $button</div> $linkEnd";
                        } else {
                            return "$linkStart <div style='$color'>Time: $time0500 <br> Temp: $temp0500 °C <br> Humidity: $rh0500% <br> PIC: $pic0500</div> $linkEnd";
                        }
                    })->html(),
                TextColumn::make('0800_data')
                    ->label('08:00')
                    ->getStateUsing(function ($record) {
                        $temp0800 = $record->temp_0800 ?? '-';
                        $time0800 = $record->time_0800 ? Carbon::parse($record->time_0800)->format('H:i') : '-';
                        $rh0800 = $record->rh_0800 ?? '-';
                        $pic0800 = $record->formatPicSignature('pic_0800');

                        $color = '';
                        $linkStart = '';
                        $linkEnd = '';
                        $deviation = null;

                        // Check if there's a deviation record matching the specific time and temperature
                        if ($temp0800 !== '-' && $record->time_0800) {
                            $deviation = $record->temperatureDeviations
                                ->where('time', $record->time_0800)
                                ->where('temperature_deviation', $temp0800)
                                ->first();
                        }

                        // Apply red styling only if temperature is out of range OR if there's a matching deviation record
                        $isOutOfRange = $temp0800 !== '-' &&
                            ($temp0800 < $record->roomTemperature->temperature_start || $temp0800 > $record->roomTemperature->temperature_end);

                        if ($isOutOfRange || $deviation) {
                            $color = 'color: red; font-weight: bold;';
                        }

                        if ($deviation) {
                            $deviationUrl = route('filament.dashboard.resources.temperature-deviations.view', ['record' => $deviation->id]);
                            $linkStart = "<a href='$deviationUrl' style='text-decoration: none; color: inherit;'>";
                            $linkEnd = '</a>';
                        }

                        if ($deviation) {
                            $deviationUrl = route('filament.dashboard.resources.temperature-deviations.view', ['record' => $deviation->id]);
                            $button = "<br><a href='$deviationUrl' class='inline-flex items-center px-2 py-1 bg-danger text-xs rounded hover:bg-red-600 mt-1' style='display:inline-block; margin-top:4px;'>View Deviation</a>";

                            return "$linkStart <div style='$color'>Time: $time0800 <br> Temp: $temp0800 °C <br> Humidity: $rh0800% <br> PIC: $pic0800 <br> $button</div> $linkEnd";
                        } else {
                            return "$linkStart <div style='$color'>Time: $time0800 <br> Temp: $temp0800 °C <br> Humidity: $rh0800% <br> PIC: $pic0800</div> $linkEnd";
                        }
                    })->html(),
                TextColumn::make('1100_data')
                    ->label('11:00')
                    ->getStateUsing(function ($record) {
                        $temp1100 = $record->temp_1100 ?? '-';
                        $time1100 = $record->time_1100 ? Carbon::parse($record->time_1100)->format('H:i') : '-';
                        $rh1100 = $record->rh_1100 ?? '-';
                        $pic1100 = $record->formatPicSignature('pic_1100');

                        $color = '';
                        $linkStart = '';
                        $linkEnd = '';
                        $deviation = null;

                        // Check if there's a deviation record matching the specific time and temperature
                        if ($temp1100 !== '-' && $record->time_1100) {
                            $deviation = $record->temperatureDeviations
                                ->where('time', $record->time_1100)
                                ->where('temperature_deviation', $temp1100)
                                ->first();
                        }

                        // Apply red styling only if temperature is out of range OR if there's a matching deviation record
                        $isOutOfRange = $temp1100 !== '-' &&
                            ($temp1100 < $record->roomTemperature->temperature_start || $temp1100 > $record->roomTemperature->temperature_end);

                        if ($isOutOfRange || $deviation) {
                            $color = 'color: red; font-weight: bold;';
                        }

                        if ($deviation) {
                            $deviationUrl = route('filament.dashboard.resources.temperature-deviations.view', ['record' => $deviation->id]);
                            $linkStart = "<a href='$deviationUrl' style='text-decoration: none; color: inherit;'>";
                            $linkEnd = '</a>';
                        }

                        if ($deviation) {
                            $deviationUrl = route('filament.dashboard.resources.temperature-deviations.view', ['record' => $deviation->id]);
                            $button = "<br><a href='$deviationUrl' class='inline-flex items-center px-2 py-1 bg-danger text-xs rounded hover:bg-red-600 mt-1' style='display:inline-block; margin-top:4px;'>View Deviation</a>";

                            return "$linkStart <div style='$color'>Time: $time1100 <br> Temp: $temp1100 °C <br> Humidity: $rh1100% <br> PIC: $pic1100 <br> $button</div> $linkEnd";
                        } else {
                            return "$linkStart <div style='$color'>Time: $time1100 <br> Temp: $temp1100 °C <br> Humidity: $rh1100% <br> PIC: $pic1100</div> $linkEnd";
                        }
                    })->html(),
                TextColumn::make('1400_data')
                    ->label('14:00')
                    ->getStateUsing(function ($record) {
                        $temp1400 = $record->temp_1400 ?? '-';
                        $time1400 = $record->time_1400 ? Carbon::parse($record->time_1400)->format('H:i') : '-';
                        $rh1400 = $record->rh_1400 ?? '-';
                        $pic1400 = $record->formatPicSignature('pic_1400');

                        $color = '';
                        $linkStart = '';
                        $linkEnd = '';
                        $deviation = null;

                        // Check if there's a deviation record matching the specific time and temperature
                        if ($temp1400 !== '-' && $record->time_1400) {
                            $deviation = $record->temperatureDeviations
                                ->where('time', $record->time_1400)
                                ->where('temperature_deviation', $temp1400)
                                ->first();
                        }

                        // Apply red styling only if temperature is out of range OR if there's a matching deviation record
                        $isOutOfRange = $temp1400 !== '-' &&
                            ($temp1400 < $record->roomTemperature->temperature_start || $temp1400 > $record->roomTemperature->temperature_end);

                        if ($isOutOfRange || $deviation) {
                            $color = 'color: red; font-weight: bold;';
                        }

                        if ($deviation) {
                            $deviationUrl = route('filament.dashboard.resources.temperature-deviations.view', ['record' => $deviation->id]);
                            $linkStart = "<a href='$deviationUrl' style='text-decoration: none; color: inherit;'>";
                            $linkEnd = '</a>';
                        }

                        if ($deviation) {
                            $deviationUrl = route('filament.dashboard.resources.temperature-deviations.view', ['record' => $deviation->id]);
                            $button = "<br><a href='$deviationUrl' class='inline-flex items-center px-2 py-1 bg-danger text-xs rounded hover:bg-red-600 mt-1' style='display:inline-block; margin-top:4px;'>View Deviation</a>";

                            return "$linkStart <div style='$color'>Time: $time1400 <br> Temp: $temp1400 °C <br> Humidity: $rh1400% <br> PIC: $pic1400 <br> $button</div> $linkEnd";
                        } else {
                            return "$linkStart <div style='$color'>Time: $time1400 <br> Temp: $temp1400 °C <br> Humidity: $rh1400% <br> PIC: $pic1400</div> $linkEnd";
                        }
                    })->html(),
                TextColumn::make('1700_data')
                    ->label('17:00')
                    ->getStateUsing(function ($record) {
                        $temp1700 = $record->temp_1700 ?? '-';
                        $time1700 = $record->time_1700 ? Carbon::parse($record->time_1700)->format('H:i') : '-';
                        $rh1700 = $record->rh_1700 ?? '-';
                        $pic1700 = $record->formatPicSignature('pic_1700');

                        $color = '';
                        $linkStart = '';
                        $linkEnd = '';
                        $deviation = null;

                        // Check if there's a deviation record matching the specific time and temperature
                        if ($temp1700 !== '-' && $record->time_1700) {
                            $deviation = $record->temperatureDeviations
                                ->where('time', $record->time_1700)
                                ->where('temperature_deviation', $temp1700)
                                ->first();
                        }

                        // Apply red styling only if temperature is out of range OR if there's a matching deviation record
                        $isOutOfRange = $temp1700 !== '-' &&
                            ($temp1700 < $record->roomTemperature->temperature_start || $temp1700 > $record->roomTemperature->temperature_end);

                        if ($isOutOfRange || $deviation) {
                            $color = 'color: red; font-weight: bold;';
                        }

                        if ($deviation) {
                            $deviationUrl = route('filament.dashboard.resources.temperature-deviations.view', ['record' => $deviation->id]);
                            $linkStart = "<a href='$deviationUrl' style='text-decoration: none; color: inherit;'>";
                            $linkEnd = '</a>';
                        }

                        if ($deviation) {
                            $deviationUrl = route('filament.dashboard.resources.temperature-deviations.view', ['record' => $deviation->id]);
                            $button = "<br><a href='$deviationUrl' class='inline-flex items-center px-2 py-1 bg-danger text-xs rounded hover:bg-red-600 mt-1' style='display:inline-block; margin-top:4px;'>View Deviation</a>";

                            return "$linkStart <div style='$color'>Time: $time1700 <br> Temp: $temp1700 °C <br> Humidity: $rh1700% <br> PIC: $pic1700 <br> $button</div> $linkEnd";
                        } else {
                            return "$linkStart <div style='$color'>Time: $time1700 <br> Temp: $temp1700 °C <br> Humidity: $rh1700% <br> PIC: $pic1700</div> $linkEnd";
                        }
                    })->html(),
                TextColumn::make('2000_data')
                    ->label('20:00')
                    ->getStateUsing(function ($record) {
                        $temp2000 = $record->temp_2000 ?? '-';
                        $time2000 = $record->time_2000 ? Carbon::parse($record->time_2000)->format('H:i') : '-';
                        $rh2000 = $record->rh_2000 ?? '-';
                        $pic2000 = $record->formatPicSignature('pic_2000');

                        $color = '';
                        $linkStart = '';
                        $linkEnd = '';
                        $deviation = null;

                        // Check if there's a deviation record matching the specific time and temperature
                        if ($temp2000 !== '-' && $record->time_2000) {
                            $deviation = $record->temperatureDeviations
                                ->where('time', $record->time_2000)
                                ->where('temperature_deviation', $temp2000)
                                ->first();
                        }

                        // Apply red styling only if temperature is out of range OR if there's a matching deviation record
                        $isOutOfRange = $temp2000 !== '-' &&
                            ($temp2000 < $record->roomTemperature->temperature_start || $temp2000 > $record->roomTemperature->temperature_end);

                        if ($isOutOfRange || $deviation) {
                            $color = 'color: red; font-weight: bold;';
                        }

                        if ($deviation) {
                            $deviationUrl = route('filament.dashboard.resources.temperature-deviations.view', ['record' => $deviation->id]);
                            $linkStart = "<a href='$deviationUrl' style='text-decoration: none; color: inherit;'>";
                            $linkEnd = '</a>';
                        }

                        if ($deviation) {
                            $deviationUrl = route('filament.dashboard.resources.temperature-deviations.view', ['record' => $deviation->id]);
                            $button = "<br><a href='$deviationUrl' class='inline-flex items-center px-2 py-1 bg-danger text-xs rounded hover:bg-red-600 mt-1' style='display:inline-block; margin-top:4px;'>View Deviation</a>";

                            return "$linkStart <div style='$color'>Time: $time2000 <br> Temp: $temp2000 °C <br> Humidity: $rh2000% <br> PIC: $pic2000 <br> $button</div> $linkEnd";
                        } else {
                            return "$linkStart <div style='$color'>Time: $time2000 <br> Temp: $temp2000 °C <br> Humidity: $rh2000% <br> PIC: $pic2000</div> $linkEnd";
                        }
                    })->html(),
                TextColumn::make('2300_data')
                    ->label('23:00')
                    ->getStateUsing(function ($record) {
                        $temp2300 = $record->temp_2300 ?? '-';
                        $time2300 = $record->time_2300 ? Carbon::parse($record->time_2300)->format('H:i') : '-';
                        $rh2300 = $record->rh_2300 ?? '-';
                        $pic2300 = $record->formatPicSignature('pic_2300');

                        $color = '';
                        $linkStart = '';
                        $linkEnd = '';
                        $deviation = null;

                        // Check if there's a deviation record matching the specific time and temperature
                        if ($temp2300 !== '-' && $record->time_2300) {
                            $deviation = $record->temperatureDeviations
                                ->where('time', $record->time_2300)
                                ->where('temperature_deviation', $temp2300)
                                ->first();
                        }

                        // Apply red styling only if temperature is out of range OR if there's a matching deviation record
                        $isOutOfRange = $temp2300 !== '-' &&
                            ($temp2300 < $record->roomTemperature->temperature_start || $temp2300 > $record->roomTemperature->temperature_end);

                        if ($isOutOfRange || $deviation) {
                            $color = 'color: red; font-weight: bold;';
                        }

                        if ($deviation) {
                            $deviationUrl = route('filament.dashboard.resources.temperature-deviations.view', ['record' => $deviation->id]);
                            $linkStart = "<a href='$deviationUrl' style='text-decoration: none; color: inherit;'>";
                            $linkEnd = '</a>';
                        }

                        if ($deviation) {
                            $deviationUrl = route('filament.dashboard.resources.temperature-deviations.view', ['record' => $deviation->id]);
                            $button = "<br><a href='$deviationUrl' class='inline-flex items-center px-2 py-1 bg-danger text-xs rounded hover:bg-red-600 mt-1' style='display:inline-block; margin-top:4px;'>View Deviation</a>";

                            return "$linkStart <div style='$color'>Time: $time2300 <br> Temp: $temp2300 °C <br> Humidity: $rh2300% <br> PIC: $pic2300 <br> $button</div> $linkEnd";
                        } else {
                            return "$linkStart <div style='$color'>Time: $time2300 <br> Temp: $temp2300 °C <br> Humidity: $rh2300% <br> PIC: $pic2300</div> $linkEnd";
                        }
                    })->html(),
                TextColumn::make('reviewed_by')
                    ->label('Reviewed By')
                    ->searchable()
                    ->getStateUsing(function ($record) {
                        return $record->reviewed_by ? $record->reviewed_by : '-';
                    }),
                TextColumn::make('acknowledged_by')
                    ->label('Acknowledged By')
                    ->searchable()
                    ->getStateUsing(function ($record) {
                        return $record->acknowledged_by ? $record->acknowledged_by : '-';
                    }),
            ])
            ->filters([
                Filter::make('locations')
                    ->label('Location')
                    ->schema([
                        Select::make('location_id')
                            ->label('Location')
                            ->options(Location::pluck('location_name', 'id'))
                            ->searchable()
                            ->reactive()
                            ->required(),
                        Select::make('room_id')
                            ->label('Room')
                            ->relationship('room', 'room_name')
                            ->options(function (callable $get) {
                                $locationId = $get('location_id');

                                if (! $locationId) {
                                    return [];
                                }

                                return Room::where('location_id', $locationId)
                                    ->pluck('room_name', 'id');
                            })
                            ->searchable()
                            ->reactive()
                            ->preload()
                            ->required()
                            ->disabled(fn (callable $get) => ! $get('location_id')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (! $data['location_id']) {
                            return $query;
                        }

                        $query->where('location_id', $data['location_id']);

                        if ($data['room_id']) {
                            $query->where('room_id', $data['room_id']);
                        }

                        return $query;
                    })
                    ->indicateUsing(function (array $data): ?array {
                        $indicators = [];
                        if (! $data['location_id']) {
                            return null;
                        } else {
                            $locationName = Location::find($data['location_id'])->location_name ?? 'Unknown Location';
                            $indicators[] = Indicator::make("Location: {$locationName}")->removeField('location_id');
                        }

                        if (! $data['room_id']) {
                            return null;
                        } else {
                            $roomName = Room::find($data['room_id'])->room_name ?? 'All Rooms';
                            $indicators[] = Indicator::make("Room: {$roomName}")->removeField('room_id');
                        }

                        return $indicators;
                    }),
                Filter::make('period')
                    ->schema([
                        DatePicker::make('period')
                            ->label('Period')
                            ->displayFormat('M Y')
                            ->native(false)
                            ->closeOnDateSelection(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (! $data['period']) {
                            return $query;
                        }

                        $date = Carbon::parse($data['period']);

                        return $query->whereBetween('period', [$date->startOfMonth(), $date->endOfMonth()]);
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn ($record) => Auth::user()->hasRole('Super Admin') || (
                        $record->date == now()->toDateString() && Auth::user()->hasRole(['Supply Chain Officer', 'Security'])
                    )),
                DeleteAction::make()
                    ->visible(fn ($record) => Auth::user()->hasRole('Super Admin') || (
                        $record->date == now()->toDateString() && Auth::user()->hasRole(['Supply Chain Officer', 'Security'])
                    ))
                    ->successNotificationTitle('Temperature Humidity deleted successfully'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->successNotificationTitle('Selected Temperature Humidity deleted successfully'),
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
            'index' => ListTemperatureHumidity::route('/all'),
            'create' => CreateTemperatureHumidity::route('/create'),
            'edit' => EditTemperatureHumidity::route('/{record}/edit'),
            'view' => ViewTemperatureHumidity::route('/view/{record}'),
            'reviewed' => ReviewedTemperatureHumidity::route('/reviewed'),
            'acknowledged' => AcknowledgedTemperatureHumidity::route('/acknowledged'),
        ];
    }

    public static function getNavigationItems(): array
    {
        return [
            NavigationItem::make()
                ->label('All')
                ->url(fn () => TemperatureHumidityResource::getUrl('index'))
                ->isActiveWhen(fn () => ! request()->routeIs('filament.dashboard.resources.temperature-humidities.reviewed'))
                ->group('Temperature & Humidity')
                ->sort(0),
            NavigationItem::make()
                ->label('Reviewed')
                ->isActiveWhen(fn () => request()->routeIs('filament.dashboard.resources.temperature-humidities.reviewed'))
                ->sort(1),
            NavigationItem::make()
                ->label('Acknowledged')
                ->isActiveWhen(fn () => request()->routeIs('filament.dashboard.resources.temperature-humidities.acknowledged'))
                ->sort(1),
        ];
    }
}
