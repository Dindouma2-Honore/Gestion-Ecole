<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Filament\Resources;

use App\Models\User;
use App\Modules\Logistique\Contracts\InfrastructureServiceInterface;
use App\Modules\Pedagogie\Filament\Resources\EmploiDuTempsResource\Pages;
use App\Modules\Pedagogie\Models\EmploiDuTemps;
use App\Modules\Pedagogie\Models\Matiere;
use App\Modules\Scolarite\Contracts\ClasseServiceInterface;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class EmploiDuTempsResource extends Resource
{
    protected static ?string $model = EmploiDuTemps::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|UnitEnum|null $navigationGroup = 'Pédagogie';

    protected static ?string $navigationLabel = 'Emplois du temps';

    protected static ?string $modelLabel = 'cours planifié';

    protected static ?string $pluralModelLabel = 'emplois du temps';

    /**
     * La détection de conflit (enseignant/salle/classe déjà occupés sur le
     * créneau) vit dans EmploiDuTempsServiceInterface — jamais recalculée ici.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('annee_scolaire_id')
                    ->label('Année scolaire')
                    ->options(fn () => collect(app(AnneeScolaireServiceContract::class)->getToutesLesAnnees())
                        ->mapWithKeys(fn ($a) => [data_get($a, 'id') => data_get($a, 'libelle', data_get($a, 'nom', '#'.data_get($a, 'id')))]))
                    ->live()
                    ->default(fn () => app(AnneeScolaireServiceContract::class)->getAnneeCouranteId())
                    ->searchable()
                    ->required(),

                Select::make('classe_id')
                    ->label('Classe')
                    ->options(function (Get $get) {
                        $anneeScolaireId = (int) ($get('annee_scolaire_id')
                            ?: app(AnneeScolaireServiceContract::class)->getAnneeCouranteId());

                        return collect(app(ClasseServiceInterface::class)->getToutesLesClasses($anneeScolaireId))
                            ->pluck('nom', 'id');
                    })
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set, Get $get) {
                        if ($state) {
                            $anneeId = (int) ($get('annee_scolaire_id') ?: app(AnneeScolaireServiceContract::class)->getAnneeCouranteId());
                            $matiereId = $get('matiere_id') ? (int) $get('matiere_id') : null;
                            $suggere = app(\App\Modules\Pedagogie\Contracts\EmploiDuTempsServiceInterface::class)
                                ->getEnseignantSuggere((int) $state, $matiereId, $anneeId);
                            if ($suggere) {
                                $set('enseignant_id', $suggere);
                            }
                        }
                    })
                    ->searchable()
                    ->required(),

                Select::make('matiere_id')
                    ->label('Matière / Domaine d\'activité')
                    ->options(fn () => Matiere::where('actif', true)->pluck('nom', 'id'))
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set, Get $get) {
                        $classeId = $get('classe_id') ? (int) $get('classe_id') : null;
                        if ($classeId) {
                            $anneeId = (int) ($get('annee_scolaire_id') ?: app(AnneeScolaireServiceContract::class)->getAnneeCouranteId());
                            $suggere = app(\App\Modules\Pedagogie\Contracts\EmploiDuTempsServiceInterface::class)
                                ->getEnseignantSuggere($classeId, $state ? (int) $state : null, $anneeId);
                            if ($suggere) {
                                $set('enseignant_id', $suggere);
                            }
                        }
                    }),

                \Filament\Forms\Components\TextInput::make('libelle_activite')
                    ->label('Libellé de l\'activité (si sans matière formelle)')
                    ->placeholder('Ex: Accueil, Sieste, Motricité')
                    ->visible(fn (Get $get) => empty($get('matiere_id'))),

                Select::make('enseignant_id')
                    ->label('Enseignant')
                    ->options(fn () => DB::table('enseignants')->join('employes', 'employes.id', '=', 'enseignants.employe_id')->orderBy('employes.nom')->selectRaw("CONCAT(employes.nom, ' ', employes.prenom) as nom, enseignants.id")->pluck('nom', 'enseignants.id'))
                    ->searchable()
                    ->required(),

                Select::make('salle_id')
                    ->label('Salle')
                    ->options(fn () => collect(app(InfrastructureServiceInterface::class)->getSallesDisponibles())
                        ->pluck('nom', 'id'))
                    ->searchable()
                    ->required(),

                Select::make('creneau_id')
                    ->label('Créneau')
                    ->relationship('creneau', 'id')
                    ->getOptionLabelFromRecordUsing(
                        fn ($record) => "Jour {$record->jour_semaine} — {$record->heure_debut} à {$record->heure_fin}"
                    )
                    ->required(),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('matiere.nom')
                    ->label('Matière / Activité')
                    ->formatStateUsing(fn ($record) => $record->matiere?->nom ?? $record->libelle_activite ?? 'Activité générale')
                    ->searchable(),
                Tables\Columns\TextColumn::make('classe_id')
                    ->label('Classe')
                    ->formatStateUsing(fn ($state) => rescue(fn () => app(ClasseServiceInterface::class)->getNomClasse((int) $state), "#{$state}")),
                Tables\Columns\TextColumn::make('enseignant_id')
                    ->label('Enseignant')
                    ->formatStateUsing(fn ($state) => User::find($state)?->name ?? "#{$state}"),
                Tables\Columns\TextColumn::make('creneau.jour_semaine')->label('Jour'),
                Tables\Columns\TextColumn::make('creneau.heure_debut')->label('Début'),
                Tables\Columns\TextColumn::make('creneau.heure_fin')->label('Fin'),
                Tables\Columns\IconColumn::make('actif')->label('Actif')->boolean(),
                Tables\Columns\TextColumn::make('statut')->label('État du planning')->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'preparation', 'brouillon' => 'En préparation',
                        'genere' => 'Généré', 'ajuste' => 'Ajusté', 'valide' => 'Validé',
                        'publie' => 'Publié', 'archive' => 'Archivé', default => (string) $state,
                    })
                    ->color(fn (?string $state) => match ($state) {
                        'publie' => 'success', 'valide' => 'info', 'archive' => 'gray', 'ajuste' => 'warning', default => 'gray'
                    }),
            ])
            ->actions([
                EditAction::make()->label('Modifier')
                    ->visible(fn (EmploiDuTemps $record): bool => ! in_array($record->statut, ['valide', 'publie'], true)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmploisDuTemps::route('/'),
            'create' => Pages\CreateEmploiDuTemps::route('/create'),
            'edit' => Pages\EditEmploiDuTemps::route('/{record}/edit'),
        ];
    }
}
