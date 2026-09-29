<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Resources\ReunionResource\RelationManagers;

use App\Models\User;
use App\Modules\Socle\Contracts\ReunionServiceContract;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

class DecisionsRelationManager extends RelationManager
{
    protected static string $relationship = 'decisions';

    protected static ?string $title = 'Décisions';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('description')->limit(60),
                TextColumn::make('responsable.name')->label('Responsable'),
                TextColumn::make('echeance')->date(),
                TextColumn::make('statut')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'validee' => 'success',
                        'rejetee' => 'danger',
                        'en_attente_validation' => 'warning',
                        'en_cours' => 'info',
                        'cloturee' => 'gray',
                        default => 'gray',
                    }),
            ])
            ->headerActions([
                Action::make('ajouterDecision')
                    ->label('Ajouter une décision')
                    ->icon('heroicon-o-plus')
                    ->schema([
                        Textarea::make('description')
                            ->required(),
                        Select::make('responsable_id')
                            ->label('Responsable')
                            ->options(User::pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                        DatePicker::make('echeance')
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        app(ReunionServiceContract::class)->ajouterDecision(
                            $this->getOwnerRecord()->id,
                            $data['description'],
                            (int) $data['responsable_id'],
                            Carbon::parse($data['echeance']),
                        );
                    }),
            ]);
    }
}
