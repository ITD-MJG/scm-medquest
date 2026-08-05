<?php

namespace App\Filament\Resources\TemperatureHumidities\Pages;

use App\Filament\Resources\TemperatureDeviations\TemperatureDeviationResource;
use App\Filament\Resources\TemperatureHumidities\TemperatureHumidityResource;
use App\Traits\HasLocationBasedAccess;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class EditTemperatureHumidity extends EditRecord
{
    use HasLocationBasedAccess;

    protected static string $resource = TemperatureHumidityResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        // Check if user can access this record's location
        if (! static::canAccessLocation($this->record->location_id)) {
            abort(403, 'You do not have permission to access this record.');
        }
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Strip transient UI-only fields that don't exist as DB columns
        unset($data['temperature_start'], $data['temperature_end'], $data['observed_temperature']);

        // Ensure temperature numeric fields are set
        $tempFields = ['temp_0200', 'temp_0500', 'temp_0800', 'temp_1100', 'temp_1400', 'temp_1700', 'temp_2000', 'temp_2300'];
        foreach ($tempFields as $temp) {
            $data[$temp] = $data[$temp] ?? null;
        }

        // Clear any existing deviation flag to ensure we only check the current time slot
        session()->forget('deviation_triggered');
        session()->forget('deviation_data');

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $start = microtime(true);
        Log::info('[TIMING] handleRecordUpdate START for record '.$record->id);

        $result = parent::handleRecordUpdate($record, $data);

        $elapsed = microtime(true) - $start;
        Log::info('[TIMING] handleRecordUpdate END - took '.$elapsed.'s for record '.$record->id);

        return $result;
    }

    protected function afterSave(): void
    {
        $record = $this->record;
        // Get temperature range from room temperature (not location)
        $roomTemp = $record->roomTemperature;
        $minTemp = $roomTemp->temperature_start;
        $maxTemp = $roomTemp->temperature_end;
        $location = $record->location;
        $now = Carbon::now('Asia/Jakarta');

        $timeWindows = [
            'temp_0200' => ['start' => '02:00:00', 'end' => '02:30:59', 'time_field' => 'time_0200'],
            'temp_0500' => ['start' => '05:00:00', 'end' => '05:30:59', 'time_field' => 'time_0500'],
            'temp_0800' => ['start' => '08:00:00', 'end' => '08:30:59', 'time_field' => 'time_0800'],
            'temp_1100' => ['start' => '11:00:00', 'end' => '11:30:59', 'time_field' => 'time_1100'],
            'temp_1400' => ['start' => '14:00:00', 'end' => '14:30:59', 'time_field' => 'time_1400'],
            'temp_1700' => ['start' => '17:00:00', 'end' => '17:30:59', 'time_field' => 'time_1700'],
            'temp_2000' => ['start' => '20:00:00', 'end' => '20:30:59', 'time_field' => 'time_2000'],
            'temp_2300' => ['start' => '23:00:00', 'end' => '23:30:59', 'time_field' => 'time_2300'],
        ];

        foreach ($timeWindows as $tempField => $window) {
            $start = Carbon::createFromTimeString($window['start'], 'Asia/Jakarta');
            $end = Carbon::createFromTimeString($window['end'], 'Asia/Jakarta');

            if ($now->between($start, $end)) {
                $tempValue = $record->$tempField;
                $timeValue = $record->{$window['time_field']};

                if (! is_null($tempValue) && ($tempValue < $minTemp || $tempValue > $maxTemp)) {
                    session()->put('deviation_triggered', true);
                    session()->put('deviation_data', [[ // wrap in array to match expected format
                        'temperature_id' => $record->id,
                        'location_id' => $record->location_id,
                        'room_id' => $record->room_id,
                        'room_temperature_id' => $record->room_temperature_id,
                        'serial_number_id' => $record->serial_number_id,
                        'time' => $timeValue,
                        'temperature_deviation' => $tempValue,
                    ]]);
                }

                break; // Only evaluate the current time window
            }
        }

        $recipient = auth()->user();
        Notification::make()
            ->success()
            ->title('Temperature & Humidity for '.$location->location_name.' updated')
            ->body('Please check the Temperature & Humidity page for more details.')
            ->actions([
                Action::make('View')
                    ->url(TemperatureHumidityResource::getUrl('view', ['record' => $record]))
                    ->icon('heroicon-o-eye')
                    ->color('success')
                    ->close()
                    ->markAsRead(),
            ])
            ->sendToDatabase($recipient);
    }

    protected function getRedirectUrl(): string
    {
        // Check if deviation was triggered
        if (session()->pull('deviation_triggered', false)) {
            $deviations = session()->pull('deviation_data', []);

            if (! empty($deviations) && isset($deviations[0])) {
                return TemperatureDeviationResource::getUrl('create', [
                    'temp_id' => $deviations[0]['temperature_id'],
                    'location_id' => $this->record->location_id,
                    'time' => $deviations[0]['time'],
                    'temperature_deviation' => $deviations[0]['temperature_deviation'],
                ]);
            }
        }

        // Default fallback
        return $this->getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('is_reviewed')
                ->label('Mark as Reviewed')
                ->visible(fn () => Auth::user()->hasRole(['Super Admin', 'Supply Chain Manager']))
                ->action(function (Model $record) {
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
                ->icon('heroicon-o-check'),
            Action::make('is_acknowledged')
                ->label('Mark as Acknowledged')
                ->visible(fn () => Auth::user()->hasRole(['Super Admin', 'QA Manager']))
                ->action(function (Model $record) {
                    $record->update([
                        'is_acknowledged' => true,
                        'acknowledged_by' => auth()->user()->initial.' '.strtoupper(now('Asia/Jakarta')->format('d M Y')),
                        'acknowledged_at' => now('Asia/Jakarta'),
                    ]);
                    Notification::make()
                        ->title('Success!')
                        ->body('Marked as acknowledged successfully by '.auth()->user()->name.'.')
                        ->success()
                        ->send();
                })
                ->requiresConfirmation()
                ->color('info')
                ->icon('heroicon-o-check'),
        ];
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Temperature & Humidity successfully updated');
    }
}
