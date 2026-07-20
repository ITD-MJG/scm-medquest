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

class ReviewedTemperatureDeviation extends ListRecords
{
    protected static string $resource = TemperatureDeviationResource::class;

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
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['location', 'room', 'roomTemperature'])->orderByDesc('date')->where('is_reviewed', false)->whereNotNull('length_temperature_deviation')->whereNotNull('risk_analysis'))
            ->emptyStateHeading('No pending review data is found')
            ->columns([
                TextColumn::make('date')
                    ->label('Date (Tanggal)')
                    ->sortable()
                    ->searchable()
                    ->date('d/m/Y'),
                TextColumn::make('location_id')
                ->label('Location / Room / Temp')
                    ->getStateUsing(function ($record) {
                        return $record->location->location_name . '<br>' .
                               $record->room->room_name . '<br>' .
                               $record->roomTemperature->temperature_start . '°C to ' . $record->roomTemperature->temperature_end . '°C';
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
                Action::make('is_reviewed')
                    ->label('Mark as Reviewed')
                    ->visible(function () {
                        $admin = Auth::user()->hasRole('Supply Chain Manager');

                        return $admin;
                    })
                    ->action(function (Model $record) {
                        $record->update([
                            'is_reviewed' => true,
                            'reviewed_by' => auth()->user()->initial.' '.strtoupper(now('Asia/Jakarta')->format('d M Y')),
                            'reviewed_at' => now('Asia/Jakarta'),
                        ]);
                        Notification::make()
                            ->title('Success!')
                            ->body('Marked as reviewed successfully')
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->color('success')
                    ->icon('heroicon-o-check'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('is_reviewed')
                        ->label('Mark as Reviewed')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(function () {
                            $admin = Auth::user()->hasRole('Supply Chain Manager');

                            return $admin;
                        })
                        ->action(function (Collection $records) {
                            foreach ($records as $record) {
                                $record->is_reviewed = true;
                                $record->reviewed_by = auth()->user()->initial.' '.strtoupper(now('Asia/Jakarta')->format('d M Y'));
                                $record->reviewed_at = now('Asia/Jakarta');
                                $record->save();
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
