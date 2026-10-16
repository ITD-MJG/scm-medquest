<?php

namespace App\Filament\Resources\ApprovalFlows\RelationManagers;

use App\Models\ApprovalFlowStep;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;

class ApprovalFlowStepsRelationManager extends RelationManager
{
    protected static string $relationship = 'steps';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('label')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                TextInput::make('sequence')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->default(fn (): int => $this->nextSequence()),

                Select::make('role_name')
                    ->label('Required Role')
                    ->options(fn (): array => Role::query()->orderBy('name')->pluck('name', 'name')->all())
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->columns([
                TextColumn::make('sequence')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('label')
                    ->searchable(),

                TextColumn::make('role_name')
                    ->label('Required Role')
                    ->badge()
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): ApprovalFlowStep {
                        $data['sequence'] ??= $this->nextSequence();

                        return $this->getRelationship()->create($data);
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sequence');
    }

    private function nextSequence(): int
    {
        return (int) $this->getOwnerRecord()->steps()->max('sequence') + 1;
    }
}
