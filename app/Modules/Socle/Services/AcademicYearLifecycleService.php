<?php

declare(strict_types=1);

namespace App\Modules\Socle\Services;

use App\Modules\Socle\Enums\AcademicYearStatus;
use App\Modules\Socle\Exceptions\TransitionStatutInvalideException;
use App\Modules\Socle\Models\AnneeScolaire;
use Illuminate\Support\Facades\DB;

class AcademicYearLifecycleService
{
    public function activate(int $academicYearId): void
    {
        DB::transaction(function () use ($academicYearId): void {
            $year = AnneeScolaire::query()->lockForUpdate()->findOrFail($academicYearId);
            $this->ensureTransition($year, AcademicYearStatus::ACTIVE);

            AnneeScolaire::query()
                ->where('status', AcademicYearStatus::ACTIVE->value)
                ->whereKeyNot($year->id)
                ->lockForUpdate()
                ->get()
                ->each(function (AnneeScolaire $activeYear): void {
                    $activeYear->update(['status' => AcademicYearStatus::CLOTURE_EN_COURS]);
                    $activeYear->update(['status' => AcademicYearStatus::CLOTUREE]);
                });

            $year->update(['status' => AcademicYearStatus::ACTIVE]);
        });
    }

    public function beginClosure(int $academicYearId): void
    {
        $this->transition($academicYearId, AcademicYearStatus::CLOTURE_EN_COURS);
    }

    public function completeClosure(int $academicYearId): void
    {
        $this->transition($academicYearId, AcademicYearStatus::CLOTUREE);
    }

    public function close(int $academicYearId): void
    {
        DB::transaction(function () use ($academicYearId): void {
            $year = AnneeScolaire::query()->lockForUpdate()->findOrFail($academicYearId);
            $this->ensureTransition($year, AcademicYearStatus::CLOTURE_EN_COURS);
            $year->update(['status' => AcademicYearStatus::CLOTURE_EN_COURS]);
            $year->update(['status' => AcademicYearStatus::CLOTUREE]);
        });
    }

    public function archive(int $academicYearId): void
    {
        DB::transaction(function () use ($academicYearId): void {
            $year = AnneeScolaire::query()->lockForUpdate()->findOrFail($academicYearId);
            $this->ensureTransition($year, AcademicYearStatus::ARCHIVEE);

            // Une année clôturée est immuable au niveau Eloquent. Seul ce
            // cycle de vie transactionnel peut effectuer son archivage.
            DB::table('annees_scolaires')
                ->where('id', $year->id)
                ->where('status', AcademicYearStatus::CLOTUREE->value)
                ->update([
                    'status' => AcademicYearStatus::ARCHIVEE->value,
                    'statut' => AnneeScolaire::STATUT_ARCHIVEE,
                    'updated_at' => now(),
                ]);
        });
    }

    private function transition(int $academicYearId, AcademicYearStatus $destination): void
    {
        DB::transaction(function () use ($academicYearId, $destination): void {
            $year = AnneeScolaire::query()->lockForUpdate()->findOrFail($academicYearId);
            $this->ensureTransition($year, $destination);
            $year->update(['status' => $destination]);
        });
    }

    private function ensureTransition(AnneeScolaire $year, AcademicYearStatus $destination): void
    {
        $current = $year->status ?? AnneeScolaire::legacyStatusToCurrent((string) $year->statut);
        $allowed = match ($current) {
            AcademicYearStatus::PREPARATION => [AcademicYearStatus::ACTIVE],
            AcademicYearStatus::ACTIVE => [AcademicYearStatus::CLOTURE_EN_COURS],
            AcademicYearStatus::CLOTURE_EN_COURS => [AcademicYearStatus::CLOTUREE],
            AcademicYearStatus::CLOTUREE => [AcademicYearStatus::ARCHIVEE],
            AcademicYearStatus::ARCHIVEE => [],
        };

        if (! in_array($destination, $allowed, true)) {
            throw new TransitionStatutInvalideException($current->value, $destination->value);
        }
    }
}
