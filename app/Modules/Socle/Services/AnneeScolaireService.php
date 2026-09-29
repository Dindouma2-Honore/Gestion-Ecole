<?php

declare(strict_types=1);

namespace App\Modules\Socle\Services;

use App\Models\User;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use App\Modules\Socle\Contracts\TransfertElevesAnneeContract;
use App\Modules\Socle\Exceptions\AucuneAnneeActiveException;
use App\Modules\Socle\Exceptions\TransitionStatutInvalideException;
use App\Modules\Socle\Models\AnneeScolaire;
use App\Modules\Socle\Models\Periode;
use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AnneeScolaireService implements AnneeScolaireServiceContract
{
    private const ANNEE_ACTIVE_CACHE_KEY = 'annee_scolaire_courante_id_v2';

    public function __construct(private TransfertElevesAnneeContract $transfertEleves) {}

    public function anneeActiveId(): int
    {
        return $this->getAnneeCouranteId();
    }

    public function estActive(int $anneeScolaireId): bool
    {
        return $this->getAnneeCouranteId() === $anneeScolaireId;
    }

    public function periode(int $anneeScolaireId): array
    {
        $annee = AnneeScolaire::findOrFail($anneeScolaireId);

        return [
            'debut' => new DateTimeImmutable($annee->date_debut->format('Y-m-d')),
            'fin' => new DateTimeImmutable($annee->date_fin->format('Y-m-d')),
        ];
    }

    public function activerAnnee(int $anneeScolaireId): void
    {
        DB::transaction(function () use ($anneeScolaireId) {
            $nouvelle = AnneeScolaire::query()->lockForUpdate()->findOrFail($anneeScolaireId);

            if (! in_array($nouvelle->statut, [AnneeScolaire::STATUT_BROUILLON, AnneeScolaire::STATUT_ACTIVE], true)) {
                throw new TransitionStatutInvalideException($nouvelle->statut, AnneeScolaire::STATUT_ACTIVE);
            }

            AnneeScolaire::where('statut', 'active')
                ->whereKeyNot($anneeScolaireId)
                ->update(['statut' => 'cloturee']);

            $nouvelle->update(['statut' => AnneeScolaire::STATUT_ACTIVE]);
        });

        $this->oublierAnneeCourante();
    }

    public function cloturerAnnee(int $anneeScolaireId): void
    {
        $this->transitionner($anneeScolaireId, AnneeScolaire::STATUT_ACTIVE, AnneeScolaire::STATUT_CLOTUREE);
    }

    public function archiverAnnee(int $anneeScolaireId): void
    {
        $this->transitionner($anneeScolaireId, AnneeScolaire::STATUT_CLOTUREE, AnneeScolaire::STATUT_ARCHIVEE);
    }

    public function getAnneeCourante(): object
    {
        $anneeId = Cache::rememberForever(
            self::ANNEE_ACTIVE_CACHE_KEY,
            fn (): int => $this->chargerAnneeActiveId(),
        );

        $annee = AnneeScolaire::query()
            ->whereKey((int) $anneeId)
            ->where('statut', AnneeScolaire::STATUT_ACTIVE)
            ->first();

        if (! $annee) {
            $this->oublierAnneeCourante();
            $annee = AnneeScolaire::query()
                ->where('statut', AnneeScolaire::STATUT_ACTIVE)
                ->first();
        }

        if (! $annee) {
            throw new AucuneAnneeActiveException;
        }

        Cache::forever(self::ANNEE_ACTIVE_CACHE_KEY, $annee->id);

        return $annee;
    }

    public function getAnneeCouranteId(): int
    {
        return $this->getAnneeCourante()->id;
    }

    public function getAnneeScolaire(int $anneeScolaireId): object
    {
        return AnneeScolaire::findOrFail($anneeScolaireId);
    }

    public function getToutesLesAnnees(): array
    {
        return AnneeScolaire::orderByDesc('date_debut')->get()->toArray();
    }

    public function ecritureAutorisee(int $anneeScolaireId, User $user): bool
    {
        $statut = AnneeScolaire::query()->findOrFail($anneeScolaireId)->statut;

        if ($statut === AnneeScolaire::STATUT_ARCHIVEE) {
            return false;
        }

        return $statut !== AnneeScolaire::STATUT_CLOTUREE || $user->hasRole('Fondateur');
    }

    public function getPeriodes(int $anneeScolaireId): array
    {
        return Periode::where('annee_scolaire_id', $anneeScolaireId)
            ->orderBy('ordre')
            ->get()
            ->toArray();
    }

    public function transfererEleves(int $anneeSourceId, int $anneeDestinationId): void
    {
        AnneeScolaire::query()->findOrFail($anneeSourceId);
        AnneeScolaire::query()->findOrFail($anneeDestinationId);

        DB::transaction(fn () => $this->transfertEleves->transferer($anneeSourceId, $anneeDestinationId));
    }

    private function transitionner(int $anneeScolaireId, string $attendu, string $destination): void
    {
        DB::transaction(function () use ($anneeScolaireId, $attendu, $destination): void {
            $annee = AnneeScolaire::query()->lockForUpdate()->findOrFail($anneeScolaireId);

            if ($annee->statut !== $attendu) {
                throw new TransitionStatutInvalideException($annee->statut, $destination);
            }

            $annee->update(['statut' => $destination]);
        });

        $this->oublierAnneeCourante();
    }

    private function chargerAnneeActiveId(): int
    {
        $anneeId = AnneeScolaire::query()
            ->where('statut', AnneeScolaire::STATUT_ACTIVE)
            ->value('id');

        if ($anneeId === null) {
            throw new AucuneAnneeActiveException;
        }

        return (int) $anneeId;
    }

    private function oublierAnneeCourante(): void
    {
        Cache::forget(self::ANNEE_ACTIVE_CACHE_KEY);
        Cache::forget('annee_scolaire_courante');
    }
}
