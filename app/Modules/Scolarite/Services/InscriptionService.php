<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Services;

use App\Modules\Scolarite\Contracts\ClasseServiceInterface;
use App\Modules\Scolarite\Contracts\FactureServiceInterface;
use App\Modules\Scolarite\Contracts\InscriptionFacturationPort;
use App\Modules\Scolarite\Contracts\InscriptionServiceInterface;
use App\Modules\Scolarite\Exceptions\CapaciteClasseDepasseeException;
use App\Modules\Scolarite\Exceptions\DoubleInscriptionException;
use App\Modules\Scolarite\Exceptions\EleveIntrouvableException;
use App\Modules\Scolarite\Exceptions\ParentPayeurInvalideException;
use App\Modules\Scolarite\Models\Eleve;
use App\Modules\Scolarite\Models\Inscription;
use App\Modules\Scolarite\Models\ParentTuteur;
use App\Modules\Socle\Contracts\AuditServiceContract;
use Illuminate\Support\Facades\DB;

class InscriptionService implements InscriptionServiceInterface
{
    public function __construct(
        private readonly ClasseServiceInterface $classeService,
        private readonly AuditServiceContract $auditService,
        private readonly FactureServiceInterface $factureService,
        // Nullable : le module Finances (Dev B) n'est pas encore livré, donc
        // aucun binding n'existe pour ce port. Rendre le paramètre optionnel
        // permet à InscriptionService de rester instanciable (inscrire(),
        // preparerEtInscrire()) tant que Finances n'est pas prêt.
        private readonly ?InscriptionFacturationPort $facturation = null,
    ) {}

    public function inscrire(int $eleveId, int $classeId, int $anneeScolaireId): int
    {
        return DB::transaction(function () use ($eleveId, $classeId, $anneeScolaireId) {

            $eleve = Eleve::find($eleveId);

            if (! $eleve) {
                throw EleveIntrouvableException::pourId($eleveId);
            }

            if ($this->estInscrit($eleveId, $anneeScolaireId)) {
                throw new DoubleInscriptionException($eleveId, $anneeScolaireId);
            }

            if ($this->classeService->getPlacesRestantes($classeId) <= 0) {
                throw new CapaciteClasseDepasseeException($classeId);
            }

            // Le matricule permanent est généré dès la création du dossier élève.
            $inscription = Inscription::create([
                'eleve_id' => $eleveId,
                'classe_id' => $classeId,
                'annee_scolaire_id' => $anneeScolaireId,
                'type' => Inscription::query()->where('eleve_id', $eleveId)->exists()
                    ? 'reinscription'
                    : 'inscription',
                'date_inscription' => now(),
                'statut' => 'validee',
            ]);

            $eleve->update(['statut' => 'inscrit']);

            // 4. Journaliser via le Socle — jamais d'écriture directe dans la table audits.
            $this->auditService->enregistrerAvecMotif(
                $inscription,
                "Inscription de l'élève #{$eleveId} en classe #{$classeId}",
                'inscription_initiale'
            );

            // 5. Générer la facture correspondante (montant = frais de la
            // classe). L'envoi au parent par WhatsApp sera branché plus tard
            // par un autre développeur, via FactureServiceInterface — cette
            // étape s'arrête à la génération.
            if (method_exists($this->factureService, 'genererPourInscription')) {
                $this->factureService->genererPourInscription($inscription->id);
            }

            return $inscription->id;
        });
    }

    public function reinscrire(int $eleveId, int $classeId, int $anneeScolaireId, array $fraisDiversIds = []): int
    {
        if ($this->estInscrit($eleveId, $anneeScolaireId)) {
            throw new DoubleInscriptionException($eleveId, $anneeScolaireId);
        }
        if ($this->classeService->getPlacesRestantes($classeId) <= 0) {
            throw new CapaciteClasseDepasseeException($classeId);
        }

        return (int) Inscription::create([
            'eleve_id' => $eleveId,
            'classe_id' => $classeId,
            'annee_scolaire_id' => $anneeScolaireId,
            'type' => 'reinscription',
            'date_inscription' => now(),
            'statut' => 'en_attente_versement',
        ])->id;
    }

    public function demarrerPreinscription(int $eleveId, int $classeId, int $anneeScolaireId, int $parentId, array $fraisOptionnels = [], ?float $montantVerse = null): int
    {
        if (! $this->facturation) {
            throw new \RuntimeException(
                "Le module Finances n'est pas encore disponible : la préinscription avec facture provisoire est désactivée. Utilisez inscrire() ou preparerEtInscrire() pour valider directement."
            );
        }

        return DB::transaction(function () use ($eleveId, $classeId, $anneeScolaireId, $parentId, $fraisOptionnels, $montantVerse): int {
            $eleve = Eleve::with(['parentsTuteurs' => fn ($query) => $query->whereKey($parentId)])->find($eleveId);

            if (! $eleve) {
                throw EleveIntrouvableException::pourId($eleveId);
            }

            $parent = $eleve->parentsTuteurs->first();
            if (! $parent || ! filter_var($parent->email, FILTER_VALIDATE_EMAIL)) {
                throw new ParentPayeurInvalideException;
            }

            if (Inscription::where('eleve_id', $eleveId)->where('annee_scolaire_id', $anneeScolaireId)->exists()) {
                throw new DoubleInscriptionException($eleveId, $anneeScolaireId);
            }

            if ($this->classeService->getPlacesRestantes($classeId) <= 0) {
                throw new CapaciteClasseDepasseeException($classeId);
            }

            $inscription = Inscription::create([
                'eleve_id' => $eleveId,
                'classe_id' => $classeId,
                'annee_scolaire_id' => $anneeScolaireId,
                'type' => Inscription::query()->where('eleve_id', $eleveId)->exists()
                    ? 'reinscription'
                    : 'inscription',
                'date_inscription' => now(),
                'statut' => 'en_attente_versement',
            ]);

            $this->facturation->creerFactureProvisoire(
                inscriptionId: $inscription->id,
                eleveId: $eleveId,
                niveauId: $this->classeService->getNiveauId($classeId),
                classeId: $classeId,
                anneeScolaireId: $anneeScolaireId,
                parentId: $parentId,
                fraisOptionnels: $fraisOptionnels,
                montantVerse: $montantVerse,
                identite: [
                    'eleve' => trim("{$eleve->prenom} {$eleve->nom}"),
                    'parent' => trim("{$parent->prenom} {$parent->nom}"),
                    'email' => $parent->email,
                ],
            );

            $this->auditService->enregistrerAvecMotif(
                $inscription,
                "Préinscription de l'élève #{$eleveId} en attente de versement",
                'facture_provisoire_emise',
            );

            return $inscription->id;
        });
    }

    public function preparerEtDemarrerPreinscription(?int $eleveId, array $eleve, ?int $parentId, array $parent, string $lienParente, int $classeId, int $anneeScolaireId, array $fraisOptionnels = [], ?float $montantVerse = null): int
    {
        return DB::transaction(function () use ($eleveId, $eleve, $parentId, $parent, $lienParente, $classeId, $anneeScolaireId, $fraisOptionnels, $montantVerse): int {
            $dossierEleve = $eleveId
                ? Eleve::query()->findOrFail($eleveId)
                : Eleve::query()->create([...$eleve, 'statut' => 'prospect']);

            $dossierParent = $parentId
                ? ParentTuteur::query()->findOrFail($parentId)
                : ParentTuteur::query()->create([...$parent, 'portail_actif' => true]);

            $dossierEleve->parentsTuteurs()->syncWithoutDetaching([
                $dossierParent->id => [
                    'lien' => $lienParente,
                    'responsable_legal' => true,
                    'responsable_paiement' => true,
                    'autorise_recuperation' => true,
                ],
            ]);

            return $this->demarrerPreinscription(
                $dossierEleve->id,
                $classeId,
                $anneeScolaireId,
                $dossierParent->id,
                $fraisOptionnels,
                $montantVerse,
            );
        });
    }

    /**
     * Même préparation que preparerEtDemarrerPreinscription() (dossier
     * élève + dossier parent, liés), mais valide l'inscription tout de
     * suite via inscrire() au lieu de passer par le module Finances.
     */
    public function preparerEtInscrire(?int $eleveId, array $eleve, ?int $parentId, array $parent, string $lienParente, int $classeId, int $anneeScolaireId): int
    {
        return DB::transaction(function () use ($eleveId, $eleve, $parentId, $parent, $lienParente, $classeId, $anneeScolaireId): int {
            $dossierEleve = $eleveId
                ? Eleve::query()->findOrFail($eleveId)
                : Eleve::query()->create([...$eleve, 'statut' => 'prospect']);

            $dossierParent = $parentId
                ? ParentTuteur::query()->findOrFail($parentId)
                : ParentTuteur::query()->create([...$parent, 'portail_actif' => true]);

            $dossierEleve->parentsTuteurs()->syncWithoutDetaching([
                $dossierParent->id => [
                    'lien' => $lienParente,
                    'responsable_legal' => true,
                    'responsable_paiement' => true,
                    'autorise_recuperation' => true,
                ],
            ]);

            return $this->inscrire($dossierEleve->id, $classeId, $anneeScolaireId);
        });
    }

    public function validerApresPaiement(int $inscriptionId): void
    {
        DB::transaction(function () use ($inscriptionId): void {
            $inscription = Inscription::query()->lockForUpdate()->findOrFail($inscriptionId);
            if ($inscription->statut === 'validee') {
                return;
            }

            $eleve = Eleve::query()->lockForUpdate()->findOrFail($inscription->eleve_id);
            if (empty($eleve->matricule_permanent)) {
                $compteur = CompteurMatricule::query()->where('annee_scolaire_id', $inscription->annee_scolaire_id)->lockForUpdate()->first()
                    ?? CompteurMatricule::create(['annee_scolaire_id' => $inscription->annee_scolaire_id, 'dernier_numero' => 0]);
                $compteur->increment('dernier_numero');
                $eleve->matricule_permanent = 'AMB-'.date('Y').'-'.str_pad((string) $compteur->dernier_numero, 6, '0', STR_PAD_LEFT);
            }

            $eleve->statut = 'inscrit';
            $eleve->save();
            $inscription->update(['statut' => 'validee']);

            $this->auditService->enregistrerAvecMotif($inscription, 'Inscription validée après paiement', 'versement_confirme_par_comptable');
        });
    }

    public function activerApresVersement(int $inscriptionId): void
    {
        $inscription = Inscription::findOrFail($inscriptionId);
        $inscription->update(['statut' => 'active']);
        $inscription->eleve()->update(['statut' => 'actif']);
    }

    public function estInscrit(int $eleveId, int $anneeScolaireId): bool
    {
        return Inscription::where('eleve_id', $eleveId)
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->where('statut', 'validee')
            ->exists();
    }

    public function getFraisRestants(int $inscriptionId): float
    {
        // TODO: le module Finances (Dev B) n'est pas encore livré. Une fois
        // FinancesContracts disponible côté Finances, Finances calculera et
        // exposera ce montant lui-même — Scolarité ne doit jamais recalculer
        // les frais. En attendant, valeur neutre pour ne pas bloquer les
        // modules qui consomment déjà ce Contract (ex: Qualité pédagogique).
        return 0.0;
    }

    public function getContexteFinancier(int $inscriptionId): array
    {
        $inscription = Inscription::query()->findOrFail($inscriptionId);

        return [
            'inscription_id' => (int) $inscription->id,
            'eleve_id' => (int) $inscription->eleve_id,
            'classe_id' => (int) $inscription->classe_id,
            'annee_scolaire_id' => (int) $inscription->annee_scolaire_id,
            'statut' => (string) $inscription->statut,
        ];
    }
}
