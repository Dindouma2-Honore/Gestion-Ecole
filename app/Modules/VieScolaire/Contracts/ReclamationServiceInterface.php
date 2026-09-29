<?php

declare(strict_types=1);

namespace App\Modules\VieScolaire\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

interface ReclamationServiceInterface
{
    public function signaler(string $type, string $description, ?Model $signalePar, string $priorite = 'normale'): object;

    public function affecter(int $reclamationId, int $responsableId): void;

    public function repondre(int $reclamationId, string $reponse): void;

    public function cloturer(int $reclamationId): void;

    public function getReclamationsEnRetard(): Collection;

    public function getStatistiquesParCategorie(): Collection;
}
