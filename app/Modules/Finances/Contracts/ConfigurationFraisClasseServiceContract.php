<?php

declare(strict_types=1);

namespace App\Modules\Finances\Contracts;

interface ConfigurationFraisClasseServiceContract
{
    /** @param list<array{ordre:int,libelle:string,montant:float,date_echeance:string,actif?:bool}> $tranches */
    public function configurer(int $classeId, int $anneeScolaireId, float $montantTotal, array $tranches, array $options = []): object;

    /** @return array<string, mixed>|null */
    public function obtenir(int $classeId, int $anneeScolaireId): ?array;

    /** @return array{montant_total:float,total_paye:float,reste_a_payer:float,montant_en_retard:float,tranches:list<array<string,mixed>>} */
    public function situation(int $eleveId, int $classeId, int $anneeScolaireId): array;
}
