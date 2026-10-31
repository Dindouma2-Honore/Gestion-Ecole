<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Filament\Pages;

use App\Modules\Pedagogie\Filament\Resources\AnomalieAppelResource;
use App\Modules\Pedagogie\Filament\Resources\BulletinResource;
use App\Modules\Pedagogie\Filament\Resources\EmploiDuTempsResource;
use App\Modules\Pedagogie\Filament\Resources\EvaluationResource;
use App\Modules\Pedagogie\Filament\Resources\MatiereResource;
use App\Modules\Pedagogie\Filament\Resources\NoteResource;
use App\Modules\Pedagogie\Filament\Resources\PresenceResource;
use App\Modules\Pedagogie\Filament\Resources\ProgrammeResource;
use App\Modules\Pedagogie\Filament\Resources\ProgressionResource;
use App\Modules\Pedagogie\Filament\Resources\SeanceResource;
use App\Modules\Pedagogie\Models\AnomalieAppel;
use App\Modules\Pedagogie\Models\Bulletin;
use App\Modules\Pedagogie\Models\Evaluation;
use App\Modules\Pedagogie\Models\Note;
use App\Modules\Pedagogie\Models\ProgrammeChapitre;
use App\Modules\Pedagogie\Models\Progression;
use App\Modules\Pedagogie\Models\Seance;
use App\Modules\RH\Contracts\AbsenceServiceContract;
use App\Modules\Scolarite\Contracts\AcademicStructureCatalogContract;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use App\Modules\Socle\Contracts\ParametrageServiceContract;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class PedagogieDashboard extends Page
{
    public ?int $cycleId = null;

    public ?int $classeId = null;

    public ?int $periodeId = null;

    public ?string $dateDebut = null;

    public ?string $dateFin = null;

    protected static ?string $slug = 'pedagogie';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Tableau de bord';

    protected string $view = 'pedagogie::filament.pages.dashboard';

    public function mount(): void
    {
        $this->dateDebut = today()->toDateString();
        $this->dateFin = today()->addDays(7)->toDateString();
    }

    public function updatedCycleId(): void
    {
        $this->classeId = null;
    }

    public function reinitialiserFiltres(): void
    {
        $this->cycleId = null;
        $this->classeId = null;
        $this->periodeId = null;
        $this->dateDebut = today()->toDateString();
        $this->dateFin = today()->addDays(7)->toDateString();
    }

    public function getContext(): array
    {
        $parametres = app(ParametrageServiceContract::class);
        try {
            $anneeId = (int) app(AnneeScolaireServiceContract::class)->getAnneeCouranteId();
        } catch (\Throwable) {
            $anneeId = 0;
        }
        $periodes = $anneeId ? collect(app(AnneeScolaireServiceContract::class)->getPeriodes($anneeId)) : collect();
        if ($this->periodeId === null) {
            $this->periodeId = (int) data_get($periodes->first(fn ($p) => in_array(data_get($p, 'statut'), ['active', 'actif', 'ouverte'], true)), 'id') ?: null;
        }

        return [
            'etablissement' => $parametres->getConfigEtablissement()->nom ?? 'Établissement',
            'cycles' => collect(app(AcademicStructureCatalogContract::class)->cycles(true))->pluck('name', 'id'),
            'classes' => DB::table('classes')->when($this->cycleId, fn ($q) => $q->whereIn('classes.niveau_id', array_keys(app(AcademicStructureCatalogContract::class)->niveaux((int) $this->cycleId))))->when($anneeId, fn ($q) => $q->where('classes.annee_scolaire_id', $anneeId))->orderBy('classes.nom')->pluck('classes.nom', 'classes.id'),
            'periodes' => $periodes->mapWithKeys(fn ($p) => [(int) data_get($p, 'id') => data_get($p, 'libelle', data_get($p, 'nom', '#'.data_get($p, 'id')))]),
        ];
    }

    private function classeIds(): ?array
    {
        if ($this->classeId) {
            return [$this->classeId];
        }

        return $this->cycleId ? DB::table('classes')->whereIn('classes.niveau_id', array_keys(app(AcademicStructureCatalogContract::class)->niveaux((int) $this->cycleId)))->pluck('classes.id')->map(fn ($id) => (int) $id)->all() : null;
    }

    private function scopeSeances(Builder $query): Builder
    {
        $classeIds = $this->classeIds();

        return $query->when($classeIds !== null, fn (Builder $q) => $q->whereHas('emploiDuTemps', fn (Builder $e) => $e->whereIn('classe_id', $classeIds)));
    }

    public function getIndicators(): array
    {
        $today = today();
        $debut = $this->dateDebut ?: $today->toDateString();
        $fin = $this->dateFin ?: $debut;
        if ($fin < $debut) {
            [$debut,$fin] = [$fin, $debut];
        }
        $todayQuery = $this->scopeSeances(Seance::query()->whereBetween('date_seance', [$debut, $fin]));
        try {
            $anneeId = (int) app(AnneeScolaireServiceContract::class)->getAnneeCouranteId();
        } catch (\Throwable) {
            $anneeId = 0;
        }
        $activePeriod = $anneeId > 0
            ? collect(app(AnneeScolaireServiceContract::class)->getPeriodes($anneeId))->first(fn ($p) => in_array(data_get($p, 'statut'), ['active', 'actif', 'ouverte'], true))
            : null;
        $activePeriodId = data_get($activePeriod, 'id');

        return [
            ['label' => 'Séances sur la période', 'value' => (clone $todayQuery)->count(), 'url' => SeanceResource::getUrl()],
            ['label' => 'Réalisées', 'value' => (clone $todayQuery)->where('statut', 'dispensee')->count(), 'url' => SeanceResource::getUrl()],
            ['label' => 'Non renseignées', 'value' => (clone $todayQuery)->whereIn('statut', ['programmee', 'commencee'])->count(), 'url' => SeanceResource::getUrl()],
            ['label' => 'Annulées', 'value' => (clone $todayQuery)->where('statut', 'annulee')->count(), 'url' => SeanceResource::getUrl()],
            ['label' => 'Appels manquants', 'value' => AnomalieAppel::where('resolue', false)->when($this->classeIds() !== null, fn ($q) => $q->whereHas('seance.emploiDuTemps', fn ($e) => $e->whereIn('classe_id', $this->classeIds())))->count(), 'url' => AnomalieAppelResource::getUrl()],
            ['label' => 'Personnel absent aujourd’hui', 'value' => app(AbsenceServiceContract::class)->compterAbsencesValideesPourDate($today), 'url' => '#'],
            ['label' => 'Progressions en retard', 'value' => ProgrammeChapitre::whereNotIn('id', Progression::whereNotNull('seance_id')->whereNotNull('chapitre_id')->select('chapitre_id'))->whereNotNull('periode_prevue_id')->count(), 'url' => ProgressionResource::getUrl()],
            ['label' => 'Évaluations sur la période', 'value' => Evaluation::whereBetween('date_evaluation', [$debut, $fin])->when($this->periodeId, fn ($q) => $q->where('periode_id', $this->periodeId))->when($this->classeIds() !== null, fn ($q) => $q->whereIn('classe_id', $this->classeIds()))->count(), 'url' => EvaluationResource::getUrl()],
            ['label' => 'Notes à finaliser', 'value' => Note::whereIn('statut', ['brouillon', 'soumis'])->whereHas('evaluation', fn ($q) => $q->when($this->periodeId, fn ($e) => $e->where('periode_id', $this->periodeId))->when($this->classeIds() !== null, fn ($e) => $e->whereIn('classe_id', $this->classeIds())))->count(), 'url' => NoteResource::getUrl()],
            ['label' => 'Bulletins non finalisés', 'value' => Bulletin::when($this->periodeId ?: $activePeriodId, fn ($q, $id) => $q->where('periode_id', $id))->when($this->classeIds() !== null, fn ($q) => $q->whereIn('classe_id', $this->classeIds()))->whereNotIn('statut', ['publie', 'archive'])->count(), 'url' => BulletinResource::getUrl()],
        ];
    }

    public function getAnomalies(): array
    {
        return AnomalieAppel::with('seance.emploiDuTemps.matiere')->where('resolue', false)->when($this->classeIds() !== null, fn ($q) => $q->whereHas('seance.emploiDuTemps', fn ($e) => $e->whereIn('classe_id', $this->classeIds())))->latest('detectee_le')->limit(8)->get()->map(fn ($a) => [
            'classe' => 'Classe #'.($a->seance?->emploiDuTemps?->classe_id ?? '—'),
            'matiere' => $a->seance?->emploiDuTemps?->matiere?->nom ?? '—',
            'type' => 'Appel manquant', 'url' => AnomalieAppelResource::getUrl(),
        ])->all();
    }

    public function getModuleIcon(): string
    {
        return 'heroicon-o-book-open';
    }

    public function getModuleDescription(): string
    {
        return 'Pilotez les matières, programmes, emplois du temps, séances et appels depuis cet espace.';
    }

    public function getModuleLinks(): array
    {
        return [
            ['label' => 'Matières', 'description' => 'Catalogue des matières enseignées', 'icon' => 'heroicon-o-book-open', 'url' => MatiereResource::getUrl('index')],
            ['label' => 'Évaluations', 'description' => 'Devoirs, examens et barèmes', 'icon' => 'heroicon-o-clipboard-document-check', 'url' => EvaluationResource::getUrl('index')],
            ['label' => 'Notes', 'description' => 'Saisie contrôlée et verrouillage des notes', 'icon' => 'heroicon-o-pencil-square', 'url' => NoteResource::getUrl('index')],
            ['label' => 'Programmes', 'description' => 'Programmes et objectifs pédagogiques', 'icon' => 'heroicon-o-clipboard-document-list', 'url' => ProgrammeResource::getUrl('index')],
            ['label' => 'Emplois du temps', 'description' => 'Cours, créneaux, classes et salles', 'icon' => 'heroicon-o-calendar-days', 'url' => EmploiDuTempsResource::getUrl('index')],
            ['label' => 'Séances', 'description' => 'Séances programmées et dispensées', 'icon' => 'heroicon-o-presentation-chart-bar', 'url' => SeanceResource::getUrl('index')],
            ['label' => 'Présences', 'description' => 'Appels et suivi de présence', 'icon' => 'heroicon-o-check-badge', 'url' => PresenceResource::getUrl('index')],
            ['label' => 'Anomalies d’appel', 'description' => 'Appels manquants ou en retard', 'icon' => 'heroicon-o-exclamation-triangle', 'url' => AnomalieAppelResource::getUrl('index')],
        ];
    }
}
