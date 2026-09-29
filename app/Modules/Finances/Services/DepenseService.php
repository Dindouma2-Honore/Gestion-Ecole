<?php

declare(strict_types=1);

namespace App\Modules\Finances\Services;

use App\Modules\Finances\Contracts\BalanceDepenseProviderContract;
use App\Modules\Finances\Contracts\CaisseServiceContract;
use App\Modules\Finances\Contracts\DepenseServiceContract;
use App\Modules\Finances\Contracts\GestionDepenseServiceContract;
use App\Modules\Finances\Models\Depense;
use App\Modules\Finances\Models\RubriqueDepense;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use App\Modules\Socle\Contracts\AuditServiceContract;
use App\Modules\Socle\Contracts\WorkflowServiceContract;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class DepenseService implements BalanceDepenseProviderContract, DepenseServiceContract, GestionDepenseServiceContract
{
    public function __construct(
        private readonly CaisseServiceContract $caisse,
        private readonly AuditServiceContract $audit,
        private readonly AnneeScolaireServiceContract $annees,
        private readonly WorkflowServiceContract $workflows,
    ) {}

    public function creerRubrique(string $nom): object
    {
        $user = Auth::user();
        if (! $user?->hasRole('Fondateur')) {
            throw new AccessDeniedHttpException('Seul le Fondateur peut créer une rubrique de dépense.');
        }

        $rubrique = RubriqueDepense::query()->create(['nom' => trim($nom), 'active' => true, 'cree_par' => $user->id]);
        $this->audit->enregistrer($rubrique, "Création de la rubrique de dépense {$rubrique->nom}");

        return $rubrique;
    }

    public function creerDepense(int $rubriqueId, string $libelle, float $montant, string $motif, ?string $justificatif = null): object
    {
        $user = Auth::user();
        if (! $user) {
            throw new AccessDeniedHttpException('Une authentification est requise pour créer une dépense.');
        }
        if ($montant <= 0 || trim($motif) === '') {
            throw new \DomainException('Le montant et le motif de la dépense sont obligatoires.');
        }
        $contexte = ['montant' => $montant, 'categorie_id' => $rubriqueId];
        $autoValidee = $this->workflows->peutEtreAutoValide('VALIDATION_DEPENSE', $contexte);
        $depense = Depense::query()->create([
            'annee_scolaire_id' => $this->annees->getAnneeCouranteId(),
            'rubrique_depense_id' => RubriqueDepense::query()->where('active', true)->findOrFail($rubriqueId)->id,
            'libelle' => trim($libelle), 'montant' => $montant, 'date_depense' => today(),
            'statut' => $autoValidee ? 'validee' : 'en_attente_validation',
            'motif' => trim($motif), 'justificatif' => $justificatif, 'cree_par' => $user->id,
            'valide_par' => $autoValidee ? $user->id : null, 'validee_le' => $autoValidee ? now() : null,
        ]);
        if (! $autoValidee) {
            $instance = $this->workflows->demarrerWorkflow('VALIDATION_DEPENSE', $depense, 'Finances', $contexte);
            $depense->update(['workflow_instance_id' => $instance->id]);
        }
        $this->audit->enregistrerAvecMotif($depense, 'Création d’une dépense', trim($motif));

        return $depense;
    }

    public function valider(int $depenseId, string $motif): object
    {
        $user = Auth::user();
        if (! $user || trim($motif) === '') {
            throw new AccessDeniedHttpException('Une validation motivée est requise.');
        }
        $depense = Depense::query()->findOrFail($depenseId);
        if ($depense->statut !== 'en_attente_validation') {
            throw new \DomainException('Seule une dépense en attente peut être validée.');
        }
        if (! $depense->workflow_instance_id) {
            throw new \DomainException('Aucun workflow de validation associé.');
        }
        $instance = $this->workflows->transitionner($depense->workflow_instance_id, 'valide', $user->id, $motif);
        if ($instance->statut !== 'valide') {
            throw new \DomainException('Le workflow comporte encore une étape de validation.');
        }
        $depense->update(['statut' => 'validee', 'valide_par' => $user->id, 'validee_le' => now()]);
        $this->audit->enregistrerAvecMotif($depense, 'Validation d’une dépense', trim($motif));

        return $depense->refresh();
    }

    public function marquerPayee(int $depenseId): object
    {
        return DB::transaction(function () use ($depenseId): Depense {
            $depense = Depense::query()->with('rubrique')->lockForUpdate()->findOrFail($depenseId);
            if ($depense->statut !== 'validee') {
                throw new \DomainException('Seule une dépense validée peut être payée.');
            }
            $this->caisse->enregistrerMouvement('decaissement', (float) $depense->montant, $depense, $depense->justificatif, $depense->rubrique->nom, 'Finances', 'Dépenses');
            $depense->update(['statut' => 'payee', 'payee_le' => now()]);
            $this->audit->enregistrer($depense, 'Décaissement de la dépense');

            return $depense->refresh();
        });
    }

    public function getTotalDepenseParCategorie(int $categorieId, int $anneeScolaireId): float
    {
        return round((float) Depense::query()
            ->where('rubrique_depense_id', $categorieId)
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->where('statut', 'payee')
            ->sum('montant'), 2);
    }

    public function getDepensesEnAttenteValidation(DateTimeInterface $dateDebut, DateTimeInterface $dateFin): Collection
    {
        return Depense::query()->with('rubrique')->where('statut', 'en_attente_validation')->whereBetween('date_depense', [$dateDebut, $dateFin])->get();
    }
}
