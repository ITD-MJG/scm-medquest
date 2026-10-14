<?php

namespace App\Filament\Resources\ApprovalFlows;

use App\Models\ApprovalFlow;
use App\Models\ApprovalFlowStep;
use Filament\Forms\Components\Repeater;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

class ApprovalFlowResource extends Resource
{
    protected static ?string $model = ApprovalFlow::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-check-badge';

    protected static string|\UnitEnum|null $navigationGroup = 'System Administration';

    protected static ?string $navigationLabel = 'Approval Flows';

    protected static ?int $navigationSort = 4;

    public static function canViewAny(): bool
    {
        return Auth::user()->hasRole(['Super Admin', 'Admin']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Flow')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        Select::make('model_type')
                            ->label('Document Type')
                            ->options(self::documentTypes())
                            ->required()
                            ->unique(ignoreRecord: true),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->inline(false)
                            ->default(false)
                            ->helperText('Only one flow per document type can be active. Activating this flow retires the others.'),
                    ])
                    ->columns(2),

                Section::make('Approval Steps')
                    ->description('Ordered from first to last. Each step must be signed in sequence by a user holding the role (or a Super Admin).')
                    ->schema([
                        Repeater::make('steps')
                            ->relationship('steps')
                            ->label('')
                            ->orderColumn('sequence')
                            ->reorderable()
                            ->collapsible()
                            ->defaultItems(0)
                            ->schema([
                                TextInput::make('label')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(1),

                                Select::make('role_name')
                                    ->label('Required Role')
                                    ->options(fn (): array => Role::query()->orderBy('name')->pluck('name', 'name')->all())
                                    ->required()
                                    ->columnSpan(1),
                            ])
                            ->columns(2)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('model_type')
                    ->label('Document Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::documentTypes()[$state] ?? $state)
                    ->sortable(),

                TextColumn::make('steps_count')
                    ->counts('steps')
                    ->label('Steps')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('name');
    }

    /**
     * The closed set of document types that can carry approvals.
     *
     * @return array<string, string>
     */
    public static function documentTypes(): array
    {
        return [
            'incoming_receipt' => 'Incoming Receipt',
            'incoming_receipt_grifols' => 'Incoming Receipt (Grifols)',
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListApprovalFlows::route('/'),
            'create' => Pages\CreateApprovalFlow::route('/create'),
            'edit' => Pages\EditApprovalFlow::route('/{record}/edit'),
        ];
    }
}
