<?php

namespace App\Filament\Resources\TemperatureHumidities\Pages;

use App\Exports\AllLocationsTemperatureHumidityExport;
use App\Exports\TemperatureHumidityExport;
use App\Filament\Resources\TemperatureHumidities\TemperatureHumidityResource;
use App\Models\Location;
use App\Models\Room;
use App\Models\TemperatureHumidity;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class ListTemperatureHumidity extends ListRecords
{
    protected static string $resource = TemperatureHumidityResource::class;

    protected static ?string $title = 'All Temperature & Humidity';

    public function getBreadcrumb(): string
    {
        return 'All'; // or any label you want
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('New Temperature & Humidity')
                ->color('success')
                ->visible(fn () => auth()->user()->hasRole(['Super Admin', 'Supply Chain Officer', 'Security'])),
            ActionGroup::make([
                Action::make('export_xlsx')
                    ->label('Export to XLSX')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->modalHeading('Export Temperature & Humidity to XLSX')
                    ->modalButton('Export to XLSX')
                    ->schema([
                        Select::make('location_id')
                            ->label('Location')
                            ->relationship('location', 'location_name')
                            ->options(function () {
                                $user = auth()->user();
                                if ($user->location_id) {
                                    return Location::where('id', $user->location_id)
                                        ->pluck('location_name', 'id');
                                }

                                return Location::pluck('location_name', 'id');
                            })
                            ->searchable()
                            ->reactive()
                            ->preload()
                            ->required()
                            ->afterStateUpdated(function ($state, callable $set) {
                                $set('room_id', null);
                            }),

                        Select::make('room_id')
                            ->label('Rooms')
                            ->multiple()
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

                        Select::make('month_type')
                            ->label('Month Type')
                            ->options([
                                'this_month' => 'This Month',
                                'choose' => 'Choose Month',
                            ])
                            ->default('this_month')
                            ->reactive(),

                        DatePicker::make('chosen_month')
                            ->label('Choose Month')
                            ->displayFormat('F Y')
                            ->visible(fn ($get) => $get('month_type') === 'choose')
                            ->required(fn ($get) => $get('month_type') === 'choose'),
                    ])
                    ->action(function (array $data) {
                        $location_id = $data['location_id'];
                        $room_ids = $data['room_id'];

                        if ($data['month_type'] === 'this_month') {
                            $month = now()->month;
                            $year = now()->year;
                        } else {
                            $chosenMonth = Carbon::parse($data['chosen_month']);
                            $month = $chosenMonth->month;
                            $year = $chosenMonth->year;
                        }

                        $query = TemperatureHumidity::query()
                            ->where('location_id', $location_id)
                            ->whereBetween('period', [Carbon::create($year, $month, 1)->startOfMonth(), Carbon::create($year, $month, 1)->endOfMonth()])
                            ->with(['location', 'room', 'serialNumber', 'roomTemperature']);

                        if (! empty($room_ids)) {
                            $query->whereIn('room_id', $room_ids);
                        }

                        $records = $query->get();

                        if ($records->isEmpty()) {
                            Notification::make()
                                ->warning()
                                ->title('No data is found')
                                ->send();

                            return;
                        }

                        $monthName = strtoupper(Carbon::createFromDate($year, $month)->format('M'));
                        $location = Location::find($location_id);
                        $sluggedLocation = strtoupper(str_replace(' ', '_', $location->location_name));

                        if ($room_ids && count($room_ids) === 1) {
                            $room = Room::find($room_ids[0]);
                            $roomName = strtoupper(str_replace(' ', '_', $room->room_name));
                            $filename = "TemperatureHumidity_{$sluggedLocation}_{$roomName}_{$monthName}{$year}.xlsx";
                        } else {
                            $filename = "TemperatureHumidity_{$sluggedLocation}_{$monthName}{$year}.xlsx";
                        }

                        return Excel::download(new TemperatureHumidityExport($records, $location->location_name), $filename);
                    }),
                Action::make('export_pdf')
                    ->label('Export to PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('warning')
                    ->modalHeading('Export Temperature & Humidity to PDF')
                    ->modalButton('Export to PDF')
                    ->schema([
                        Select::make('location_id')
                            ->label('Location')
                            ->relationship('location', 'location_name')
                            ->options(function () {
                                $user = auth()->user();
                                if ($user->location_id) {
                                    return Location::where('id', $user->location_id)
                                        ->pluck('location_name', 'id');
                                }

                                return Location::pluck('location_name', 'id');
                            })
                            ->searchable()
                            ->reactive()
                            ->preload()
                            ->required()
                            ->afterStateUpdated(function ($state, callable $set) {
                                $set('room_id', null);
                            }),

                        Select::make('room_id')
                            ->label('Rooms')
                            ->multiple()
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

                        Select::make('month_type')
                            ->label('Month Type')
                            ->options([
                                'this_month' => 'This Month',
                                'choose' => 'Choose Month',
                            ])
                            ->default('this_month')
                            ->reactive(),

                        DatePicker::make('chosen_month')
                            ->label('Choose Month')
                            ->displayFormat('F Y')
                            ->visible(fn ($get) => $get('month_type') === 'choose')
                            ->required(fn ($get) => $get('month_type') === 'choose'),
                    ])
                    ->action(function (array $data) {
                        $location_id = $data['location_id'];
                        $room_ids = $data['room_id'];

                        if ($data['month_type'] === 'this_month') {
                            $month = now()->month;
                            $year = now()->year;
                        } else {
                            $chosenMonth = Carbon::parse($data['chosen_month']);
                            $month = $chosenMonth->month;
                            $year = $chosenMonth->year;
                        }

                        $query = TemperatureHumidity::query()
                            ->where('location_id', $location_id)
                            ->whereBetween('period', [Carbon::create($year, $month, 1)->startOfMonth(), Carbon::create($year, $month, 1)->endOfMonth()]);

                        if (! empty($room_ids)) {
                            $query->whereIn('room_id', $room_ids);
                        }
                        $totalCount = $query->count();
                        if ($totalCount > 1000) {
                            Notification::make()
                                ->warning()
                                ->title('Data export limit')
                                ->body("Your selection has {$totalCount} records. Only the first 1,000 will be exported for performance.")
                                ->persistent()
                                ->send();
                        }
                        $query->limit(1000);
                        $records = $query->get();

                        if ($records->isEmpty()) {
                            Notification::make()
                                ->warning()
                                ->title('No data is found')
                                ->send();

                            return;
                        }

                        // Store the records in session for PDF export
                        $ids = $records->pluck('id')->toArray();
                        session(['export_ids' => $ids]);

                        return redirect()->route('temperature-humidities.bulk-export');
                    }),
            ])
                ->label('Export')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('primary')
                ->button(),
            Action::make('export_all_locations')
                ->label('Export All Locations')
                ->icon('heroicon-o-arrow-down-tray')
                ->visible(function () {
                    $user = Auth::user();

                    return $user->location_id === null &&
                        $user->hasRole(['Supply Chain Manager', 'QA Manager']);
                })
                ->schema([
                    Select::make('month_type_all')
                        ->label('Month Type')
                        ->options([
                            'this_month' => 'This Month',
                            'choose' => 'Choose Month',
                        ])
                        ->default('this_month')
                        ->reactive(),

                    DatePicker::make('chosen_month_all')
                        ->label('Choose Month')
                        ->displayFormat('F Y')
                        ->visible(fn ($get) => $get('month_type_all') === 'choose')
                        ->required(fn ($get) => $get('month_type_all') === 'choose'),
                ])
                ->action(function (array $data) {
                    if ($data['month_type_all'] === 'this_month') {
                        $month = now()->month;
                        $year = now()->year;
                    } else {
                        $chosenMonth = Carbon::parse($data['chosen_month_all']);
                        $month = $chosenMonth->month;
                        $year = $chosenMonth->year;
                    }

                    // Get all locations
                    $locations = Location::all();
                    $exportData = [];

                    foreach ($locations as $location) {
                        // Get all records for this location and month
                        $records = TemperatureHumidity::query()
                            ->where('location_id', $location->id)
                            ->whereBetween('period', [Carbon::create($year, $month, 1)->startOfMonth(), Carbon::create($year, $month, 1)->endOfMonth()])
                            ->with(['location', 'room', 'serialNumber', 'roomTemperature'])
                            ->get();

                        if ($records->isNotEmpty()) {
                            $exportData[$location->id] = [
                                'records' => $records,
                                'location' => $location,
                                'location_name' => $location->location_name,
                            ];
                        }
                    }

                    // Check if there are any records to export
                    if (empty($exportData)) {
                        // Show alert message
                        Notification::make()
                            ->warning()
                            ->title('No data is found')
                            ->send();

                        return;
                    }

                    $monthName = strtoupper(Carbon::createFromDate($year, $month)->format('M'));
                    $filename = "TemperatureHumidity_ALL_{$monthName}{$year}.xlsx";

                    return Excel::download(new AllLocationsTemperatureHumidityExport($exportData), $filename);
                }),
        ];
    }
}
