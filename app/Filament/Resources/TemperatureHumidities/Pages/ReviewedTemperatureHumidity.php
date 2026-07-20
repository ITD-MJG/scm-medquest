<?php

namespace App\Filament\Resources\TemperatureHumidities\Pages;

use App\Filament\Resources\TemperatureHumidities\TemperatureHumidityResource;
use App\Models\TemperatureHumidity;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class ReviewedTemperatureHumidity extends ListRecords
{
    protected static string $resource = TemperatureHumidityResource::class;

    protected static ?string $title = 'Pending Review';

    public function getBreadcrumb(): string
    {
        return 'Pending Review'; // or any label you want
    }

    public function table(Table $table): Table
    {
        return $table
            ->header(view('filament.tables.top-bottom-pagination-tables', [
                'table' => $table,
            ]))
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['location', 'room', 'serialNumber', 'temperatureDeviations', 'roomTemperature'])->orderByDesc('date')->where('is_reviewed', false)
                ->whereNotNull('time_0200')
                ->whereNotNull('time_0500')
                ->whereNotNull('time_0800')
                ->whereNotNull('time_1100')
                ->whereNotNull('time_1400')
                ->whereNotNull('time_1700')
                ->whereNotNull('time_2000')
                ->whereNotNull('time_2300')
                ->whereNotNull('temp_0200')
                ->whereNotNull('temp_0500')
                ->whereNotNull('temp_0800')
                ->whereNotNull('temp_1100')
                ->whereNotNull('temp_1400')
                ->whereNotNull('temp_1700')
                ->whereNotNull('temp_2000')
                ->whereNotNull('temp_2300')
                ->whereNotNull('pic_0200')
                ->whereNotNull('pic_0500')
                ->whereNotNull('pic_0800')
                ->whereNotNull('pic_1100')
                ->whereNotNull('pic_1400')
                ->whereNotNull('pic_1700')
                ->whereNotNull('pic_2000')
                ->whereNotNull('pic_2300'))
            ->emptyStateHeading('No pending review data is found')
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

                        if ($temp0200 !== '-') {
                            if ($temp0200 < $record->roomTemperature->temperature_start || $temp0200 > $record->roomTemperature->temperature_end) {
                                $color = 'color: red; font-weight: bold;';

                                // Check if there's a deviation record for this time slot (02:00 - 02:30)
                                $deviation = $record->temperatureDeviations
                                    ->filter(fn ($d) => Carbon::parse($d->time)->between('02:00:00', '02:30:59'))
                                    ->first();

                                if ($deviation) {
                                    $deviationUrl = route('filament.dashboard.resources.temperature-deviations.view', $deviation->id);
                                    $linkStart = "<a href='$deviationUrl' style='text-decoration: none; color: inherit;'>";
                                    $linkEnd = '</a>';
                                }
                            }
                        }

                        return "$linkStart <div style='$color'>Time: $time0200 <br> Temp: $temp0200 °C <br> Humidity: $rh0200% <br> PIC: $pic0200</div> $linkEnd";
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

                        if ($temp0500 !== '-') {
                            if ($temp0500 < $record->roomTemperature->temperature_start || $temp0500 > $record->roomTemperature->temperature_end) {
                                $color = 'color: red; font-weight: bold;';

                                // Check if there's a deviation record for this time slot (05:00 - 05:30)
                                $deviation = $record->temperatureDeviations
                                    ->filter(fn ($d) => Carbon::parse($d->time)->between('05:00:00', '05:30:59'))
                                    ->first();

                                if ($deviation) {
                                    $deviationUrl = route('filament.dashboard.resources.temperature-deviations.view', $deviation->id);
                                    $linkStart = "<a href='$deviationUrl' style='text-decoration: none; color: inherit;'>";
                                    $linkEnd = '</a>';
                                }
                            }
                        }

                        return "$linkStart <div style='$color'>Time: $time0500 <br> Temp: $temp0500 °C <br> Humidity: $rh0500% <br> PIC: $pic0500</div> $linkEnd";
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

                        if ($temp0800 !== '-') {
                            if ($temp0800 < $record->roomTemperature->temperature_start || $temp0800 > $record->roomTemperature->temperature_end) {
                                $color = 'color: red; font-weight: bold;';

                                // Check if there's a deviation record for this time slot (08:00 - 08:30)
                                $deviation = $record->temperatureDeviations
                                    ->filter(fn ($d) => Carbon::parse($d->time)->between('08:00:00', '08:30:59'))
                                    ->first();

                                if ($deviation) {
                                    $deviationUrl = route('filament.dashboard.resources.temperature-deviations.view', $deviation->id);
                                    $linkStart = "<a href='$deviationUrl' style='text-decoration: none; color: inherit;'>";
                                    $linkEnd = '</a>';
                                }
                            }
                        }

                        return "$linkStart <div style='$color'>Time: $time0800 <br> Temp: $temp0800 °C <br> Humidity: $rh0800% <br> PIC: $pic0800</div> $linkEnd";
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

                        if ($temp1100 !== '-') {
                            if ($temp1100 < $record->roomTemperature->temperature_start || $temp1100 > $record->roomTemperature->temperature_end) {
                                $color = 'color: red; font-weight: bold;';

                                // Check if there's a deviation record for this time slot (11:00 - 11:30)
                                $deviation = $record->temperatureDeviations
                                    ->filter(fn ($d) => Carbon::parse($d->time)->between('11:00:00', '11:30:59'))
                                    ->first();

                                if ($deviation) {
                                    $deviationUrl = route('filament.dashboard.resources.temperature-deviations.view', $deviation->id);
                                    $linkStart = "<a href='$deviationUrl' style='text-decoration: none; color: inherit;'>";
                                    $linkEnd = '</a>';
                                }
                            }
                        }

                        return "$linkStart <div style='$color'>Time: $time1100 <br> Temp: $temp1100 °C <br> Humidity: $rh1100% <br> PIC: $pic1100</div> $linkEnd";
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

                        if ($temp1400 !== '-') {
                            if ($temp1400 < $record->roomTemperature->temperature_start || $temp1400 > $record->roomTemperature->temperature_end) {
                                $color = 'color: red; font-weight: bold;';

                                // Check if there's a deviation record for this time slot (14:00 - 14:30)
                                $deviation = $record->temperatureDeviations
                                    ->filter(fn ($d) => Carbon::parse($d->time)->between('14:00:00', '14:30:59'))
                                    ->first();

                                if ($deviation) {
                                    $deviationUrl = route('filament.dashboard.resources.temperature-deviations.view', $deviation->id);
                                    $linkStart = "<a href='$deviationUrl' style='text-decoration: none; color: inherit;'>";
                                    $linkEnd = '</a>';
                                }
                            }
                        }

                        return "$linkStart <div style='$color'>Time: $time1400 <br> Temp: $temp1400 °C <br> Humidity: $rh1400% <br> PIC: $pic1400</div> $linkEnd";
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

                        if ($temp1700 !== '-') {
                            if ($temp1700 < $record->roomTemperature->temperature_start || $temp1700 > $record->roomTemperature->temperature_end) {
                                $color = 'color: red; font-weight: bold;';

                                // Check if there's a deviation record for this time slot (17:00 - 17:30)
                                $deviation = $record->temperatureDeviations
                                    ->filter(fn ($d) => Carbon::parse($d->time)->between('17:00:00', '17:30:59'))
                                    ->first();

                                if ($deviation) {
                                    $deviationUrl = route('filament.dashboard.resources.temperature-deviations.view', $deviation->id);
                                    $linkStart = "<a href='$deviationUrl' style='text-decoration: none; color: inherit;'>";
                                    $linkEnd = '</a>';
                                }
                            }
                        }

                        return "$linkStart <div style='$color'>Time: $time1700 <br> Temp: $temp1700 °C <br> Humidity: $rh1700% <br> PIC: $pic1700</div> $linkEnd";
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

                        if ($temp2000 !== '-') {
                            if ($temp2000 < $record->roomTemperature->temperature_start || $temp2000 > $record->roomTemperature->temperature_end) {
                                $color = 'color: red; font-weight: bold;';

                                // Check if there's a deviation record for this time slot (20:00 - 20:30)
                                $deviation = $record->temperatureDeviations
                                    ->filter(fn ($d) => Carbon::parse($d->time)->between('20:00:00', '20:30:59'))
                                    ->first();

                                if ($deviation) {
                                    $deviationUrl = route('filament.dashboard.resources.temperature-deviations.view', $deviation->id);
                                    $linkStart = "<a href='$deviationUrl' style='text-decoration: none; color: inherit;'>";
                                    $linkEnd = '</a>';
                                }
                            }
                        }

                        return "$linkStart <div style='$color'>Time: $time2000 <br> Temp: $temp2000 °C <br> Humidity: $rh2000% <br> PIC: $pic2000</div> $linkEnd";
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

                        if ($temp2300 !== '-') {
                            if ($temp2300 < $record->roomTemperature->temperature_start || $temp2300 > $record->roomTemperature->temperature_end) {
                                $color = 'color: red; font-weight: bold;';

                                // Check if there's a deviation record for this time slot (23:00 - 23:30)
                                $deviation = $record->temperatureDeviations
                                    ->filter(fn ($d) => Carbon::parse($d->time)->between('23:00:00', '23:30:59'))
                                    ->first();

                                if ($deviation) {
                                    $deviationUrl = route('filament.dashboard.resources.temperature-deviations.view', $deviation->id);
                                    $linkStart = "<a href='$deviationUrl' style='text-decoration: none; color: inherit;'>";
                                    $linkEnd = '</a>';
                                }
                            }
                        }

                        return "$linkStart <div style='$color'>Time: $time2300 <br> Temp: $temp2300 °C <br> Humidity: $rh2300% <br> PIC: $pic2300</div> $linkEnd";
                    })->html(),

            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('is_reviewed')
                    ->label('Mark as Reviewed')
                    ->visible(fn () => auth()->user()->hasRole(['Super Admin', 'Supply Chain Manager']))
                    ->action(function (TemperatureHumidity $record) {
                        $record->update([
                            'is_reviewed' => true,
                            'reviewed_by' => auth()->user()->initial.' '.strtoupper(now('Asia/Jakarta')->format('d M Y')),
                            'reviewed_at' => now('Asia/Jakarta'),
                        ]);
                        Notification::make()
                            ->title('Success!')
                            ->body('Marked as reviewed successfully by '.auth()->user()->name.'.')
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->color('success')
                    ->icon('heroicon-o-check-circle'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('is_reviewed')
                        ->label('Mark as Reviewed')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(function () {
                            $admin = Auth::user()->hasRole(['Super Admin', 'Supply Chain Manager']);

                            return $admin;
                        })
                        ->action(function (Collection $records) {
                            $alreadyReviewed = $records->every(fn ($record) => $record->is_reviewed);

                            if ($alreadyReviewed) {
                                Notification::make()
                                    ->title('All selected records are already reviewed')
                                    ->warning()
                                    ->send();

                                return;
                            }
                            foreach ($records as $record) {
                                if (! $record->is_reviewed) {
                                    $record->is_reviewed = true;
                                    $record->reviewed_by = auth()->user()->initial.' '.strtoupper(now('Asia/Jakarta')->format('d M Y'));
                                    $record->reviewed_at = now('Asia/Jakarta');
                                    $record->save();
                                }
                            }

                            Notification::make()
                                ->title('Success!')
                                ->body('Selected data marked as reviewed successfully')
                                ->success()
                                ->send();
                        }),

                ]),
            ]);
    }
}
