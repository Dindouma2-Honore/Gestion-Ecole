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

    public function creer(string $nom, ?string $code, float $coefficient, ?string $description = null, string $type = Matiere::TYPE_MATIERE): array
    {
        if ($code !== null && Matiere::where('code', $code)->exists()) {
            throw CodeMatiereDejaUtiliseException::pourCode($code);
        }

        $matiere = new Matiere([
            'nom' => $nom,
            'code' => $code,
            'nature' => self::naturePourType($type),
            'coefficient' => $coefficient,
            'coefficient_defaut' => $coefficient,
            'description' => $description,
            'actif' => true,
        ]);
        $matiere->type = $type;
        $matiere->save();

        return $matiere->toArray();
    }

    private static function naturePourType(string $type): string
    {
        return match ($type) {
            Matiere::TYPE_DOMAINE_ACTIVITE => 'domaine',
            default => Matiere::TYPE_MATIERE,
        };
    }
}
