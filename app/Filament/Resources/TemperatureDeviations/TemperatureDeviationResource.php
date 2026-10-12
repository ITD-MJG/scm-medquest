<?php

namespace App\Filament\Resources\TemperatureDeviations;

use App\Filament\Resources\TemperatureDeviations\Pages\AcknowledgedTemperatureDeviation;
use App\Filament\Resources\TemperatureDeviations\Pages\CreateTemperatureDeviation;
use App\Filament\Resources\TemperatureDeviations\Pages\EditTemperatureDeviation;
use App\Filament\Resources\TemperatureDeviations\Pages\ListTemperatureDeviations;
use App\Filament\Resources\TemperatureDeviations\Pages\ReviewedTemperatureDeviation;
use App\Filament\Resources\TemperatureDeviations\Pages\ViewTemperatureDeviation;
use App\Models\Location;
use App\Models\Room;
use App\Models\RoomTemperature;
use App\Models\SerialNumber;
use App\Models\TemperatureDeviation;
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
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class TemperatureDeviationResource extends Resource
{
    use HasLocationBasedAccess;

    protected static ?string $model = TemperatureDeviation::class;

    protected static ?int $navigationSort = 1;

    protected static string|\UnitEnum|null $navigationGroup = 'Temperature Deviation';

    protected static bool $shouldRegisterNavigation = false;

    public static function getEloquentQuery(): Builder
    {
        return static::applyLocationFilter(parent::getEloquentQuery());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Hidden::make('temperature_humidity_id')->default(request()->get('temp_id')),
            Section::make('Date & Time')
                ->columns(2)
                ->schema([
                    DatePicker::make('date')->label('Date')->default(Carbon::now())->required(),
                    TimePicker::make('time')
                        ->label('Time')
                        ->seconds(false)
                        ->default(function () {
                            $time = request()->get('time');

                            return $time ? Carbon::createFromFormat('H:i', $time)->format('H:i') : null;
                        })
                        ->required(),
                ]),
            Section::make('Location & Storage Temperature Standards')
                ->columns(4)
                ->schema([
                    Hidden::make('temperature_humidity_id')->default(function () {
                        $humidity = TemperatureHumidity::query()
                            ->whereDate('created_at', now('Asia/Jakarta')->toDateString())
                            ->latest()
                            ->first();

                        return $humidity->id;
                    }),
                    Select::make('location_id')
                        ->label('Location')
                        ->relationship(
                            'location',
                            'location_name',
                            modifyQueryUsing: function (Builder $query) {
                                $accessibleLocationIds = static::getAccessibleLocationIdsForTemperature();
                                if (! empty($accessibleLocationIds)) {
                                    $query->whereIn('id', $accessibleLocationIds);
                                }
                            },
                        )
                        ->default(function () {
                            $user = Auth::user();
                            // Check URL parameter first
                            if (request()->get('location_id')) {
                                return request()->get('location_id');
                            }
                            // If user has specific location and doesn't have admin-level roles, default to that
                            if ($user->location_id && ! $user->hasRole(['Super Admin', 'Admin', 'Supply Chain Manager', 'QA Manager'])) {
                                return $user->location_id;
                            }

                            return null;
                        })
                        ->preload()
                        ->searchable()
                        ->reactive()
                        ->required(),
                    Select::make('room_id')
                        ->label('Room')
                        ->relationship('room', 'room_name')
                        ->default(fn () => request()->get('room_id') ?? null)
                        ->options(function (callable $get) {
                            $locationId = $get('location_id');

                            if (! $locationId) {
                                return [];
                            }

                            return Room::where('location_id', $locationId)->pluck('room_name', 'id');
                        })
                        ->searchable()
                        ->reactive()
                        ->preload()
                        ->required()
                        ->disabled(fn (callable $get) => ! $get('location_id')),
                    Select::make('serial_number_id')
                        ->label('Serial Number')
                        ->default(fn () => request()->get('serial_number_id') ?? null)
                        ->options(function (callable $get) {
                            $roomId = $get('room_id');

                            if (! $roomId) {
                                return [];
                            }

                            return SerialNumber::where('room_id', $roomId)->pluck('serial_number', 'id');
                        })
                        ->searchable()
                        ->required()
                        ->disabled(fn (callable $get) => ! $get('room_id'))
                        ->preload()
                        ->required(),
                    Select::make('room_temperature_id')
                        ->label('Room Temperature Standards')
                        ->default(fn () => request()->get('room_temperature_id') ?? null)
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
                                $set('temperature_start', $roomTemperature->temperature_start);
                                $set('temperature_end', $roomTemperature->temperature_end);
                            }
                        }),
                    Hidden::make('temperature_start'),
                    Hidden::make('temperature_end'),
                ]),
            Section::make('Temperature Deviation & Reason (Filled by Staff / Security) ')
                ->columns(2)
                ->schema([
                    TextInput::make('temperature_deviation')
                        ->label('Temperature deviation (°C)')
                        ->required(Auth::user()->hasRole(['Staff', 'Security']))
                        ->default(fn () => request()->get('temperature_deviation'))
                        ->dehydrated(),
                    TextArea::make('deviation_reason')
                        ->label('Reason for deviation')
                        ->required(Auth::user()->hasRole(['Staff', 'Security']))
                        ->dehydrated(),
                ])
                ->disabled(fn () => ! Auth::user()->hasRole(['Supply Chain Officer', 'Security'])),
            Section::make('Length of Temperature Deviation & Risk Analysis (Filled by QA Staff / Supervisor)')
                ->columns(2)
                ->schema([
                    TextInput::make('length_temperature_deviation')
                        ->label('Length Temperature deviation (Minutes/Hours)')
                        ->required(Auth::user()->hasRole(['QA Staff', 'QA Supervisor']))
                        ->dehydrated(),
                    TextArea::make('risk_analysis')
                        ->label('Risk Analysis')
                        ->required(Auth::user()->hasRole(['QA Staff', 'QA Supervisor']))
                        ->dehydrated(),
                ])
                ->disabled(fn () => ! Auth::user()->hasAnyRole(['QA Staff', 'QA Supervisor'])),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->header(
                view('filament.tables.top-bottom-pagination-tables', [
                    'table' => $table,
                ]),
            )
            ->modifyQueryUsing(function ($query) {
                return $query->with(['location', 'room', 'roomTemperature'])->orderByDesc('created_at');
            })
            ->columns([
                TextColumn::make('date')->label('Date (Tanggal)')->sortable()->searchable()->date('d/m/Y'),
                TextColumn::make('location_id')
                    ->label('Location / Room / Temp')
                    ->getStateUsing(function ($record) {
                        return $record->location->location_name.'<br>'.
                               $record->room->room_name.'<br>'.
                               $record->roomTemperature->temperature_start.'°C to '.$record->roomTemperature->temperature_end.'°C';
                    })
                    ->sortable()
                    ->searchable()
                    ->html(),
                TextColumn::make('time')->label('Time (Jam)')->sortable()->searchable()->time('H:i'),
                TextColumn::make('temperature_deviation')->label('Temperature Deviation (°C)'),
                TextColumn::make('deviation_reason')->label('Reason for Deviation'),
                TextColumn::make('length_temperature_deviation')->label('Length of Temperature Deviation (Menit/Jam)'),
                TextColumn::make('pic')->label('PIC (SCM)'),
                TextColumn::make('risk_analysis')->label('Risk Analysis of impact deviation'),
                TextColumn::make('analyzer_pic')->label('Analyzed by (QA)'),
                TextColumn::make('reviewed_by')->label('Reviewed by'),
                TextColumn::make('acknowledged_by')->label('Acknowledged by'),
            ])
            ->filters([
                SelectFilter::make('location_id')->label('Location')->relationship('location', 'location_name', modifyQueryUsing: fn (Builder $query) => $query->whereIn('location_code', ['BP1', 'BP2']))->searchable()->preload(),
                SelectFilter::make('room_id')->label('Room')->relationship('room', 'room_name')->searchable()->preload(),
                Filter::make('period')
                    ->schema([DatePicker::make('period')->label('Period')->displayFormat('M Y')->native(false)->closeOnDateSelection()])
                    ->query(function (Builder $query, array $data): Builder {
                        if (! $data['period']) {
                            return $query;
                        }

                        $date = Carbon::parse($data['period']);

                        return $query->whereBetween('date', [$date->startOfMonth(), $date->endOfMonth()]);
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn ($record) => Auth::user()->hasRole(['Supply Chain Officer', 'QA Staff', 'QA Supervisor'])),
                DeleteAction::make()
                    ->visible(fn ($record) => Auth::user()->hasRole(['Supply Chain Officer', 'QA Staff', 'QA Supervisor']))
                    ->successNotificationTitle('Temperature Deviation deleted successfully'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->successNotificationTitle('Selected Temperature Deviation(s) deleted successfully'),
                ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Date & Time')
                ->columns(2)
                ->schema([TextEntry::make('date')->label('Date')->date('d/m/Y'), TextEntry::make('time')->label('Time')->time('H:i')]),
            Section::make('Reviewed & Acknowledged')
                ->columns(2)
                ->schema([TextEntry::make('reviewed_by')->label('Reviewed By')->formatStateUsing(fn ($record) => $record->reviewed_by ? $record->reviewed_by : '-'), TextEntry::make('acknowledged_by')->label('Acknowledged By')->formatStateUsing(fn ($record) => $record->acknowledged_by ? $record->acknowledged_by : '-')]),
            Section::make('Location & Storage Temperature Standards')
                ->columns(4)
                ->schema([TextEntry::make('location_id')->label('Location')->formatStateUsing(fn ($record) => $record->location->location_name), TextEntry::make('room_id')->label('Room')->formatStateUsing(fn ($record) => $record->room->room_name), TextEntry::make('serial_number_id')->label('Serial Number')->formatStateUsing(fn ($record) => $record->serialNumber->serial_number), TextEntry::make('room_temperature_id')->label('Storage Temperature Standards')->formatStateUsing(fn ($record) => $record->roomTemperature->temperature_start.'°C to '.$record->roomTemperature->temperature_end.'°C')]),
            Section::make('Temperature Deviation & Reason')
                ->columns(2)
                ->schema([TextEntry::make('temperature_deviation')->label('Temperature Deviation'), TextEntry::make('deviation_reason')->label('Reason for Deviation')]),
            Section::make('Length of Temperature Deviation & Risk Analysis')
                ->columns(2)
                ->schema([TextEntry::make('length_temperature_deviation')->label('Length of Temperature Deviation'), TextEntry::make('risk_analysis')->label('Risk Analysis')]),
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
            'index' => ListTemperatureDeviations::route('/'),
            'create' => CreateTemperatureDeviation::route('/create'),
            'edit' => EditTemperatureDeviation::route('/{record}/edit'),
            'view' => ViewTemperatureDeviation::route('/view/{record}'),
            'reviewed' => ReviewedTemperatureDeviation::route('/reviewed'),
            'acknowledged' => AcknowledgedTemperatureDeviation::route('/acknowledged'),
        ];
    }
}
