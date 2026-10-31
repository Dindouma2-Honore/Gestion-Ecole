<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Contracts;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

interface EquipementServiceInterface
{
    public function enregistrerEquipement(array $donnees): object;

    public function deplacer(int $equipementId, int $nouvelleSalleId, ?string $motif = null, ?int $responsableId = null): void;

    /** Peut lever GarantieExpireeException (avertissement, pas blocage) */
    public function declarerPanne(int $equipementId, string $description): object;

    public function mettreAuRebut(int $equipementId, string $motif): void;

    public function getValeurPatrimoine(): float;

    public function getEquipementsSousGarantie(): Collection;

    /**
     * Nature du matériel de l'équipement, déduite de sa catégorie de catalogue.
     *
     * @return string|null 'durable' | 'consommable', ou null si l'équipement
     *                     n'a pas encore de catégorie (donnée legacy)
     */
    public function natureEquipement(int $equipementId): ?string;

    public function modifierEtatPhysique(int $equipementId, string $etat): void;

    public function changerStatut(int $equipementId, string $nouveauStatut, ?string $motif = null): void;

    public function getHistoriqueStatuts(int $equipementId): Collection;

    public function ajouterPhoto(int $equipementId, UploadedFile $fichier, string $type = 'principale', ?string $description = null, bool $principal = false): object;

    public function definirPhotoPrincipale(int $equipementId, int $photoId): void;

    public function retirerPhoto(int $photoId): void;
}
