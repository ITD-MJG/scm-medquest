<?php

namespace App\Filament\Resources\TemperatureHumidities\Pages;

use Filament\Actions\Action;
use App\Models\RoomTemperature;
use Carbon\Carbon;
use App\Models\Location;
use Illuminate\Support\Str;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Resources\TemperatureHumidities\TemperatureHumidityResource;
use App\Filament\Resources\TemperatureDeviations\TemperatureDeviationResource;

class CreateTemperatureHumidity extends CreateRecord
{
    protected static string $resource = TemperatureHumidityResource::class;
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['temperatureId'] = Str::orderedUuid();
        $data['serial_number_id'] = $data['serial_number_id'] ?? null; // Ensure serial number is set
        $data['location_id'] = $data['location_id'] ?? null; // Ensure location is set
        $data['room_id'] = $data['room_id'] ?? null; // Ensure room is set
        $data['room_temperature_id'] = $data['room_temperature_id'] ?? null; // Ensure room temperature is set
        // Ensure temperature fields are set
        $tempFields = ['temp_0800', 'temp_1100', 'temp_1400', 'temp_1700', 'temp_2000', 'temp_2300', 'temp_0200', 'temp_0500'];
        foreach ($tempFields as $temp) {
            $data[$temp] = $data[$temp] ?? null;
        }
        
        // Clear any existing deviation flag to ensure we only check the current time slot
        session()->forget('deviation_triggered');
        session()->forget('deviation_data');

        return $data;
    }
    protected function afterCreate()
    {
        $temperatureHumidity = $this->record;
        $location = $temperatureHumidity->location;
        $room = $temperatureHumidity->room;

        // Skip deviation detection for Super Admin (bypasses time windows)
        if (!auth()->user()->hasRole('Super Admin')) {
            $roomTemp = $temperatureHumidity->roomTemperature;
            $minTemp = $roomTemp->temperature_start;
            $maxTemp = $roomTemp->temperature_end;
            $now = Carbon::now('Asia/Jakarta');

            $timeSlots = [
                'temp_0200' => ['time_field' => 'time_0200', 'start' => '02:00:00', 'end' => '02:30:59'],
                'temp_0500' => ['time_field' => 'time_0500', 'start' => '05:00:00', 'end' => '05:30:59'],
                'temp_0800' => ['time_field' => 'time_0800', 'start' => '08:00:00', 'end' => '08:30:59'],
                'temp_1100' => ['time_field' => 'time_1100', 'start' => '11:00:00', 'end' => '11:30:59'],
                'temp_1400' => ['time_field' => 'time_1400', 'start' => '14:00:00', 'end' => '14:30:59'],
                'temp_1700' => ['time_field' => 'time_1700', 'start' => '17:00:00', 'end' => '17:30:59'],
                'temp_2000' => ['time_field' => 'time_2000', 'start' => '20:00:00', 'end' => '20:30:59'],
                'temp_2300' => ['time_field' => 'time_2300', 'start' => '23:00:00', 'end' => '23:30:59'],
            ];

            // Check only the current time slot temperature
            foreach ($timeSlots as $tempField => $slotInfo) {
                $start = Carbon::createFromTimeString($slotInfo['start'], 'Asia/Jakarta');
                $end = Carbon::createFromTimeString($slotInfo['end'], 'Asia/Jakarta');

                if ($now->between($start, $end)) {
                    $tempValue = $temperatureHumidity->$tempField;
                    $inputtedTime = $temperatureHumidity->{$slotInfo['time_field']} ?? 'Unknown Time';

                    if (!is_null($tempValue) && ($tempValue < $minTemp || $tempValue > $maxTemp)) {
                        $deviationData = [[
                            'temperature_id' => $temperatureHumidity->id,
                            'location_id' => $temperatureHumidity->location_id,
                            'room_id' => $temperatureHumidity->room_id,
                            'room_temperature_id' => $temperatureHumidity->room_temperature_id,
                            'serial_number_id' => $temperatureHumidity->serial_number_id,
                            'time' => $inputtedTime,
                            'temperature_deviation' => $tempValue,
                        ]];

                        session()->put('deviation_triggered', true);
                        session()->put('deviation_data', $deviationData);
                    }
                    break; // Only check the current time slot
                }
            }
        }

        $recipient = auth()->user();
        Notification::make()
            ->success()
            ->title('New Temperature & Humidity for '.$room->room_name.' '. $location->location_name .' created')
            ->body('Please check the Temperature & Humidity page for more details.')
            ->actions([
                Action::make('View')
                    ->url(TemperatureHumidityResource::getUrl('view', ['record' => $temperatureHumidity]))
                    ->icon('heroicon-o-eye')
                    ->color('success')
                    ->markAsRead()
                    ->close()
            ])
            ->sendToDatabase($recipient);
    }
    protected function getRedirectUrl(): string
    {
        // Check if deviation was triggered
        if (session()->pull('deviation_triggered', false)) {
            // Default to false
            $deviations = session()->pull('deviation_data', []);
            return TemperatureDeviationResource::getUrl('create',[
                'temp_id' => $deviations[0]['temperature_id'],
                'location_id' => $deviations[0]['location_id'],
                'room_id' => $deviations[0]['room_id'],
                'room_temperature_id' => $deviations[0]['room_temperature_id'],
                'serial_number_id' => $deviations[0]['serial_number_id'],
                'time' => $deviations[0]['time'],
                'temperature_deviation' => $deviations[0]['temperature_deviation']
            ]); // Redirect to Deviation form
        }

        return $this->getResource()::getUrl('index'); // Default redirect after successful save
    }
    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Temperature & Humidity successfully created');
    }
}
