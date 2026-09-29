<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Contracts;

use Illuminate\Support\Collection;

interface SeanceServiceInterface
{
    public function genererSeancesPourSemaine(int $anneeScolaireId, \DateTimeInterface $lundiDeLaSemaine): void;

    public function marquerCommencee(int $seanceId): void;

    public function marquerCommenceeSiNecessaire(int $seanceId): void;

    public function marquerDispensee(int $seanceId): void;

    // Peut lever SeanceIntrouvableException (voir Exceptions/) si la séance n'existe pas.
    public function annuler(int $seanceId, string $motif): void;

    public function reporter(int $seanceId, \DateTimeInterface $nouvelleDate): void;

    public function getSeancesDuJour(int $classeId, \DateTimeInterface $date): Collection;

    public function getSeancesSansProgression(\DateTimeInterface $depuis): Collection;

    public function existe(int $seanceId): bool;

    /**
     * Utilisé par le module Pédagogie (Progression pédagogique) pour savoir
     * si une séance a bien été dispensée avant d'accepter la saisie du
     * cahier de texte — sans jamais lire directement la table `seances`.
     *
     * Peut lever SeanceIntrouvableException (voir Exceptions/) si la séance n'existe pas.
     */
    public function getStatut(int $seanceId): string;

    /**
     * Utilisé par le module Pédagogie une fois la progression saisie pour
     * une séance, afin de mettre à jour le flag côté Assiduité sans y
     * accéder directement.
     */
    public function marquerProgressionRenseignee(int $seanceId): void;
}
