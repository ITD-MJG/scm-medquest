<?php

namespace App\Filament\Resources\TemperatureDeviations\Pages;

use App\Exports\TemperatureDeviationExport;
use App\Filament\Resources\TemperatureDeviations\TemperatureDeviationResource;
use App\Models\Location;
use App\Models\Room;
use App\Models\TemperatureDeviation;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;

class ListTemperatureDeviations extends ListRecords
{
    protected static string $resource = TemperatureDeviationResource::class;

    protected static ?string $title = 'All Temperature Deviations';

    public function getBreadcrumb(): string
    {
        return 'All'; // or any label you want
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('export_xlsx')
                    ->label('Export to XLSX')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->modalHeading('Export Temperature Deviations to XLSX')
                    ->modalButton('Export to XLSX')
                    ->schema([
                        Select::make('location_id')->label('Location')->options(Location::pluck('location_name', 'id'))->searchable()->reactive()->required(),
                        Select::make('room_id')
                            ->label('Room')
                            ->options(function (callable $get) {
                                $locationId = $get('location_id');
                                if (! $locationId) {
                                    return [];
                                }

                                return Room::where('location_id', $locationId)->pluck('room_name', 'id');
                            })
                            ->searchable()
                            ->reactive()
                            ->multiple(),
                        Select::make('month_type')
                            ->label('Month Type')
                            ->options([
                                'this_month' => 'This Month',
                                'choose' => 'Choose Month',
                            ])
                            ->default('this_month')
                            ->reactive(),

                        DatePicker::make('chosen_month')->label('Choose Month')->displayFormat('F Y')->visible(fn ($get) => $get('month_type') === 'choose')->required(fn ($get) => $get('month_type') === 'choose'),
                    ])
                    ->action(function (array $data) {
                        $location_id = $data['location_id'];
                        $room_ids = $data['room_id'];
                        $location = Location::find($location_id);
                        if ($data['month_type'] === 'this_month') {
                            $month = now()->month;
                            $year = now()->year;
                        } else {
                            $chosenMonth = Carbon::parse($data['chosen_month']);
                            $month = $chosenMonth->month;
                            $year = $chosenMonth->year;
                        }

                        $query = TemperatureDeviation::query()
                            ->where('location_id', $location_id)
                            ->whereBetween('date', [Carbon::create($year, $month, 1)->startOfMonth(), Carbon::create($year, $month, 1)->endOfMonth()])
                            ->with(['location', 'room', 'serialNumber', 'roomTemperature']);

                        // If specific rooms are selected, filter by those rooms
                        if ($room_ids && count($room_ids) > 0) {
                            $query->whereIn('room_id', $room_ids);
                        }

                        $records = $query->get();

                        // Check if there are any records to export
                        if ($records->isEmpty()) {
                            // Show alert message
                            Notification::make()
                                ->warning()
                                ->title('No data is found')
                                ->send();

                            return;
                        }

                        $monthName = strtoupper(Carbon::createFromDate($year, $month)->format('M'));
                        $sluggedLocation = strtoupper(str_replace(' ', '_', $location->location_name));

                        if ($room_ids && count($room_ids) === 1) {
                            $room = Room::find($room_ids[0]);
                            $roomName = strtoupper(str_replace(' ', '_', $room->room_name));
                            $filename = "TemperatureDeviation_{$sluggedLocation}_{$roomName}_{$monthName}{$year}.xlsx";
                        } else {
                            $filename = "TemperatureDeviation_{$sluggedLocation}_{$monthName}{$year}.xlsx";
                        }

                        return Excel::download(new TemperatureDeviationExport($records), $filename);
                    }),
                Action::make('export_pdf')
                    ->label('Export to PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('warning')
                    ->modalHeading('Export Temperature Deviations to PDF')
                    ->modalButton('Export to PDF')
                    ->schema([
                        Select::make('location_id')->label('Location')->options(Location::pluck('location_name', 'id'))->searchable()->reactive()->required(),
                        Select::make('room_id')
                            ->label('Room')
                            ->options(function (callable $get) {
                                $locationId = $get('location_id');
                                if (! $locationId) {
                                    return [];
                                }

                                return Room::where('location_id', $locationId)->pluck('room_name', 'id');
                            })
                            ->searchable()
                            ->reactive()
                            ->multiple()
                            ->required(),
                        Select::make('month_type')
                            ->label('Month Type')
                            ->options([
                                'this_month' => 'This Month',
                                'choose' => 'Choose Month',
                            ])
                            ->default('this_month')
                            ->reactive(),

                        DatePicker::make('chosen_month')->label('Choose Month')->displayFormat('F Y')->visible(fn ($get) => $get('month_type') === 'choose')->required(fn ($get) => $get('month_type') === 'choose'),
                    ])
                    ->action(function (array $data) {
                        $location_id = $data['location_id'];
                        $room_ids = $data['room_id'];
                        $location = Location::find($location_id);

                        if ($data['month_type'] === 'this_month') {
                            $month = now()->month;
                            $year = now()->year;
                        } else {
                            $chosenMonth = Carbon::parse($data['chosen_month']);
                            $month = $chosenMonth->month;
                            $year = $chosenMonth->year;
                        }

                        $query = TemperatureDeviation::query()
                            ->where('location_id', $location_id)
                            ->whereBetween('date', [Carbon::create($year, $month, 1)->startOfMonth(), Carbon::create($year, $month, 1)->endOfMonth()])
                            ->with(['location', 'room', 'serialNumber', 'roomTemperature']);

                        // If specific rooms are selected, filter by those rooms
                        if ($room_ids && count($room_ids) > 0) {
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

                        // Check if there are any records to export
                        if ($records->isEmpty()) {
                            // Show alert message
                            Notification::make()
                                ->warning()
                                ->title('No data is found')
                                ->send();

                            return;
                        }

                        // Store the records in session for PDF export
                        $ids = $records->pluck('id')->toArray();
                        session(['export_ids' => $ids]);

                        return redirect()->route('temperature-deviations.bulk-export-pdf');
                    }),
            ])
                ->label('Export')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('primary')
                ->button(),
            CreateAction::make()
                ->label('New Temperature Deviation')
                ->color('success')
                ->visible(fn () => auth()->user()->hasRole(['Supply Chain Officer', 'Security'])),
        ];
    }
}
