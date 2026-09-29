<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Resources;

use App\Filament\Support\AmbassadorsDesign;
use App\Models\User;
use App\Modules\Socle\Contracts\TacheServiceContract;
use App\Modules\Socle\Models\Tache;
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
use Illuminate\Support\Facades\Auth;

class TacheResource extends Resource
{
    protected static ?string $model = Tache::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-check-circle';

    protected static string|\UnitEnum|null $navigationGroup = 'Module Complémentaire';

    protected static ?string $modelLabel = 'Tâche & Validation';

    protected static ?string $pluralModelLabel = 'Tâches & Validations';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('titre')
                    ->required(),
                Textarea::make('description'),
                Select::make('responsable_id')
                    ->label('Responsable')
                    ->options(User::pluck('name', 'id'))
                    ->searchable()
                    ->required(),
                DateTimePicker::make('echeance')
                    ->label('Échéance')
                    ->required(),
                Select::make('priorite')
                    ->options([
                        'basse' => 'Basse',
                        'normale' => 'Normale',
                        'haute' => 'Haute',
                        'urgente' => 'Urgente',
                    ])
                    ->default('normale')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('titre')->searchable()->sortable(),
                TextColumn::make('responsable.name')->label('Responsable')->searchable(),
                TextColumn::make('priorite')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'urgente' => 'danger',
                        'haute' => 'warning',
                        'normale' => 'info',
                        'basse' => 'gray',
                        default => 'gray',
                    }),
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
                TextColumn::make('echeance')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('statut')
                    ->options([
                        'a_faire' => 'À faire',
                        'en_cours' => 'En cours',
                        'en_attente_validation' => 'En attente validation',
                        'validee' => 'Validée',
                        'rejetee' => 'Rejetée',
                        'cloturee' => 'Clôturée',
                    ]),
                SelectFilter::make('priorite')
                    ->options([
                        'basse' => 'Basse',
                        'normale' => 'Normale',
                        'haute' => 'Haute',
                        'urgente' => 'Urgente',
                    ]),
                SelectFilter::make('responsable_id')
                    ->label('Responsable')
                    ->relationship('responsable', 'name')
                    ->searchable(),
            ])
            ->recordActions([
                Action::make('demarrer')
                    ->label('Démarrer')
                    ->icon('heroicon-o-play')
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn (Tache $record): bool => $record->estAuStatut('a_faire'))
                    ->action(fn (Tache $record) => $record->changerStatut('en_cours', Auth::user())),
                Action::make('soumettre_validation')
                    ->label('Soumettre à validation')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('warning')
                    ->schema([
                        Select::make('validateur_ids')
                            ->label('Validateurs (dans l’ordre)')
                            ->options(User::pluck('name', 'id'))
                            ->multiple()
                            ->required(),
                    ])
                    ->visible(fn (Tache $record): bool => $record->estAuStatut('en_cours'))
                    ->action(fn (Tache $record, array $data) => app(TacheServiceContract::class)
                        ->demarrerCircuitValidation($record->id, $data['validateur_ids'])),
                Action::make('valider_etape')
                    ->label('Valider mon étape')
                    ->icon('heroicon-o-check-circle')
                    ->color(AmbassadorsDesign::VALIDATION_COLOR)
                    ->schema([
                        Textarea::make('commentaire')->label('Commentaire'),
                    ])
                    ->visible(fn (Tache $record): bool => $record->estAuStatut('en_attente_validation')
                        && Auth::id() !== null
                        && (int) $record->etapeSuivanteAValider()?->validateur_id === (int) Auth::id())
                    ->action(fn (Tache $record, array $data) => app(TacheServiceContract::class)
                        ->validerEtape($record->id, (int) Auth::id(), true, $data['commentaire'] ?? null)),
                Action::make('rejeter_etape')
                    ->label('Rejeter mon étape')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->schema([
                        Textarea::make('commentaire')->label('Motif du rejet'),
                    ])
                    ->visible(fn (Tache $record): bool => $record->estAuStatut('en_attente_validation')
                        && Auth::id() !== null
                        && (int) $record->etapeSuivanteAValider()?->validateur_id === (int) Auth::id())
                    ->action(fn (Tache $record, array $data) => app(TacheServiceContract::class)
                        ->validerEtape($record->id, (int) Auth::id(), false, $data['commentaire'] ?? null)),
                Action::make('reprendre')
                    ->label('Reprendre')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn (Tache $record): bool => $record->estAuStatut('rejetee'))
                    ->action(fn (Tache $record) => $record->changerStatut('en_cours', Auth::user())),
                Action::make('cloturer')
                    ->label('Clôturer')
                    ->icon('heroicon-o-archive-box')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (Tache $record): bool => $record->estAuStatut('en_cours') || $record->estAuStatut('validee'))
                    ->action(fn (Tache $record) => $record->changerStatut('cloturee', Auth::user())),
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => TacheResource\Pages\ListTaches::route('/'),
            'create' => TacheResource\Pages\CreateTache::route('/create'),
            'edit' => TacheResource\Pages\EditTache::route('/{record}/edit'),
        ];
    }
}
