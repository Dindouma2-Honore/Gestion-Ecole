<?php

declare(strict_types=1);

namespace App\Modules\Socle\Services;

use App\Models\User;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use App\Modules\Socle\Contracts\TransfertElevesAnneeContract;
use App\Modules\Socle\Enums\AcademicYearStatus;
use App\Modules\Socle\Exceptions\AucuneAnneeActiveException;
use App\Modules\Socle\Models\AnneeScolaire;
use App\Modules\Socle\Models\Periode;
use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AnneeScolaireService implements AnneeScolaireServiceContract
{
    private const ANNEE_ACTIVE_CACHE_KEY = 'annee_scolaire_courante_id_v2';

    public function __construct(
        private TransfertElevesAnneeContract $transfertEleves,
        private AcademicYearLifecycleService $lifecycle,
    ) {}

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
        $this->lifecycle->activate($anneeScolaireId);
        $this->oublierAnneeCourante();
    }

    /** Démarre la clôture : l'année reste consultable, sans être modifiable. */
    public function demarrerClotureAnnee(int $anneeScolaireId): void
    {
        $this->lifecycle->beginClosure($anneeScolaireId);
        $this->oublierAnneeCourante();
    }

    /** Termine une clôture précédemment démarrée. */
    public function finaliserClotureAnnee(int $anneeScolaireId): void
    {
        $this->lifecycle->completeClosure($anneeScolaireId);
        $this->oublierAnneeCourante();
    }

    /**
     * Compatibilité legacy : l'ancienne action de clôture réalise désormais
     * les deux transitions contrôlées ACTIVE → CLOTURE_EN_COURS → CLOTUREE.
     */
    public function cloturerAnnee(int $anneeScolaireId): void
    {
        $this->lifecycle->close($anneeScolaireId);
        $this->oublierAnneeCourante();
    }

    public function archiverAnnee(int $anneeScolaireId): void
    {
        $this->lifecycle->archive($anneeScolaireId);
        $this->oublierAnneeCourante();
    }

    public function getAnneeCourante(): object
    {
        $anneeId = Cache::rememberForever(
            self::ANNEE_ACTIVE_CACHE_KEY,
            fn (): int => $this->chargerAnneeActiveId(),
        );

        $annee = AnneeScolaire::query()
            ->whereKey((int) $anneeId)
            ->where('status', AcademicYearStatus::ACTIVE->value)
            ->first();

        if (! $annee) {
            $this->oublierAnneeCourante();
            $annee = AnneeScolaire::query()
                ->where('status', AcademicYearStatus::ACTIVE->value)
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
        $annee = AnneeScolaire::query()->findOrFail($anneeScolaireId);

        return ! $annee->isReadonly();
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

    private function chargerAnneeActiveId(): int
    {
        $anneeId = AnneeScolaire::query()
            ->where('status', AcademicYearStatus::ACTIVE->value)
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
