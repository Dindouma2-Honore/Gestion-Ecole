<?php

namespace App\Modules\Pedagogie\Exceptions;

use Exception;

class ConflitEmploiDuTempsException extends Exception
{
    private const MESSAGES = [
        'enseignant_deja_occupe' => 'Cet enseignant est déjà planifié dans une autre classe sur ce créneau.',
        'enseignant_indisponible' => 'Cet enseignant est indisponible ou inactif dans les Ressources humaines.',
        'salle_deja_occupee' => 'Cette salle est déjà réservée sur ce créneau.',
        'classe_deja_occupee' => 'Cette classe possède déjà un cours sur ce créneau.',
        'volume_hebdomadaire_depasse' => 'Ce cours dépasserait le volume horaire hebdomadaire prévu pour l’offre pédagogique.',
    ];

    public function __construct(private readonly array $conflits)
    {
        parent::__construct('Conflit(s) détecté(s) : '.implode(' ', array_map(
            fn (string $conflit): string => self::MESSAGES[$conflit] ?? $conflit,
            $conflits,
        )));
    }

    public function getConflits(): array
    {
        return $this->conflits;
    }
}
