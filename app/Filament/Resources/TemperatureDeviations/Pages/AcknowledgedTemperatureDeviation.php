<?php

namespace App\Filament\Resources\TemperatureDeviations\Pages;

use App\Filament\Resources\TemperatureDeviations\TemperatureDeviationResource;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AcknowledgedTemperatureDeviation extends ListRecords
{
    protected static string $resource = TemperatureDeviationResource::class;

    protected static ?string $title = 'Pending Acknowledgement';

    public function getBreadcrumb(): string
    {
        return 'Pending Acknowledgement'; // or any label you want
    }

    public function table(Table $table): Table
    {
        return $table
            ->header(view('filament.tables.top-bottom-pagination-tables', [
                'table' => $table,
            ]))
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['location', 'room', 'roomTemperature'])->orderByDesc('date')->where('is_acknowledged', false)->whereNotNull('length_temperature_deviation')->whereNotNull('risk_analysis'))
            ->emptyStateHeading('No pending acknowledge data is found')
            ->columns([
                TextColumn::make('date')
                    ->label('Date (Tanggal)')
                    ->sortable()
                    ->searchable()
                    ->date('d/m/Y'),
                TextColumn::make('location_id')
                    ->label('Location / Room / Temp')
                    ->getStateUsing(function ($record) {
                        return $record->location->location_name.'<br>'.
                               $record->room->room_name.'<br>'.
                               $record->roomTemperature->temperature_start.'°C to '.$record->roomTemperature->temperature_end.'°C';
                    })
                    ->html(),
                TextColumn::make('time')
                    ->label('Time (Jam)')
                    ->sortable()
                    ->searchable()
                    ->time('H:i'),
                TextColumn::make('temperature_deviation')
                    ->label('Temperature Deviation (°C)'),
                TextColumn::make('deviation_reason')
                    ->label('Reason for Deviation'),
                TextColumn::make('length_temperature_deviation')
                    ->label('Length of Temperature Deviation (Menit/Jam)'),
                TextColumn::make('pic')
                    ->label('PIC (SCM)'),
                TextColumn::make('risk_analysis')
                    ->label('Risk Analysis of impact deviation'),
                TextColumn::make('analyzer_pic')
                    ->label('Analyzed by (QA)'),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('is_acknowledged')
                    ->label('Mark as Acknowledged')
                    ->visible(function () {
                        $admin = Auth::user()->hasRole('QA Manager');

                        return $admin;
                    })
                    ->action(function (Model $record) {
                        $record->update([
                            'is_acknowledged' => true,
                            'acknowledged_by' => auth()->user()->initial.' '.strtoupper(now('Asia/Jakarta')->format('d M Y')),
                            'acknowledged_at' => now('Asia/Jakarta'),
                        ]);
                        Notification::make()
                            ->title('Success!')
                            ->body('Marked as acknowledged successfully')
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->color('info')
                    ->icon('heroicon-o-check'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('is_acknowledged')
                        ->label('Mark as Acknowledged')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(function () {
                            return Auth::user()->hasRole('QA Manager');
                        })
                        ->action(function (Collection $records) {
                            foreach ($records as $record) {
                                $record->is_acknowledged = true;
                                $record->acknowledged_by = auth()->user()->initial.' '.strtoupper(now('Asia/Jakarta')->format('d M Y'));
                                $record->acknowledged_at = now('Asia/Jakarta');
                                $record->save();
                            }

                            Notification::make()
                                ->title('Success!')
                                ->body('Selected data marked as acknowledged successfully')
                                ->success()
                                ->send();
                        }),
                ]),
            ]);
    }
}
