<?php

declare(strict_types=1);

namespace App\Modules\Socle\Services;

use App\Modules\Socle\Contracts\AcademicContextContract;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use App\Modules\Socle\Data\AcademicContextDTO;
use App\Modules\Socle\Data\AcademicPeriodData;
use App\Modules\Socle\Data\AcademicYearData;
use App\Modules\Socle\Enums\AcademicPeriodType;
use App\Modules\Socle\Enums\AcademicYearStatus;
use App\Modules\Socle\Enums\PeriodSystem;
use App\Modules\Socle\Models\AnneeScolaire;
use App\Modules\Socle\Models\Periode;
use DateTimeImmutable;
use Illuminate\Contracts\Session\Session;

class AcademicContextManager implements AcademicContextContract
{
    private const SESSION_KEY = 'academic_context.selected_year_id';

    public function __construct(
        private readonly AnneeScolaireServiceContract $academicYears,
        private readonly Session $session,
    ) {}

    public function active(): AcademicYearData
    {
        return $this->toAcademicYearData($this->academicYears->getAnneeCourante());
    }

    public function selected(): AcademicYearData
    {
        $selectedYearId = $this->session->get($this->sessionKey());
        if ($selectedYearId !== null) {
            $selected = AnneeScolaire::query()->find((int) $selectedYearId);
            if ($selected !== null) {
                return $this->toAcademicYearData($selected);
            }

            $this->session->forget($this->sessionKey());
        }

        return $this->active();
    }

    public function select(int $academicYearId): AcademicContextDTO
    {
        AnneeScolaire::query()->findOrFail($academicYearId);
        $this->session->put($this->sessionKey(), $academicYearId);

        return $this->context();
    }

    public function status(): AcademicYearStatus
    {
        return $this->selected()->status;
    }

    public function isPreparation(): bool
    {
        return $this->status() === AcademicYearStatus::PREPARATION;
    }

    public function isActive(): bool
    {
        return $this->status() === AcademicYearStatus::ACTIVE;
    }

    public function isClosing(): bool
    {
        return $this->status() === AcademicYearStatus::CLOTURE_EN_COURS;
    }

    public function isClosed(): bool
    {
        return $this->status() === AcademicYearStatus::CLOTUREE;
    }

    public function isArchived(): bool
    {
        return $this->status() === AcademicYearStatus::ARCHIVEE;
    }

    public function isReadOnly(): bool
    {
        return $this->isClosed() || $this->isArchived();
    }

    public function context(): AcademicContextDTO
    {
        return new AcademicContextDTO($this->active(), $this->selected());
    }

    public function currentAcademicYear(): ?AcademicYearData
    {
        return $this->active();
    }

    public function academicYear(int $academicYearId): ?AcademicYearData
    {
        $year = AnneeScolaire::query()->find($academicYearId);

        return $year === null ? null : $this->toAcademicYearData($year);
    }

    public function academicYears(): array
    {
        return AnneeScolaire::query()
            ->orderByDesc('date_debut')
            ->get()
            ->map(fn (AnneeScolaire $year): AcademicYearData => $this->toAcademicYearData($year))
            ->all();
    }

    public function periodsFor(int $academicYearId): array
    {
        return Periode::query()
            ->where('annee_scolaire_id', $academicYearId)
            ->orderBy('ordre')
            ->get()
            ->map(fn (Periode $period): AcademicPeriodData => new AcademicPeriodData(
                id: (int) $period->id,
                academicYearId: (int) $period->annee_scolaire_id,
                label: (string) ($period->libelle ?? $period->nom),
                type: AcademicPeriodType::from((string) $period->type),
                startsAt: DateTimeImmutable::createFromInterface($period->date_debut),
                endsAt: DateTimeImmutable::createFromInterface($period->date_fin),
                order: (int) $period->ordre,
                parentId: $period->parent_id === null ? null : (int) $period->parent_id,
                closed: (bool) $period->cloturee,
            ))
            ->all();
    }

    private function sessionKey(): string
    {
        return self::SESSION_KEY.'.'.((string) auth()->id() ?: 'guest');
    }

    private function toAcademicYearData(AnneeScolaire $year): AcademicYearData
    {
        return new AcademicYearData(
            id: (int) $year->id,
            label: (string) $year->libelle,
            startsAt: DateTimeImmutable::createFromInterface($year->date_debut),
            endsAt: DateTimeImmutable::createFromInterface($year->date_fin),
            status: $year->status,
            periodSystem: $year->period_system ?? PeriodSystem::TRIMESTER,
        );
    }
}
