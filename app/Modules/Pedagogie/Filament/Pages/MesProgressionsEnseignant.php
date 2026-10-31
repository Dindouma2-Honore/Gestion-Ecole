<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Filament\Pages;

use App\Modules\Pedagogie\Contracts\ProgressionServiceInterface;
use App\Modules\Pedagogie\Models\OffrePedagogique;
use App\Modules\Pedagogie\Models\Programme;
use App\Modules\Pedagogie\Models\ProgrammeChapitre;
use App\Modules\Pedagogie\Models\ProgrammeLecon;
use App\Modules\Pedagogie\Models\Progression;
use App\Modules\Pedagogie\Contracts\ProgrammeServiceInterface;
use App\Modules\Pedagogie\Services\ProgressionCalculator;
use App\Modules\RH\Contracts\EnseignantServiceInterface;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

class MesProgressionsEnseignant extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'pedagogie/mes-progressions';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Progression par classe';

    protected string $view = 'pedagogie::filament.pages.mes-progressions-enseignant';

    public ?int $anneeScolaireId = null;

    public ?int $cycleId = null;

    public ?int $niveauId = null;

    public ?int $classeId = null;

    public ?int $enseignantFiltreId = null;

    public ?int $matiereId = null;

    public ?string $etatProgression = null;

    public function mount(): void
    {
        $this->anneeScolaireId = app(AnneeScolaireServiceContract::class)->getAnneeCouranteId();
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();
        if ($user === null) {
            return false;
        }

        return static::estProfilAdministratif()
            || app(EnseignantServiceInterface::class)->getEnseignantIdPourUtilisateur((int) $user->id) !== null;
    }

    public function updatedAnneeScolaireId(): void
    {
        $this->cycleId = $this->niveauId = $this->classeId = null;
        $this->resetTable();
    }

    public function updatedCycleId(): void
    {
        $this->niveauId = $this->classeId = null;
        $this->resetTable();
    }

    public function updatedNiveauId(): void
    {
        $this->classeId = null;
        $this->resetTable();
    }

    public function updatedClasseId(): void { $this->resetTable(); }
    public function updatedEnseignantFiltreId(): void { $this->resetTable(); }
    public function updatedMatiereId(): void { $this->resetTable(); }
    public function updatedEtatProgression(): void { $this->resetTable(); }

    public function getAnneesScolaires(): array
    {
        return collect(app(AnneeScolaireServiceContract::class)->getToutesLesAnnees())
            ->mapWithKeys(fn ($annee): array => [(int) data_get($annee, 'id') => (string) data_get($annee, 'libelle')])
            ->all();
    }

    public function getCycles(): array
    {
        return DB::table('cycles')
            ->join('niveaux', 'niveaux.cycle_id', '=', 'cycles.id')
            ->join('classes', 'classes.niveau_id', '=', 'niveaux.id')
            ->where('classes.annee_scolaire_id', $this->anneeScolaireId)
            ->when($this->isAdministrativeViewer(), fn ($q) => $q->orderBy('cycles.nom'), fn ($q) => $q->whereIn('classes.id', $this->authorizedClassIds())->orderBy('cycles.nom'))
            ->distinct()->pluck('cycles.nom', 'cycles.id')->all();
    }

    public function getNiveaux(): array
    {
        if (! $this->cycleId) {
            return [];
        }

        return DB::table('niveaux')
            ->join('classes', 'classes.niveau_id', '=', 'niveaux.id')
            ->where('classes.annee_scolaire_id', $this->anneeScolaireId)
            ->when($this->cycleId, fn ($q) => $q->where('niveaux.cycle_id', $this->cycleId))
            ->when(! $this->isAdministrativeViewer(), fn ($q) => $q->whereIn('classes.id', $this->authorizedClassIds()))
            ->orderBy('niveaux.ordre')->orderBy('niveaux.nom')
            ->distinct()->pluck('niveaux.nom', 'niveaux.id')->all();
    }

    public function getClasses(): array
    {
        if (! $this->niveauId) {
            return [];
        }

        return DB::table('classes')
            ->where('annee_scolaire_id', $this->anneeScolaireId)
            ->when($this->niveauId, fn ($q) => $q->where('niveau_id', $this->niveauId))
            ->when(! $this->isAdministrativeViewer(), fn ($q) => $q->whereIn('id', $this->authorizedClassIds()))
            ->orderBy('nom')->pluck('nom', 'id')->all();
    }

    public function getEnseignants(): array
    {
        return DB::table('affectations_pedagogiques as a')
            ->join('enseignants as e', 'e.id', '=', 'a.enseignant_id')
            ->join('employes as p', 'p.id', '=', 'e.employe_id')
            ->join('offres_pedagogiques as o', 'o.id', '=', 'a.offre_pedagogique_id')
            ->where('a.annee_scolaire_id', $this->anneeScolaireId)
            ->where('a.actif', true)->where('o.actif', true)
            ->orderBy('p.nom')->orderBy('p.prenom')
            ->selectRaw("CONCAT(p.nom, ' ', p.prenom) as nom, e.id")
            ->distinct()->pluck('nom', 'e.id')->all();
    }

    public function getMatieres(): array
    {
        return DB::table('matieres as m')
            ->join('offres_pedagogiques as o', 'o.matiere_id', '=', 'm.id')
            ->where('o.annee_scolaire_id', $this->anneeScolaireId)->where('o.actif', true)
            ->when(! $this->isAdministrativeViewer(), fn ($q) => $q->whereIn('o.id', $this->authorizedOfferIds()))
            ->orderBy('m.nom')->distinct()->pluck('m.nom', 'm.id')->all();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->progressionOffersQuery($this->connectedTeacherId()))
            ->columns([
                TextColumn::make('classe_nom')->label('Classe')->sortable(),
                TextColumn::make('matiere_nom')->label('Matière / Offre')->sortable(),
                TextColumn::make('enseignants_affectes')->label('Enseignant')
                    ->state(fn (OffrePedagogique $record): string => DB::table('affectations_pedagogiques as a')
                        ->join('enseignants as e', 'e.id', '=', 'a.enseignant_id')
                        ->join('employes as p', 'p.id', '=', 'e.employe_id')
                        ->where('a.offre_pedagogique_id', $record->id)->where('a.annee_scolaire_id', $record->annee_scolaire_id)->where('a.actif', true)
                        ->orderBy('p.nom')->orderBy('p.prenom')->get(['p.nom', 'p.prenom'])
                        ->map(fn ($person): string => trim($person->prenom.' '.$person->nom))->implode(', ') ?: '—'),
                TextColumn::make('programme_titre')->label('Programme')->placeholder('Aucun programme validé'),
                TextColumn::make('progression_programme')->label('Progression programme')
                    ->state(fn (OffrePedagogique $record): string => $this->progressionMetrics($record)['taux_pondere'].'%'),
                TextColumn::make('progression_temporelle')->label('Progression temporelle')
                    ->state(fn (OffrePedagogique $record): string => $this->progressionMetrics($record)['taux_theorique'].'%'),
                TextColumn::make('ecart')->label('Écart')
                    ->state(fn (OffrePedagogique $record): string => $this->progressionMetrics($record)['delta'].' pts')
                    ->badge()
                    ->color(fn (OffrePedagogique $record): string => $this->progressionMetrics($record)['delta'] < -10 ? 'danger' : 'success'),
                TextColumn::make('derniere_progression')->label('Dernière progression')
                    ->state(fn (OffrePedagogique $record): string => $this->derniereProgression($record)?->format('d/m/Y') ?? '—'),
            ])
            ->actions([
                Action::make('consulterDetails')
                    ->label('Détail')
                    ->icon('heroicon-o-eye')
                    ->visible(fn (): bool => $this->isAdministrativeViewer())
                    ->modalHeading(fn (OffrePedagogique $record): string => 'Progressions — '.$record->classe_nom.' / '.$record->matiere_nom)
                    ->modalSubmitAction(false)
                    ->form(fn (OffrePedagogique $record): array => [
                        Placeholder::make('historique_progression')
                            ->label('Dernières saisies')
                            ->content(fn (): string => $this->progressionHistory($record))
                            ->columnSpanFull(),
                    ]),
                Action::make('renseignerProgression')
                    ->label('Renseigner la progression')
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn (OffrePedagogique $record): bool => ! $this->isAdministrativeViewer() && $record->programme_calc_id !== null && $this->availableSessions($record) !== [])
                    ->modalHeading(fn (OffrePedagogique $record): string => 'Progression — '.$record->classe_nom.' / '.$record->matiere_nom)
                    ->modalWidth('4xl')
                    ->modalSubmitActionLabel('Enregistrer la progression')
                    ->fillForm(function (OffrePedagogique $record): array {
                        $programmeId = $this->programmeIdForOffer($record->id);
                        $teacherId = $this->connectedTeacherId();
                        $suggestion = app(ProgrammeServiceInterface::class)->getProchaineNotionAEffectuer(
                            (int) $record->matiere_id,
                            (int) $record->classe_id,
                            (int) $record->annee_scolaire_id,
                            $teacherId,
                        );
                        $programmeCorrect = $suggestion['programme']?->id === $programmeId;

                        return [
                            'seance_id' => array_key_first($this->availableSessions($record)),
                            'date_progression' => now()->toDateString(),
                            'chapitre_id' => $programmeCorrect ? $suggestion['chapitre']?->id : null,
                            'lecon_id' => $programmeCorrect ? $suggestion['lecon']?->id : null,
                            'contenu_couvert' => $programmeCorrect ? ($suggestion['titre_suggere'] ?? '') : '',
                            'duree_minutes' => $programmeCorrect ? ($suggestion['duree_minutes_suggeree'] ?? 55) : 55,
                            'statut_element' => 'commencee',
                        ];
                    })
                    ->form(fn (OffrePedagogique $record): array => $this->progressionForm($record))
                    ->action(function (OffrePedagogique $record, array $data): void {
                        try {
                            app(ProgressionServiceInterface::class)->saisirProgression(
                                seanceId: (int) $data['seance_id'],
                                chapitreId: (int) $data['chapitre_id'],
                                contenu: (string) $data['contenu_couvert'],
                                devoirs: ! empty($data['devoir_donne']) ? ($data['devoirs_donnes'] ?? null) : null,
                                chapitreTermine: (bool) ($data['chapitre_termine'] ?? false),
                                leconId: isset($data['lecon_id']) ? (int) $data['lecon_id'] : null,
                                statutElement: (string) ($data['statut_element'] ?? 'commencee'),
                                dureeMinutes: isset($data['duree_minutes']) ? (int) $data['duree_minutes'] : null,
                                devoirData: ! empty($data['devoir_donne']) ? [
                                    'titre' => $data['devoir_titre'] ?? null,
                                    'description' => $data['devoirs_donnes'] ?? null,
                                    'type_devoir' => $data['devoir_type'] ?? 'exercice',
                                    'date_limite' => $data['devoir_date_limite'] ?? null,
                                ] : null,
                                observation: $data['observation'] ?? null,
                                dateProgression: $data['date_progression'] ?? null,
                            );

                            Notification::make()->title('Progression enregistrée')->success()->send();
                        } catch (Throwable $exception) {
                            report($exception);
                            Notification::make()->title('Échec de l’enregistrement')->body($exception->getMessage())->danger()->persistent()->send();
                        }
                    }),
            ])
            ->defaultSort('classe_nom');
    }

    private function progressionOffersQuery(?int $teacherId): Builder
    {
        $query = OffrePedagogique::query()
            ->join('classes as c', 'c.id', '=', 'offres_pedagogiques.classe_id')
            ->join('matieres as m', 'm.id', '=', 'offres_pedagogiques.matiere_id')
            ->select('offres_pedagogiques.*', 'c.nom as classe_nom', 'm.nom as matiere_nom')
            ->addSelect([
                'programme_calc_id' => Programme::query()->select('id')
                    ->where(fn ($program) => $program->whereColumn('offre_pedagogique_id', 'offres_pedagogiques.id')->orWhereColumn('id', 'offres_pedagogiques.programme_id'))
                    ->whereIn('statut', ['valide', 'publie', 'soumis'])
                    ->orderByDesc('version')->orderByDesc('id')->limit(1),
                'programme_titre' => Programme::query()->select('titre')
                    ->where(fn ($program) => $program->whereColumn('offre_pedagogique_id', 'offres_pedagogiques.id')->orWhereColumn('id', 'offres_pedagogiques.programme_id'))
                    ->whereIn('statut', ['valide', 'publie', 'soumis'])
                    ->orderByDesc('version')->orderByDesc('id')->limit(1),
            ])
            ->where('offres_pedagogiques.actif', true)
            ->where('offres_pedagogiques.annee_scolaire_id', $this->anneeScolaireId)
            ->when(! $this->isAdministrativeViewer(), function (Builder $builder) use ($teacherId): void {
                if ($teacherId === null) {
                    $builder->whereRaw('1 = 0');
                    return;
                }
                $builder->whereExists(fn ($assignments) => $assignments->selectRaw('1')->from('affectations_pedagogiques as a')
                    ->whereColumn('a.offre_pedagogique_id', 'offres_pedagogiques.id')
                    ->where('a.enseignant_id', $teacherId)->where('a.annee_scolaire_id', $this->anneeScolaireId)->where('a.actif', true));
            })
            ->when($this->classeId, fn (Builder $builder) => $builder->where('offres_pedagogiques.classe_id', $this->classeId))
            ->when($this->niveauId, fn (Builder $builder) => $builder->where('c.niveau_id', $this->niveauId))
            ->when($this->cycleId, fn (Builder $builder) => $builder->whereExists(fn ($cycle) => $cycle->selectRaw('1')->from('niveaux as n')->whereColumn('n.id', 'c.niveau_id')->where('n.cycle_id', $this->cycleId)))
            ->when($this->matiereId, fn (Builder $builder) => $builder->where('offres_pedagogiques.matiere_id', $this->matiereId))
            ->when($this->isAdministrativeViewer() && $this->enseignantFiltreId, fn (Builder $builder) => $builder->whereExists(fn ($assignment) => $assignment->selectRaw('1')->from('affectations_pedagogiques as af')->whereColumn('af.offre_pedagogique_id', 'offres_pedagogiques.id')->where('af.enseignant_id', $this->enseignantFiltreId)->where('af.actif', true)));

        if ($this->etatProgression === 'sans_progression') {
            $query->whereNotExists(fn ($progressions) => $progressions->selectRaw('1')->from('progressions as pr')
                ->join('programme_chapitres as pc', 'pc.id', '=', 'pr.chapitre_id')
                ->join('programmes as pg', 'pg.id', '=', 'pc.programme_id')
                ->join('seances as s', 's.id', '=', 'pr.seance_id')
                ->join('emplois_du_temps as e', 'e.id', '=', 's.emploi_du_temps_id')
                ->where(fn ($program) => $program->whereColumn('pg.offre_pedagogique_id', 'offres_pedagogiques.id')->orWhereColumn('pg.id', 'offres_pedagogiques.programme_id'))
                ->whereColumn('e.classe_id', 'offres_pedagogiques.classe_id')
                ->whereColumn('e.matiere_id', 'offres_pedagogiques.matiere_id')
                ->whereColumn('e.annee_scolaire_id', 'offres_pedagogiques.annee_scolaire_id'));
        } elseif ($this->etatProgression !== null) {
            $query->whereExists(function ($progressions): void {
                $progressions->selectRaw('1')->from('progressions as pr')
                    ->join('programme_chapitres as pc', 'pc.id', '=', 'pr.chapitre_id')
                    ->join('programmes as pg', 'pg.id', '=', 'pc.programme_id')
                    ->join('seances as s', 's.id', '=', 'pr.seance_id')
                    ->join('emplois_du_temps as e', 'e.id', '=', 's.emploi_du_temps_id')
                    ->where(fn ($program) => $program->whereColumn('pg.offre_pedagogique_id', 'offres_pedagogiques.id')->orWhereColumn('pg.id', 'offres_pedagogiques.programme_id'))
                    ->whereColumn('e.classe_id', 'offres_pedagogiques.classe_id')
                    ->whereColumn('e.matiere_id', 'offres_pedagogiques.matiere_id')
                    ->whereColumn('e.annee_scolaire_id', 'offres_pedagogiques.annee_scolaire_id')
                    ->whereIn('pg.statut', ['valide', 'publie', 'soumis'])
                    ->when($this->etatProgression === 'en_cours', fn ($q) => $q->whereIn('pr.statut_element', ['commencee', 'poursuivie']))
                    ->when($this->etatProgression === 'terminee', fn ($q) => $q->where(fn ($sub) => $sub->where('pr.chapitre_termine', true)->orWhere('pr.statut_element', 'terminee')));
            });
        }

        return $query;
    }

    private function progressionForm(OffrePedagogique $offre): array
    {
        $programmeId = $this->programmeIdForOffer($offre->id);

        return [
            Hidden::make('seance_id')->required(),
            DatePicker::make('date_progression')->label('Date')->default(now()->toDateString())->required(),
            Select::make('chapitre_id')->label('Chapitre / unité')->options(fn (): array => $programmeId ? ProgrammeChapitre::where('programme_id', $programmeId)->orderBy('ordre')->pluck('titre', 'id')->all() : [])->live()->afterStateUpdated(fn (Set $set) => $set('lecon_id', null))->required(),
            Select::make('lecon_id')->label('Leçon / unité')->options(fn (Get $get): array => $get('chapitre_id') ? ProgrammeLecon::where('programme_chapitre_id', $get('chapitre_id'))->orderBy('ordre')->pluck('titre', 'id')->all() : [])->searchable(),
            Select::make('statut_element')->label('État')->options(['commencee' => 'Commencée', 'poursuivie' => 'Poursuivie', 'terminee' => 'Terminée'])->default('commencee')->required(),
            Textarea::make('contenu_couvert')->label('Contenu réellement traité')->rows(3)->required(),
            Textarea::make('observation')->label('Observations')->rows(2),
            TextInput::make('duree_minutes')->label('Durée (minutes)')->numeric()->default(55)->required(),
            Toggle::make('chapitre_termine')->label('Chapitre entièrement terminé')->default(false),
            Toggle::make('devoir_donne')->label('Devoir donné ?')->live(),
            TextInput::make('devoir_titre')->label('Titre du devoir')->visible(fn (Get $get): bool => (bool) $get('devoir_donne'))->required(fn (Get $get): bool => (bool) $get('devoir_donne')),
            Textarea::make('devoirs_donnes')->label('Consigne')->visible(fn (Get $get): bool => (bool) $get('devoir_donne')),
            DatePicker::make('devoir_date_limite')->label('Date de remise')->visible(fn (Get $get): bool => (bool) $get('devoir_donne')),
            Select::make('devoir_type')->label('Type')->options(['exercice' => 'Exercice', 'lecture' => 'Lecture', 'devoir_surveille' => 'Devoir surveillé', 'projet' => 'Projet', 'recherche' => 'Recherche'])->default('exercice')->visible(fn (Get $get): bool => (bool) $get('devoir_donne')),
        ];
    }

    private function availableSessions(OffrePedagogique $offre): array
    {
        $teacherId = $this->connectedTeacherId();
        if ($teacherId === null) {
            return [];
        }

        return DB::table('seances as s')
            ->join('emplois_du_temps as e', 'e.id', '=', 's.emploi_du_temps_id')
            ->where('e.classe_id', $offre->classe_id)
            ->where('e.matiere_id', $offre->matiere_id)
            ->where('e.annee_scolaire_id', $offre->annee_scolaire_id)
            ->where('s.enseignant_id', $teacherId)
            ->whereIn('s.statut', ['dispensee', 'commencee'])
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('progressions as p')->whereColumn('p.seance_id', 's.id'))
            ->orderByDesc('s.date_seance')->pluck('s.date_seance', 's.id')
            ->map(fn ($date, $id): string => 'Séance du '.\Illuminate\Support\Carbon::parse($date)->format('d/m/Y').' · #'.$id)
            ->all();
    }

    private function programmeIdForOffer(int $offerId): ?int
    {
        $offer = OffrePedagogique::find($offerId);
        if ($offer === null) {
            return null;
        }

        return Programme::query()
            ->where(fn ($program) => $program->where('offre_pedagogique_id', $offerId)->orWhereKey($offer->programme_id))
            ->whereIn('statut', ['valide', 'publie', 'soumis'])->orderByDesc('version')->orderByDesc('id')->value('id');
    }

    private function progressionMetrics(OffrePedagogique $record): array
    {
        $programmeId = $record->programme_calc_id ?: $this->programmeIdForOffer($record->id);
        if ($programmeId === null) {
            return app(ProgressionCalculator::class)->calculerEcartTemporel((int) $record->annee_scolaire_id, 0.0);
        }

        $calc = app(ProgressionCalculator::class)->calculerProgressionClasse((int) $record->matiere_id, (int) $record->classe_id, (int) $record->annee_scolaire_id, $programmeId);
        return app(ProgressionCalculator::class)->calculerEcartTemporel((int) $record->annee_scolaire_id, (float) $calc['taux_pondere']);
    }

    private function derniereProgression(OffrePedagogique $record): ?\Illuminate\Support\Carbon
    {
        $programmeId = $record->programme_calc_id ?: $this->programmeIdForOffer($record->id);
        if ($programmeId === null) {
            return null;
        }
        $date = Progression::query()->whereHas('chapitre', fn ($q) => $q->where('programme_id', $programmeId))
            ->whereHas('seance.emploiDuTemps', fn ($q) => $q->where('classe_id', $record->classe_id)->where('matiere_id', $record->matiere_id)->where('annee_scolaire_id', $record->annee_scolaire_id))
            ->max('date_progression');
        return $date ? \Illuminate\Support\Carbon::parse($date) : null;
    }

    private function progressionHistory(OffrePedagogique $record): string
    {
        $programmeId = $record->programme_calc_id ?: $this->programmeIdForOffer($record->id);
        return Progression::query()
            ->with(['chapitre', 'lecon'])
            ->whereHas('chapitre', fn ($q) => $q->where('programme_id', $programmeId))
            ->whereHas('seance.emploiDuTemps', fn ($q) => $q->where('classe_id', $record->classe_id)->where('matiere_id', $record->matiere_id)->where('annee_scolaire_id', $record->annee_scolaire_id))
            ->orderByDesc('date_progression')->limit(20)->get()
            ->map(fn (Progression $progression): string => sprintf(
                '%s · %s%s — %s (%s)',
                optional($progression->date_progression)->format('d/m/Y') ?? 'Date inconnue',
                $progression->chapitre?->titre ?? 'Chapitre',
                $progression->lecon?->titre ? ' / '.$progression->lecon->titre : '',
                $progression->contenu_couvert,
                $progression->statut_element ?? '—',
            ))
            ->implode("\n") ?: 'Aucune progression enregistrée.';
    }

    private function connectedTeacherId(): ?int
    {
        $userId = Auth::id();
        return $userId ? app(EnseignantServiceInterface::class)->getEnseignantIdPourUtilisateur((int) $userId) : null;
    }

    private function authorizedOfferIds(): array
    {
        $teacherId = $this->connectedTeacherId();
        return $teacherId === null ? [] : DB::table('affectations_pedagogiques')->where('enseignant_id', $teacherId)->where('annee_scolaire_id', $this->anneeScolaireId)->where('actif', true)->pluck('offre_pedagogique_id')->filter()->all();
    }

    private function authorizedClassIds(): array
    {
        return DB::table('offres_pedagogiques')->whereIn('id', $this->authorizedOfferIds())->pluck('classe_id')->unique()->all();
    }

    public function isAdministrativeViewer(): bool
    {
        return static::estProfilAdministratif();
    }

    private static function estProfilAdministratif(): bool
    {
        $user = Auth::user();
        return $user !== null && ($user->hasAnyRole(['Fondateur', 'Admin', 'Administrateur', 'Super Admin', 'Directeur']) || $user->can('pedagogie.progression.superviser'));
    }
}
