<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Services;

use App\Modules\Scolarite\Contracts\InscriptionServiceInterface;
use App\Modules\Scolarite\Exceptions\CapaciteClasseDepasseeException;
use App\Modules\Scolarite\Exceptions\DoubleInscriptionException;
use App\Modules\Scolarite\Exceptions\ParentPayeurInvalideException;
use App\Modules\Scolarite\Models\Classe;
use App\Modules\Scolarite\Models\Inscription;
use App\Modules\Socle\Contracts\AuditServiceContract;
use App\Modules\Socle\Contracts\TransfertElevesAnneeContract;

/**
 * Implémentation réelle du port Socle (voir TransfertElevesAnneeContract) —
 * remplace le placeholder TransfertElevesIndisponible dans le conteneur, le
 * contrat étant désormais confirmé (voir ScolariteServiceProvider::register()).
 * Appelée par AnneeScolaireServiceContract::transfererEleves(), déjà dans sa
 * propre transaction (voir AnneeScolaireService) : pas besoin d'en ouvrir
 * une ici.
 *
 * Portée volontairement minimale et honnête : aucune règle de progression de
 * niveau (CP1 -> CP2...) n'est documentée par le Module 5 ni ailleurs dans
 * ce codebase (pas de champ "classe suivante" sur Classe, pas de
 * référentiel de progression côté Socle) — on ne l'invente pas.
 * `transferer()` réinscrit chaque élève encore activement inscrit (statut
 * `active`, versement confirmé — jamais une inscription en attente ou déjà
 * annulée) dans la classe de même nom côté année destination, via le même
 * circuit métier que toute réinscription
 * (InscriptionServiceInterface::reinscrire() : frais, facture provisoire...).
 * Un échec individuel (classe destination introuvable, capacité dépassée,
 * parent payeur invalide, élève déjà transféré) ne bloque jamais les autres
 * élèves du lot — il est journalisé via le Socle pour revue par la Direction
 * plutôt que de faire échouer tout le transfert.
 *
 * `InscriptionServiceInterface` est résolu paresseusement (jamais injecté
 * au constructeur) : ce service est lié à `TransfertElevesAnneeContract`,
 * dont dépend `AnneeScolaireService` (Socle) — et `InscriptionService`
 * dépend lui-même de `FactureServiceInterface`, qui dépend
 * d'`AnneeScolaireServiceContract`. Une injection au constructeur créerait
 * une dépendance circulaire au moment de la résolution par le conteneur
 * (déclenchée sur CHAQUE page Filament via la bannière "année active" que
 * Socle enregistre globalement) — même précaution que pour
 * `InscriptionService::getFraisRestants()`.
 */
class TransfertAnneeService implements TransfertElevesAnneeContract
{
    public function __construct(
        private readonly AuditServiceContract $auditService,
    ) {}

    public function transferer(int $anneeSourceId, int $anneeDestinationId): void
    {
        $inscriptionsActives = Inscription::where('annee_scolaire_id', $anneeSourceId)
            ->where('statut', 'active')
            ->with('classe')
            ->get();

        foreach ($inscriptionsActives as $inscription) {
            $this->transfererUnEleve($inscription, $anneeDestinationId);
        }
    }

    private function transfererUnEleve(Inscription $inscription, int $anneeDestinationId): void
    {
        $classeDestination = Classe::where('annee_scolaire_id', $anneeDestinationId)
            ->where('nom', $inscription->classe?->nom)
            ->first();

        if (! $classeDestination) {
            $this->auditService->enregistrer(
                $inscription,
                "Transfert annuel : aucune classe « {$inscription->classe?->nom} » trouvée pour l'année de destination — élève #{$inscription->eleve_id} non transféré",
            );

            return;
        }

        try {
            app(InscriptionServiceInterface::class)->reinscrire($inscription->eleve_id, $classeDestination->id, $anneeDestinationId);
        } catch (DoubleInscriptionException) {
            // Élève déjà transféré (transfert relancé) — idempotent, rien à faire.
        } catch (CapaciteClasseDepasseeException|ParentPayeurInvalideException $e) {
            $this->auditService->enregistrer(
                $inscription,
                "Transfert annuel : échec pour l'élève #{$inscription->eleve_id} vers la classe #{$classeDestination->id} ({$e->getMessage()})",
            );
        }
    }
}
