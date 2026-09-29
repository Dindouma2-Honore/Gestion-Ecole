<?php

declare(strict_types=1);

namespace App\Modules\Assiduite\Contracts;

interface PresenceServiceInterface
{
    /**
     * Statut de présence calculé par le Moteur d'assiduité pour une
     * personne (élève ou membre du personnel) à une date donnée.
     *
     * @return string présent | en_retard | absent_presume | absent_confirme
     *                 | absence_justifiee | depart_anticipe | conge | mission
     */
    public function getStatutJour(int $personneId, \DateTimeInterface $date): string;

    public function enregistrerPointage(int $personneId, \DateTimeInterface $horodatage, string $terminal): void;
}
