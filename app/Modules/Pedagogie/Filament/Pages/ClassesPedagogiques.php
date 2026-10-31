<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Filament\Pages;

use App\Modules\Pedagogie\Models\AffectationPedagogique;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;

class ClassesPedagogiques extends Page
{
    protected static ?string $slug = 'pedagogie/classes';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Classes';

    protected string $view = 'pedagogie::filament.pages.classes-pedagogiques';

    #[Url(as: 'annee')]
    public ?int $anneeScolaireId = null;

    #[Url(as: 'classe')]
    public ?int $classeId = null;

    public ?string $cycleFiltre = null;

    public ?string $niveauFiltre = null;

    public function mount(): void
    {
        $this->anneeScolaireId ??= app(AnneeScolaireServiceContract::class)->getAnneeCouranteId();
    }

    public function updatedAnneeScolaireId(): void
    {
        $this->cycleFiltre = null;
        $this->niveauFiltre = null;
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

    /** @return array<int, string> */
    public function getAnneesScolaires(): array
    {
        return collect(app(AnneeScolaireServiceContract::class)->getToutesLesAnnees())
            ->mapWithKeys(fn (object|array $annee): array => [
                (int) data_get($annee, 'id') => (string) data_get($annee, 'libelle'),
            ])
            ->all();
    }

    /** @return Collection<string, Collection<string, Collection<int, object>>> */
    public function getClassesParCycle(): Collection
    {
        if (! $this->anneeScolaireId) {
            return collect();
        }

        return DB::table('classes')
            ->join('niveaux', 'niveaux.id', '=', 'classes.niveau_id')
            ->join('cycles', 'cycles.id', '=', 'niveaux.cycle_id')
            ->where('classes.annee_scolaire_id', $this->anneeScolaireId)
            ->orderBy('cycles.ordre')
            ->orderBy('cycles.nom')
            ->orderBy('niveaux.ordre')
            ->orderBy('niveaux.nom')
            ->orderBy('classes.nom')
            ->get([
                'cycles.nom as cycle_nom',
                'niveaux.nom as niveau_nom',
                'classes.id',
                'classes.nom',
                'classes.statut',
            ])
            ->groupBy('cycle_nom')
            ->map(fn (Collection $classesDuCycle): Collection => $classesDuCycle->groupBy('niveau_nom'));
    }

    public function getClasseSelectionnee(): ?object
    {
        if (! $this->classeId || ! $this->anneeScolaireId) {
            return null;
        }

        $classe = DB::table('classes as classes')
            ->join('niveaux', 'niveaux.id', '=', 'classes.niveau_id')
            ->join('cycles', 'cycles.id', '=', 'niveaux.cycle_id')
            ->leftJoin('enseignants', 'enseignants.id', '=', 'classes.professeur_principal_id')
            ->leftJoin('employes', 'employes.id', '=', 'enseignants.employe_id')
            ->where('classes.id', $this->classeId)
            ->where('classes.annee_scolaire_id', $this->anneeScolaireId)
            ->first([
                'classes.id',
                'classes.nom',
                'classes.code',
                'classes.statut',
                'classes.annee_scolaire_id',
                'classes.professeur_principal_id',
                'cycles.nom as cycle_nom',
                'niveaux.nom as niveau_nom',
                'employes.prenom as enseignant_principal_prenom',
                'employes.nom as enseignant_principal_nom',
            ]);

        if (! $classe) {
            return null;
        }

        $annee = app(AnneeScolaireServiceContract::class)->getAnneeScolaire((int) $classe->annee_scolaire_id);
        $classe->annee_libelle = (string) data_get($annee, 'libelle');
        $classe->enseignant_principal = trim(($classe->enseignant_principal_prenom ?? '').' '.($classe->enseignant_principal_nom ?? ''));
        $classe->effectif = DB::table('inscriptions')
            ->where('classe_id', $classe->id)
            ->where('annee_scolaire_id', $classe->annee_scolaire_id)
            ->whereIn('statut', ['active', 'validee'])
            ->count();

        return $classe;
    }

    /** @return Collection<int, object> */
    public function getMatieresEtEnseignants(object $classe): Collection
    {
        $offres = DB::table('offres_pedagogiques as offres')
            ->join('matieres', 'matieres.id', '=', 'offres.matiere_id')
            ->where('offres.classe_id', $classe->id)
            ->where('offres.annee_scolaire_id', $classe->annee_scolaire_id)
            ->where('offres.actif', true)
            ->orderBy('matieres.nom')
            ->get([
                'offres.id as offre_id',
                'offres.matiere_id',
                'offres.heures_hebdomadaires',
                'offres.actif',
                'matieres.nom as matiere_nom',
            ]);

        if ($offres->isEmpty()) {
            return collect();
        }

        $affectations = DB::table('affectations_pedagogiques as affectations')
            ->join('enseignants', 'enseignants.id', '=', 'affectations.enseignant_id')
            ->join('employes', 'employes.id', '=', 'enseignants.employe_id')
            ->where('affectations.classe_id', $classe->id)
            ->where('affectations.annee_scolaire_id', $classe->annee_scolaire_id)
            ->where('affectations.actif', true)
            ->get([
                'affectations.offre_pedagogique_id',
                'affectations.matiere_id',
                'affectations.type_affectation',
                'affectations.enseignant_id',
                'employes.prenom as enseignant_prenom',
                'employes.nom as enseignant_nom',
            ]);

        $affectations->each(function (object $affectation): void {
            $affectation->enseignant_nom = trim(($affectation->enseignant_prenom ?? '').' '.($affectation->enseignant_nom ?? ''));
        });

        return $offres->map(function (object $offre) use ($affectations): object {
            $offre->enseignants = $affectations
                ->filter(function (object $affectation) use ($offre): bool {
                    if ($affectation->offre_pedagogique_id !== null) {
                        return (int) $affectation->offre_pedagogique_id === (int) $offre->offre_id;
                    }

                    return $affectation->type_affectation === AffectationPedagogique::TYPE_PRINCIPAL
                        || (int) $affectation->matiere_id === (int) $offre->matiere_id
                        || ($affectation->type_affectation === AffectationPedagogique::TYPE_ASSISTANT && $affectation->matiere_id === null);
                })
                ->unique('enseignant_id')
                ->pluck('enseignant_nom')
                ->values();

            return $offre;
        });
    }

    /** @return Collection<int, object> */
    public function getEmploiDuTemps(object $classe): Collection
    {
        return DB::table('emplois_du_temps as emplois')
            ->leftJoin('creneaux_horaires', 'creneaux_horaires.id', '=', 'emplois.creneau_id')
            ->leftJoin('matieres', 'matieres.id', '=', 'emplois.matiere_id')
            ->leftJoin('enseignants', 'enseignants.id', '=', 'emplois.enseignant_id')
            ->leftJoin('employes', 'employes.id', '=', 'enseignants.employe_id')
            ->where('emplois.classe_id', $classe->id)
            ->where('emplois.annee_scolaire_id', $classe->annee_scolaire_id)
            ->where('emplois.actif', true)
            ->orderBy('creneaux_horaires.jour_semaine')
            ->orderBy('creneaux_horaires.heure_debut')
            ->get([
                'emplois.id',
                'emplois.statut',
                'creneaux_horaires.jour_semaine',
                'creneaux_horaires.heure_debut',
                'creneaux_horaires.heure_fin',
                'matieres.nom as matiere_nom',
                'employes.prenom as enseignant_prenom',
                'employes.nom as enseignant_nom',
            ]);
    }
}
