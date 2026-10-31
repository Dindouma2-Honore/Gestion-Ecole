<?php

declare(strict_types=1);

namespace App\Modules\Socle\Services;

use App\Modules\Socle\Enums\AcademicPeriodType;
use App\Modules\Socle\Exceptions\InvalidAcademicPeriodException;
use App\Modules\Socle\Models\AnneeScolaire;
use App\Modules\Socle\Models\Periode;

class AcademicPeriodValidator
{
    public function validate(Periode $period): void
    {
        $this->validateDatesWithinAcademicYear($period);
        $this->validateParent($period);
        $this->validateSiblingOrder($period);
        $this->validateOverlaps($period);
    }

    private function validateDatesWithinAcademicYear(Periode $period): void
    {
        $year = AnneeScolaire::query()->findOrFail($period->annee_scolaire_id);
        if ($period->date_debut->lt($year->date_debut) || $period->date_fin->gt($year->date_fin)) {
            throw new InvalidAcademicPeriodException('Les dates de la période doivent appartenir à son année scolaire.');
        }
    }

    private function validateParent(Periode $period): void
    {
        if ($period->type !== AcademicPeriodType::SEQUENCE && $period->parent_id !== null) {
            throw new InvalidAcademicPeriodException('Seule une séquence peut avoir une période parente.');
        }

        if ($period->type === AcademicPeriodType::SEQUENCE && $period->parent_id === null) {
            throw new InvalidAcademicPeriodException('Une séquence doit être rattachée à un semestre ou un trimestre.');
        }

        if ($period->parent_id === null) {
            return;
        }

        $parent = Periode::query()->findOrFail($period->parent_id);
        if ($parent->id === $period->id || $parent->annee_scolaire_id !== $period->annee_scolaire_id) {
            throw new InvalidAcademicPeriodException('La période parente doit appartenir à la même année scolaire.');
        }
        if (! in_array($parent->type, [AcademicPeriodType::SEMESTER, AcademicPeriodType::TRIMESTER], true)) {
            throw new InvalidAcademicPeriodException('Une séquence ne peut dépendre que d’un semestre ou d’un trimestre.');
        }
        if ($period->date_debut->lt($parent->date_debut) || $period->date_fin->gt($parent->date_fin)) {
            throw new InvalidAcademicPeriodException('Les dates d’une séquence doivent être incluses dans celles de sa période parente.');
        }
    }

    private function validateSiblingOrder(Periode $period): void
    {
        $siblings = Periode::query()
            ->where('annee_scolaire_id', $period->annee_scolaire_id)
            ->where('type', $period->type->value)
            ->where('parent_id', $period->parent_id)
            ->when($period->exists, fn ($query) => $query->whereKeyNot($period->id));

        if ($siblings->clone()->where('ordre', $period->ordre)->exists()) {
            throw new InvalidAcademicPeriodException('L’ordre doit être unique parmi les périodes du même niveau.');
        }
        if ($siblings->clone()->where('ordre', '<', $period->ordre)->whereDate('date_debut', '>', $period->date_debut)->exists()
            || $siblings->clone()->where('ordre', '>', $period->ordre)->whereDate('date_debut', '<', $period->date_debut)->exists()) {
            throw new InvalidAcademicPeriodException('L’ordre des périodes doit suivre leur chronologie.');
        }
    }

    private function validateOverlaps(Periode $period): void
    {
        $overlap = Periode::query()
            ->where('annee_scolaire_id', $period->annee_scolaire_id)
            ->where('parent_id', $period->parent_id)
            ->when($period->exists, fn ($query) => $query->whereKeyNot($period->id))
            ->whereDate('date_debut', '<=', $period->date_fin)
            ->whereDate('date_fin', '>=', $period->date_debut)
            ->exists();

        if ($overlap) {
            throw new InvalidAcademicPeriodException('Deux périodes sœurs ne peuvent pas se chevaucher.');
        }
    }
}
