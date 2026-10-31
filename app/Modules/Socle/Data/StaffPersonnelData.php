<?php

declare(strict_types=1);

namespace App\Modules\Socle\Data;

readonly class StaffPersonnelData
{
    public function __construct(public int $id, public string $nom, public string $prenom, public ?string $telephone, public string $statut, public ?int $userId, public ?string $role, public ?int $niveauId) {}

    public function nomComplet(): string
    {
        return trim($this->nom.' '.$this->prenom);
    }
}
