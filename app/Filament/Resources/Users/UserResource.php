<?php

namespace App\Filament\Resources\Users;

use Filament\Schemas\Schema;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\BulkAction;
use Filament\Forms;
use App\Models\User;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\UserResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\Users\Pages\ListUsers;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?int $navigationSort = 1;
    protected static string | \UnitEnum | null $navigationGroup = 'Admin Settings';
    public static function canViewAny(): bool
    {
        return Auth::user()->hasRole(['Super Admin', 'Admin']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Name')
                    ->required(),
                TextInput::make('initial')
                    ->label('Initial')
                    ->required()
                    ->maxLength(3),
                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required(),
                Select::make('location_id')
                    ->label('Location')
                    ->relationship('location', 'location_name')
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->helperText('Assign user to a specific location. Leave empty for admin users who can access all locations.'),
                Select::make('roles')->relationship('roles', 'name')
                    ->columnSpanFull(),
                Toggle::make('password_change_required')
                    ->label('Require Password Change')
                    ->default(true)
                    ->helperText('User will be forced to change password on first login')
                    ->visible(fn (string $operation): bool => $operation === 'create'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->header(view('filament.tables.top-bottom-pagination-tables', [
                'table' => $table,
            ]))
            ->modifyQueryUsing(function ($query) {
                $query->where('id', '!=', 1)->orderByDesc('id');
            })
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('username')
                    ->searchable()
                    ->label('Username'),
                TextColumn::make('initial')
                    ->searchable(),
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('location.location_name')
                    ->label('Location')
                    ->searchable()
                    ->sortable()
                    ->placeholder('All Locations'),
                TextColumn::make('roles.name')
                    ->searchable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
                IconColumn::make('password_change_required')
                    ->label('First Pass Change?')
                    ->boolean()
                    ->trueIcon('heroicon-o-x-circle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('danger')
                    ->falseColor('success'),
            ])
            ->filters([
                SelectFilter::make('roles')
                    ->relationship('roles', 'name', fn (Builder $query) => $query->where('id', '!=', 1)),
                TernaryFilter::make('is_active')
                    ->label('User Status')
                    ->placeholder('All users')
                    ->trueLabel('Active')
                    ->falseLabel('Inactive'),
                TernaryFilter::make('password_change_required')
                    ->label('First Time Password Change')
                    ->placeholder('All users')
                    ->trueLabel('Required')
                    ->falseLabel('Not Required'),
            ])
            ->recordActions([
                Action::make('resetPassword')
                    ->label('Reset Password')
                    ->action(function (User $record) {
                        $record->update([
                            'password' => Hash::make('Scm2025!'),
                            'password_change_required' => true,
                        ]);
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Reset Password')
                    ->modalDescription('This will reset the password to "Scm2025!" and require the user to change it on next login.')
                    ->color('warning')
                    ->icon('heroicon-o-key'),
                Action::make('generateUsername')
                    ->label('Generate Username')
                    ->action(fn (User $record) => $record->update(['username' => substr(strtolower(str_replace(' ', '.', $record->name)), 0, 8)]))
                    ->requiresConfirmation()
                    ->color('success')
                    ->icon('heroicon-o-user-circle')
                    ->visible(fn (User $record) => empty($record->username) || auth()->user()->hasRole('Super Admin')),
                Action::make('forcePasswordChange')
                    ->label('Force Password Change')
                    ->action(fn (User $record) => $record->update(['password_change_required' => true]))
                    ->requiresConfirmation()
                    ->color('warning')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->visible(fn (User $record) => !$record->password_change_required),
                Action::make('toggleActive')
                    ->label(fn (User $record): string => $record->is_active ? 'Deactivate' : 'Activate')
                    ->action(fn (User $record) => $record->update(['is_active' => !$record->is_active]))
                    ->requiresConfirmation()
                    ->color(fn (User $record): string => $record->is_active ? 'danger' : 'success')
                    ->icon(fn (User $record): string => $record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle'),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('activate')
                        ->label('Activate Users')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(function ($records) {
                            $records->each(fn ($record) => $record->update(['is_active' => true]));
                        })
                        ->requiresConfirmation(),
                    BulkAction::make('deactivate')
                        ->label('Deactivate Users')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(function ($records) {
                            $records->each(fn ($record) => $record->update(['is_active' => false]));
                        })
                        ->requiresConfirmation(),
                    BulkAction::make('force_password_change')
                        ->label('Force Password Change')
                        ->icon('heroicon-o-exclamation-triangle')
                        ->color('warning')
                        ->action(function ($records) {
                            $records->each(fn ($record) => $record->update(['password_change_required' => true]));
                        })
                        ->requiresConfirmation(),
                    BulkAction::make('generate_username')
                        ->label('Generate Username')
                        ->icon('heroicon-o-user-circle')
                        ->color('success')
                        ->action(function ($records) {
                            $records->each(fn ($record) => $record->update(['username' => substr(strtolower(str_replace(' ', '.', $record->name)), 0, 8)]));
                        })
                        ->requiresConfirmation(),
                    BulkAction::make('assignLocationBP1')
                        ->label('Assign to BizPark 1')
                        ->icon('heroicon-o-map')
                        ->color('primary')
                        ->action(function ($records) {
                            $records->each(fn ($record) => $record->update(['location_id' => 1]));
                        })
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('assignLocationBP2')
                        ->label('Assign to BizPark 2')
                        ->icon('heroicon-o-map')
                        ->color('primary')
                        ->action(function ($records) {
                            $records->each(fn ($record) => $record->update(['location_id' => 2]));
                        })
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion(),
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
            'index' => ListUsers::route('/'),
        ];
    }
}
