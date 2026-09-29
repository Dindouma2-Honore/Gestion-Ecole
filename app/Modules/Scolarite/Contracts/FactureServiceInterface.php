<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Contracts;

interface FactureServiceInterface
{
    /**
     * Génère la facture provisoire d'une inscription qui vient d'être
     * créée (montant = somme des frais dus, voir FraisServiceContract).
     * Envoie automatiquement la facture par e-mail au parent payeur.
     * Échoue si une facture provisoire existe déjà pour cette inscription.
     *
     * Retourne l'identifiant de la facture créée.
     */
    public function genererProvisoire(int $inscriptionId): int;

    /**
     * Génère la facture définitive, une fois le versement confirmé (appelée
     * par InscriptionServiceInterface::activerApresVersement()). Échoue si
     * une facture définitive existe déjà pour cette inscription.
     */
    public function genererDefinitive(int $inscriptionId): int;

    /**
     * @return array{
     *     id: int, type: string, numero: string, montant: float, statut_envoi: string,
     *     telephone_destinataire: ?string, nom_destinataire: ?string,
     * }
     */
    public function getFacture(int $factureId): array;

    /**
     * À appeler par le futur module d'envoi WhatsApp une fois la facture
     * effectivement transmise au parent. Ne doit jamais être appelé
     * directement sur la table `factures_generees`.
     */
    public function marquerEnvoyee(int $factureId, string $canal = 'whatsapp'): void;

    /**
     * À appeler par le futur module d'envoi WhatsApp en cas d'échec de
     * transmission, avec une raison exploitable (ex: numéro invalide).
     */
    public function marquerEchecEnvoi(int $factureId, string $raison): void;
}
