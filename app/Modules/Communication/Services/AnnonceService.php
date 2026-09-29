<?php

declare(strict_types=1);

namespace App\Modules\Communication\Services;

use App\Modules\Communication\Contracts\AnnonceServiceContract;
use App\Modules\Communication\Models\Annonce;
use App\Modules\Communication\Models\AnnonceAccuseLecture;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class AnnonceService implements AnnonceServiceContract
{
    public function publier(string $titre, string $contenu, string $cibleType, ?int $cibleId, ?DateTimeInterface $dateExpiration = null): object
    {
        return Annonce::create([
            'titre' => $titre,
            'contenu' => $contenu,
            'cible_type' => $cibleType,
            'cible_id' => $cibleId,
            'date_publication' => now(),
            'date_expiration' => $dateExpiration,
            'publie_par' => Auth::id() ?? 1,
        ]);
    }

    public function getAnnoncesActives(string $cibleType, ?int $cibleId = null): Collection
    {
        return Annonce::where(function ($q) use ($cibleType, $cibleId) {
            $q->where('cible_type', 'generale')
                ->orWhere(function ($q2) use ($cibleType, $cibleId) {
                    $q2->where('cible_type', $cibleType)->where('cible_id', $cibleId);
                });
        })
            ->where('date_publication', '<=', now())
            ->where(function ($q) {
                $q->whereNull('date_expiration')->orWhere('date_expiration', '>=', now());
            })
            ->orderByDesc('date_publication')
            ->get();
    }

    public function marquerLue(int $annonceId, int $userId): void
    {
        AnnonceAccuseLecture::firstOrCreate(
            ['annonce_id' => $annonceId, 'user_id' => $userId],
            ['lu_le' => now()]
        );
    }

    public function getTauxLecture(int $annonceId): float
    {
        $annonce = Annonce::find($annonceId);
        if (! $annonce) {
            return 0.0;
        }

        $lectures = AnnonceAccuseLecture::where('annonce_id', $annonceId)->count();

        // Standard calculation
        return (float) $lectures;
    }
}
