<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Resources;

use App\Filament\Support\NiveauScopeSelect;
use App\Modules\Socle\Contracts\ReunionServiceContract;
use App\Modules\Socle\Models\Reunion;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ReunionResource extends Resource
{
    protected static ?string $model = Reunion::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|\UnitEnum|null $navigationGroup = 'Module Complémentaire';

    protected static ?string $modelLabel = 'Réunion';

    protected static ?string $pluralModelLabel = 'Réunions';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('titre')
                    ->required(),
                Select::make('type')
                    ->options([
                        'conseil_classe' => 'Conseil de classe',
                        'pedagogique' => 'Pédagogique',
                        'administrative' => 'Administrative',
                        'parents' => 'Parents d\'élèves',
                        'discipline' => 'Discipline',
                    ])
                    ->required(),
                DateTimePicker::make('date_heure')
                    ->label('Date & Heure')
                    ->required(),
                TextInput::make('lieu')
                    ->placeholder('Ex: Salle du conseil'),
                NiveauScopeSelect::make(),
                Select::make('statut')
                    ->options([
                        'planifiee' => 'Planifiée',
                        'en_cours' => 'En cours',
                        'annulee' => 'Annulée',
                    ])
                    ->default('planifiee')
                    ->helperText('Le passage à "Terminée" se fait uniquement via l’action Clôturer, avec le compte rendu.')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('titre')->searchable()->sortable(),
                TextColumn::make('type')->badge()->color('info'),
                TextColumn::make('date_heure')->dateTime()->sortable(),
                TextColumn::make('lieu'),
                TextColumn::make('niveau.nom')->label('Niveau'),
                TextColumn::make('statut')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'planifiee' => 'warning',
                        'en_cours' => 'info',
                        'terminee' => 'success',
                        'annulee' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('compte_rendu')->label('Compte rendu')->limit(50)->toggleable(),
            ])
            ->filters([
                SelectFilter::make('statut')
                    ->options([
                        'planifiee' => 'Planifiée',
                        'en_cours' => 'En cours',
                        'terminee' => 'Terminée',
                        'annulee' => 'Annulée',
                    ]),
                SelectFilter::make('type')
                    ->options([
                        'conseil_classe' => 'Conseil de classe',
                        'pedagogique' => 'Pédagogique',
                        'administrative' => 'Administrative',
                        'parents' => 'Parents d\'élèves',
                        'discipline' => 'Discipline',
                    ]),
            ])
            ->recordActions([
                Action::make('cloturer')
                    ->label('Clôturer')
                    ->icon('heroicon-o-flag')
                    ->color('success')
                    ->schema([
                        Textarea::make('compte_rendu')
                            ->label('Compte rendu')
                            ->required(),
                    ])
                    ->visible(fn (Reunion $record): bool => ! in_array($record->statut, ['terminee', 'annulee'], true))
                    ->action(fn (Reunion $record, array $data) => app(ReunionServiceContract::class)
                        ->cloturerReunion($record->id, $data['compte_rendu'])),
                EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ReunionResource\RelationManagers\DecisionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ReunionResource\Pages\ListReunions::route('/'),
            'create' => ReunionResource\Pages\CreateReunion::route('/create'),
            'edit' => ReunionResource\Pages\EditReunion::route('/{record}/edit'),
        ];
    }
}
