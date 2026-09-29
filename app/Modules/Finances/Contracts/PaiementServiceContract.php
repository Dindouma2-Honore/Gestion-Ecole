<?php

declare(strict_types=1);

namespace App\Modules\Finances\Contracts;

use Illuminate\Support\Collection;

interface PaiementServiceContract
{
    /**
     * @param  array<string, mixed>  $contexteRecu  Informations métier optionnelles à afficher sur le reçu.
     */
    public function enregistrerPaiement(int $eleveId, float $montant, string $mode, ?string $referenceMobileMoney = null, array $contexteRecu = []): object;

    /** @return array{nom:string, contenu:string} */
    public function getRecuPdf(int $paiementId): array;

    public function annulerPaiement(int $paiementId, string $motif): void;

    public function getTotalPaye(int $eleveId, int $anneeScolaireId): float;

    public function getResteAPayer(int $eleveId, int $anneeScolaireId): float;

    /**
     * @return Collection<int, array{id:int, numero_recu:string, montant:float, mode:string, statut:string, date:object, document_recu_id:?int, url_recu:?string}>
     */
    public function getHistoriquePaiements(int $eleveId): Collection;
}
