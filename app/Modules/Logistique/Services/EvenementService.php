<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Services;

use App\Modules\Logistique\Contracts\EvenementServiceInterface;
use App\Modules\Logistique\Exceptions\AutorisationParentaleManquanteException;
use App\Modules\Logistique\Models\Evenement;
use App\Modules\Logistique\Models\EvenementParticipant;
use App\Modules\Socle\Contracts\DocumentServiceContract;
use Illuminate\Http\UploadedFile;

class EvenementService implements EvenementServiceInterface
{
    public function __construct(
        private readonly DocumentServiceContract $document,
    ) {}

    public function creerEvenement(array $donnees): object
    {
        $evenement = Evenement::create([...$donnees, 'statut' => 'planifie']);

        // Si l'événement a un budget prévu, ça pourrait générer une ligne
        // budgétaire ou une demande de dépense (module Finances, E.43/E.44)
        // — à confirmer avec Joel selon le processus réel de validation des
        // sorties scolaires (le Fondateur valide-t-il ces dépenses via le
        // même circuit de seuils que les dépenses courantes ?).

        return $evenement;
    }

    public function inscrireParticipant(int $evenementId, string $participantType, int $participantId): void
    {
        $evenement = Evenement::findOrFail($evenementId);

        EvenementParticipant::create([
            'evenement_id' => $evenementId,
            'participant_type' => $participantType,
            'participant_id' => $participantId,
            'autorisation_parentale_recue' => ! $evenement->necessite_autorisation_parentale,
        ]);
    }

    public function confirmerParticipation(int $evenementParticipantId): void
    {
        $participant = EvenementParticipant::findOrFail($evenementParticipantId);

        if (! $participant->autorisation_parentale_recue) {
            throw new AutorisationParentaleManquanteException($evenementParticipantId);
        }

        // Participation confirmée — la logique métier de "confirmation"
        // dépend de ce que Joel veut concrètement ici (simple flag, ou état
        // de workflow complet type HasWorkflowStatus, comme utilisé pour
        // Reclamation dans le module VieScolaire, si le processus s'avère
        // plus riche à l'usage réel).
    }

    public function enregistrerAutorisationParentale(int $evenementParticipantId, UploadedFile $document): void
    {
        $participant = EvenementParticipant::findOrFail($evenementParticipantId);

        $documentEnregistre = $this->document->attacher($participant, $document, 'autorisation_parentale', 'interne');

        $participant->update([
            'autorisation_parentale_recue' => true,
            'document_autorisation_id' => $documentEnregistre->id,
        ]);
    }

    public function cloturerEvenement(int $evenementId, string $compteRendu): void
    {
        Evenement::where('id', $evenementId)->update(['statut' => 'termine']);
        // compteRendu pourrait être stocké via le module Socle (document)
        // plutôt qu'un champ texte simple, si Joel veut un vrai rapport
        // structuré — à trancher au moment de l'implémentation réelle.
    }

    // Ce module illustre bien que toute la documentation de ce projet n'a
    // jamais eu pour but de tout figer — plusieurs points ci-dessus
    // (validation budgétaire, structure du compte-rendu) restent
    // volontairement ouverts plutôt que fixés arbitrairement, car ils
    // dépendent de décisions métier non encore prises avec Joel.
}
