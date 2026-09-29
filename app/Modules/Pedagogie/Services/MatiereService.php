<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Services;

use App\Modules\Pedagogie\Contracts\MatiereServiceInterface;
use App\Modules\Pedagogie\Exceptions\CodeMatiereDejaUtiliseException;
use App\Modules\Pedagogie\Exceptions\MatiereIntrouvableException;
use App\Modules\Pedagogie\Models\Matiere;

class MatiereService implements MatiereServiceInterface
{
    public function existe(int $matiereId): bool
    {
        return Matiere::whereKey($matiereId)->exists();
    }

    public function getMatiere(int $matiereId): array
    {
        $matiere = Matiere::find($matiereId);

        if (! $matiere) {
            throw MatiereIntrouvableException::pourId($matiereId);
        }

        return [
            'id' => $matiere->id,
            'nom' => $matiere->nom,
            'code' => $matiere->code,
            'coefficient' => $matiere->coefficient_defaut,
        ];
    }

    public function toutesActives(): array
    {
        return Matiere::where('actif', true)
            ->orderBy('nom')
            ->get()
            ->map(fn (Matiere $matiere) => [
                'id' => $matiere->id,
                'nom' => $matiere->nom,
                'code' => $matiere->code,
                'coefficient' => $matiere->coefficient_defaut,
            ])
            ->all();
    }

    public function creer(string $nom, string $code, float $coefficient, ?string $description = null): array
    {
        if (Matiere::where('code', $code)->exists()) {
            throw CodeMatiereDejaUtiliseException::pourCode($code);
        }

        $matiere = Matiere::create([
            'nom' => $nom,
            'code' => $code,
            'coefficient' => $coefficient,
            'coefficient_defaut' => $coefficient,
            'description' => $description,
            'actif' => true,
        ]);

        return $matiere->toArray();
    }
}
