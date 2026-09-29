<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Filament\Resources;

use App\Modules\Pedagogie\Contracts\ProgrammeServiceInterface;
use App\Modules\Pedagogie\Contracts\ProgressionServiceInterface;
use App\Modules\Pedagogie\Models\Matiere;
use App\Modules\Pedagogie\Models\Progression;
use App\Modules\Pedagogie\Models\Seance;
use App\Modules\Scolarite\Contracts\ClasseServiceInterface;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class ProgressionResource extends Resource
{
    protected static ?string $model = Progression::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static string|UnitEnum|null $navigationGroup = 'Pédagogie';

    protected static ?string $navigationLabel = 'Progression pédagogique';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('seance_id')
                    ->label('Séance')
                    ->relationship('seance', 'id')
                    ->getOptionLabelFromRecordUsing(
                        fn ($record) => ($record->emploiDuTemps?->matiere?->nom ?? 'Matière ?')
                            . ' — ' . optional($record->date_seance)->format('d/m/Y')
                    )
                    ->live()
                    ->searchable()
                    ->required(),

                // Ne propose que les chapitres du programme (validé) réellement
                // prévus pour la matière/classe/année de la séance sélectionnée
                // — l'enseignant renseigne ainsi le chapitre du programme qu'il
                // vient de couvrir, et non une liste de chapitres de toutes les
                // matières/classes confondues.
                Select::make('chapitre_id')
                    ->label('Chapitre du programme couvert')
                    ->options(function (Get $get) {
                        $seance = Seance::with('emploiDuTemps')->find($get('seance_id'));
                        if ($seance === null || $seance->emploiDuTemps === null) {
                            return [];
                        }

                        $emploi = $seance->emploiDuTemps;

                        return collect(app(ProgrammeServiceInterface::class)->getChapitresPrevus(
                            $emploi->matiere_id,
                            $emploi->classe_id,
                            $emploi->annee_scolaire_id,
                            $emploi->enseignant_id
                        ))->pluck('titre', 'id');
                    })
                    ->helperText('Liste limitée aux chapitres prévus par le programme validé de cette matière/classe.')
                    ->searchable(),

                Textarea::make('contenu_couvert')
                    ->label('Contenu couvert')
                    ->required(),

                Textarea::make('devoirs_donnes')
                    ->label('Devoirs donnés'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('seance.emploiDuTemps.matiere.nom')
                    ->label('Séance')
                    ->formatStateUsing(fn ($state, $record) => $record->seance
                        ? $state . ' — ' . optional($record->seance->date_seance)->format('d/m/Y')
                        : '— (déclaration hors séance)'),

                Tables\Columns\TextColumn::make('chapitre.titre')
                    ->label('Chapitre'),

                Tables\Columns\TextColumn::make('contenu_couvert')
                    ->label('Contenu')
                    ->limit(50),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Saisi le')
                    ->dateTime(),
            ])
            ->headerActions([
                // Déclaration rapide, indépendante d'une séance précise : sert
                // à renseigner les chapitres du programme déjà couverts par
                // l'enseignant (rattrapage, reprise en cours d'année, etc.).
                Action::make('declarerChapitreCouvert')
                    ->label('Déclarer un chapitre déjà couvert')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->modalHeading('Déclarer un chapitre du programme comme déjà couvert')
                    ->modalSubmitActionLabel('Déclarer comme couvert')
                    ->form([
                        Select::make('matiere_id')
                            ->label('Matière')
                            ->options(Matiere::query()->where('actif', true)->orderBy('nom')->pluck('nom', 'id'))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->required(),

                        Select::make('annee_scolaire_id')
                            ->label('Année scolaire')
                            ->options(fn () => collect(app(AnneeScolaireServiceContract::class)->getToutesLesAnnees())
                                ->mapWithKeys(fn ($a) => [data_get($a, 'id') => data_get($a, 'libelle', data_get($a, 'nom', '#' . data_get($a, 'id')))]))
                            ->live()
                            ->default(fn () => app(AnneeScolaireServiceContract::class)->getAnneeCouranteId())
                            ->required(),

                        Select::make('classe_id')
                            ->label('Classe')
                            ->options(function (Get $get) {
                                $anneeScolaireId = (int) ($get('annee_scolaire_id')
                                    ?: app(AnneeScolaireServiceContract::class)->getAnneeCouranteId());

                                return collect(app(ClasseServiceInterface::class)->getToutesLesClasses($anneeScolaireId))
                                    ->pluck('nom', 'id');
                            })
                            ->searchable()
                            ->live()
                            ->required(),

                        Select::make('chapitre_id')
                            ->label('Chapitre déjà couvert')
                            ->options(function (Get $get) {
                                $matiereId = (int) $get('matiere_id');
                                $classeId = (int) $get('classe_id');
                                $anneeScolaireId = (int) ($get('annee_scolaire_id')
                                    ?: app(AnneeScolaireServiceContract::class)->getAnneeCouranteId());

                                if (! $matiereId || ! $classeId) {
                                    return [];
                                }

                                return collect(app(ProgrammeServiceInterface::class)->getChapitresPrevus(
                                    $matiereId,
                                    $classeId,
                                    $anneeScolaireId,
                                    Auth::id()
                                ))->pluck('titre', 'id');
                            })
                            ->searchable()
                            ->required(),

                        Textarea::make('commentaire')
                            ->label('Commentaire (optionnel)')
                            ->rows(2),
                    ])
                    ->action(function (array $data): void {
                        $enseignantId = Auth::id();

                        if ($enseignantId === null) {
                            Notification::make()
                                ->title('Utilisateur non authentifié')
                                ->body('Vous devez être connecté pour déclarer un chapitre couvert.')
                                ->danger()
                                ->persistent()
                                ->send();

                            return;
                        }

                        try {
                            app(ProgressionServiceInterface::class)->declarerChapitreCouvert(
                                $enseignantId,
                                (int) $data['chapitre_id'],
                                $data['commentaire'] ?? null,
                            );

                            $avancement = app(ProgressionServiceInterface::class)->getPourcentageAvancement(
                                (int) $data['matiere_id'],
                                (int) $data['classe_id'],
                                (int) $data['annee_scolaire_id'],
                            );

                            Notification::make()
                                ->title('Chapitre déclaré comme couvert')
                                ->body("Avancement du programme pour cette matière/classe : {$avancement}%.")
                                ->success()
                                ->duration(6000)
                                ->send();
                        } catch (\Throwable $e) {
                            report($e);

                            Notification::make()
                                ->title('Échec de la déclaration')
                                ->body('Erreur : ' . $e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ProgressionResource\Pages\ListProgressions::route('/'),
            'create' => ProgressionResource\Pages\CreateProgression::route('/create'),
            'edit' => ProgressionResource\Pages\EditProgression::route('/{record}/edit'),
        ];
    }
}
