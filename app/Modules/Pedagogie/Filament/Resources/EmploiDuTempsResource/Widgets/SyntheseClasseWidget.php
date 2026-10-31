<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Filament\Resources\EmploiDuTempsResource\Widgets;

use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use App\Modules\Pedagogie\Contracts\EmploiDuTempsServiceInterface;
use App\Modules\Pedagogie\Models\EmploiDuTemps;
use Filament\Widgets\Widget;
use Filament\Notifications\Notification;
use Barryvdh\DomPDF\Facade\Pdf;
use DomainException;
use InvalidArgumentException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;

class SyntheseClasseWidget extends Widget
{
    protected string $view = 'pedagogie::filament.widgets.synthese-emploi-classe';

    protected int|string|array $columnSpan = 'full';

    #[Url(as: 'annee')]
    public ?int $anneeScolaireId = null;

    public ?string $cycleFiltre = null;

    public ?string $niveauFiltre = null;

    #[Url(as: 'classe')]
    public ?int $classeId = null;

    public function mount(): void
    {
        $this->anneeScolaireId ??= app(AnneeScolaireServiceContract::class)->getAnneeCouranteId();
    }

    public function updatedAnneeScolaireId(): void
    {
        $this->cycleFiltre = $this->niveauFiltre = null;
        $this->classeId = null;
    }

    public function updatedCycleFiltre(): void
    {
        $this->niveauFiltre = null;
        $this->classeId = null;
    }

    public function updatedNiveauFiltre(): void
    {
        $this->classeId = null;
    }

    public function genererEmploiDuTemps(): void
    {
        if (! $this->classeId || ! $this->anneeScolaireId) {
            Notification::make()->title('Sélectionnez une classe')->warning()->send();
            return;
        }

        try {
            $resultat = app(EmploiDuTempsServiceInterface::class)
                ->genererProposition($this->classeId, $this->anneeScolaireId);
        } catch (DomainException|InvalidArgumentException $exception) {
            Notification::make()->title('Génération impossible')->body($exception->getMessage())->danger()->send();
            return;
        }
        $reste = collect($resultat['restants'])
            ->map(fn (float $heures, string $matiere): string => "{$matiere} : {$heures} h")
            ->implode(' · ');

        $notification = Notification::make()
            ->title('Proposition générée')
            ->body($resultat['ajoutes'].' cours placés'.($reste !== '' ? '. Reste à placer : '.$reste : '. Tous les volumes sont placés.'));

        if ($reste !== '') {
            $notification->warning();
        } else {
            $notification->success();
        }

        $notification->send();
    }

    public function verifierConflits(): void
    {
        if (! $this->classeId || ! $this->anneeScolaireId) {
            return;
        }
        $erreurs = app(EmploiDuTempsServiceInterface::class)->verifierEmploiDuTemps($this->classeId, $this->anneeScolaireId);
        $notification = Notification::make();
        if ($erreurs === []) {
            $notification->title('Vérification terminée')->body('Aucun conflit détecté.')->success();
        } else {
            $notification->title('Conflits à corriger')->body(implode(' ', $erreurs))->danger();
        }
        $notification->send();
    }

    public function validerPlanning(): void
    {
        if (! $this->classeId || ! $this->anneeScolaireId) {
            Notification::make()->title('Sélectionnez une classe')->warning()->send();
            return;
        }
        try {
            app(EmploiDuTempsServiceInterface::class)->validerEmploiDuTemps($this->classeId, $this->anneeScolaireId);
            Notification::make()->title('Emploi du temps validé')->body('Les créneaux sont maintenant protégés contre les modifications ordinaires.')->success()->send();
        } catch (DomainException $exception) {
            Notification::make()->title('Validation impossible')->body($exception->getMessage())->danger()->send();
        }
    }

    public function publierPlanning(): void
    {
        if (! $this->classeId || ! $this->anneeScolaireId) {
            Notification::make()->title('Sélectionnez une classe')->warning()->send();
            return;
        }
        try {
            app(EmploiDuTempsServiceInterface::class)->publierEmploiDuTemps($this->classeId, $this->anneeScolaireId);
            Notification::make()->title('Emploi du temps publié')->body('Le planning est actif et disponible pour les fonctions pédagogiques.')->success()->send();
        } catch (DomainException $exception) {
            Notification::make()->title('Publication impossible')->body($exception->getMessage())->danger()->send();
        }
    }

    public function imprimer(): mixed
    {
        return Pdf::loadView('pedagogie::filament.documents.emploi-du-temps', $this->getDonneesImpression())
            ->stream($this->nomFichierPdf());
    }

    public function exporterPdf(): mixed
    {
        return Pdf::loadView('pedagogie::filament.documents.emploi-du-temps', $this->getDonneesImpression())
            ->download($this->nomFichierPdf());
    }

    private function nomFichierPdf(): string
    {
        return 'emploi-du-temps-classe-'.$this->classeId.'.pdf';
    }

    /** @return array<string, mixed> */
    private function getDonneesImpression(): array
    {
        $classe = DB::table('classes')->where('id', $this->classeId)
            ->where('annee_scolaire_id', $this->anneeScolaireId)->first(['nom']);
        if (! $classe) {
            throw new DomainException('Sélectionnez une classe pour imprimer son emploi du temps.');
        }

        $emplois = DB::table('emplois_du_temps as emplois')
            ->join('creneaux_horaires as creneaux', 'creneaux.id', '=', 'emplois.creneau_id')
            ->leftJoin('matieres', 'matieres.id', '=', 'emplois.matiere_id')
            ->leftJoin('enseignants', 'enseignants.id', '=', 'emplois.enseignant_id')
            ->leftJoin('employes', 'employes.id', '=', 'enseignants.employe_id')
            ->where('emplois.classe_id', $this->classeId)
            ->where('emplois.annee_scolaire_id', $this->anneeScolaireId)
            ->where(fn ($query) => $query->where('emplois.actif', true)->orWhereIn('emplois.statut', [
                EmploiDuTemps::STATUT_PREPARATION, EmploiDuTemps::STATUT_GENERE,
                EmploiDuTemps::STATUT_AJUSTE, EmploiDuTemps::STATUT_VALIDE, 'brouillon',
            ]))
            ->orderBy('creneaux.jour_semaine')->orderBy('creneaux.heure_debut')
            ->get([
                'creneaux.jour_semaine', 'creneaux.heure_debut', 'creneaux.heure_fin',
                'matieres.nom as matiere', 'emplois.libelle_activite', 'employes.prenom', 'employes.nom',
            ]);
        $jours = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi'];
        $emplois->each(function (object $emploi) use ($jours): void {
            $emploi->jour = $jours[(int) $emploi->jour_semaine] ?? 'Jour '.$emploi->jour_semaine;
            $emploi->enseignant = trim(($emploi->prenom ?? '').' '.($emploi->nom ?? '')) ?: '—';
            $emploi->matiere = $emploi->matiere ?: ($emploi->libelle_activite ?: 'Activité générale');
        });

        $annee = app(AnneeScolaireServiceContract::class)->getAnneeScolaire($this->anneeScolaireId);
        $statuts = [
            EmploiDuTemps::STATUT_PREPARATION => 'En préparation',
            'brouillon' => 'En préparation',
            EmploiDuTemps::STATUT_GENERE => 'Généré',
            EmploiDuTemps::STATUT_AJUSTE => 'Ajusté',
            EmploiDuTemps::STATUT_VALIDE => 'Validé',
            EmploiDuTemps::STATUT_PUBLIE => 'Publié',
        ];

        return [
            'classe' => $classe->nom,
            'annee' => (string) data_get($annee, 'libelle', ''),
            'statut' => $statuts[app(EmploiDuTempsServiceInterface::class)->getStatutEmploiDuTemps($this->classeId, $this->anneeScolaireId)] ?? 'En préparation',
            'emplois' => $emplois,
        ];
    }

    /** @return array<int, string> */
    public function getAnneesScolaires(): array
    {
        return collect(app(AnneeScolaireServiceContract::class)->getToutesLesAnnees())
            ->mapWithKeys(fn ($annee): array => [(int) data_get($annee, 'id') => (string) data_get($annee, 'libelle')])
            ->all();
    }

    private function getClassHierarchy(): Collection
    {
        if (! $this->anneeScolaireId) {
            return collect();
        }

        return DB::table('classes')
            ->join('niveaux', 'niveaux.id', '=', 'classes.niveau_id')
            ->join('cycles', 'cycles.id', '=', 'niveaux.cycle_id')
            ->where('classes.annee_scolaire_id', $this->anneeScolaireId)
            ->orderBy('cycles.ordre')->orderBy('cycles.nom')
            ->orderBy('niveaux.ordre')->orderBy('niveaux.nom')->orderBy('classes.nom')
            ->get(['cycles.nom as cycle_nom', 'niveaux.nom as niveau_nom', 'classes.id', 'classes.nom'])
            ->groupBy('cycle_nom')
            ->map(fn (Collection $items) => $items->groupBy('niveau_nom'));
    }

    /** @return Collection<int, object> */
    public function getSynthese(): Collection
    {
        if (! $this->classeId || ! $this->anneeScolaireId) {
            return collect();
        }

        $classeExiste = DB::table('classes')
            ->where('id', $this->classeId)
            ->where('annee_scolaire_id', $this->anneeScolaireId)
            ->exists();
        if (! $classeExiste) {
            return collect();
        }

        $offres = DB::table('offres_pedagogiques as offres')
            ->join('matieres', 'matieres.id', '=', 'offres.matiere_id')
            ->where('offres.classe_id', $this->classeId)
            ->where('offres.annee_scolaire_id', $this->anneeScolaireId)
            ->where('offres.actif', true)
            ->orderBy('matieres.nom')
            ->get(['offres.id as offre_id', 'offres.matiere_id', 'offres.heures_hebdomadaires', 'matieres.nom as matiere']);

        $affectations = DB::table('affectations_pedagogiques as affectations')
            ->join('enseignants', 'enseignants.id', '=', 'affectations.enseignant_id')
            ->join('employes', 'employes.id', '=', 'enseignants.employe_id')
            ->where('affectations.classe_id', $this->classeId)
            ->where('affectations.annee_scolaire_id', $this->anneeScolaireId)
            ->where('affectations.actif', true)
            ->get(['affectations.offre_pedagogique_id', 'affectations.matiere_id', 'affectations.enseignant_id', 'employes.prenom', 'employes.nom']);

        $placed = DB::table('emplois_du_temps as emplois')
            ->join('creneaux_horaires as creneaux', 'creneaux.id', '=', 'emplois.creneau_id')
            ->where('emplois.classe_id', $this->classeId)
            ->where('emplois.annee_scolaire_id', $this->anneeScolaireId)
            ->where(fn ($query) => $query->where('emplois.actif', true)->orWhereIn('emplois.statut', [
                EmploiDuTemps::STATUT_PREPARATION, EmploiDuTemps::STATUT_GENERE,
                EmploiDuTemps::STATUT_AJUSTE, EmploiDuTemps::STATUT_VALIDE, 'brouillon',
            ]))
            ->whereNotNull('emplois.matiere_id')
            ->get(['emplois.matiere_id', 'creneaux.heure_debut', 'creneaux.heure_fin'])
            ->groupBy('matiere_id')
            ->map(fn (Collection $entries): float => $entries->sum(function (object $entry): float {
                $start = strtotime((string) $entry->heure_debut);
                $end = strtotime((string) $entry->heure_fin);
                return $start !== false && $end !== false && $end > $start ? ($end - $start) / 3600 : 0;
            }));

        return $offres->map(function (object $offre) use ($affectations, $placed): object {
            $relevant = $affectations->filter(function (object $affectation) use ($offre): bool {
                if ($affectation->offre_pedagogique_id !== null) {
                    return (int) $affectation->offre_pedagogique_id === (int) $offre->offre_id;
                }
                return (int) $affectation->matiere_id === (int) $offre->matiere_id;
            });
            $offre->enseignants = $relevant->unique('enseignant_id')
                ->map(fn (object $a): string => trim(($a->prenom ?? '').' '.($a->nom ?? '')))
                ->filter()->values();
            $offre->prevu = (float) ($offre->heures_hebdomadaires ?? 0);
            $offre->place = (float) ($placed->get($offre->matiere_id, 0));
            $offre->reste = $offre->prevu - $offre->place;
            return $offre;
        });
    }

    protected function getViewData(): array
    {
        $hierarchy = $this->getClassHierarchy();
        $niveaux = $this->cycleFiltre ? $hierarchy->get($this->cycleFiltre, collect()) : collect();
        $classes = $this->niveauFiltre ? $niveaux->get($this->niveauFiltre, collect()) : collect();

        return [
            'hierarchy' => $hierarchy,
            'cycles' => $hierarchy->keys(),
            'niveaux' => $niveaux->keys(),
            'classes' => $classes,
            'synthese' => $this->getSynthese(),
            'statutPlanning' => $this->classeId && $this->anneeScolaireId
                ? app(EmploiDuTempsServiceInterface::class)->getStatutEmploiDuTemps($this->classeId, $this->anneeScolaireId)
                : EmploiDuTemps::STATUT_PREPARATION,
        ];
    }
}
