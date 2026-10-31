<?php

declare(strict_types=1);

namespace App\Modules\Socle\Models;

use App\Modules\Socle\Enums\AcademicYearStatus;
use App\Modules\Socle\Enums\PeriodSystem;
use App\Modules\Socle\Exceptions\AnneeVerrouilleeException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class AnneeScolaire extends Model
{
    protected $table = 'annees_scolaires';

    protected $fillable = [
        'libelle', 'date_debut', 'date_fin', 'statut', 'status', 'period_system',
    ];

    protected $attributes = [
        'period_system' => 'trimester',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
        'status' => AcademicYearStatus::class,
        'period_system' => PeriodSystem::class,
    ];

    /** @deprecated Use AcademicYearStatus::PREPARATION through the lifecycle service. */
    public const STATUT_BROUILLON = 'brouillon';

    public const STATUT_ACTIVE = 'active';

    public const STATUT_CLOTUREE = 'cloturee';

    public const STATUT_ARCHIVEE = 'archivee';

    public function periodes(): HasMany
    {
        return $this->hasMany(Periode::class)->orderBy('ordre');
    }

    /** @deprecated Use status === AcademicYearStatus::ACTIVE. */
    public function getIsCurrentAttribute(): bool
    {
        return $this->status === AcademicYearStatus::ACTIVE;
    }

    public function isReadonly(): bool
    {
        return in_array($this->status, [AcademicYearStatus::CLOTUREE, AcademicYearStatus::ARCHIVEE], true);
    }

    public static function legacyStatusToCurrent(string $status): AcademicYearStatus
    {
        return match ($status) {
            self::STATUT_ACTIVE => AcademicYearStatus::ACTIVE,
            self::STATUT_CLOTUREE => AcademicYearStatus::CLOTUREE,
            self::STATUT_ARCHIVEE => AcademicYearStatus::ARCHIVEE,
            default => AcademicYearStatus::PREPARATION,
        };
    }

    public static function currentStatusToLegacy(AcademicYearStatus $status): string
    {
        return match ($status) {
            AcademicYearStatus::PREPARATION => self::STATUT_BROUILLON,
            AcademicYearStatus::ACTIVE => self::STATUT_ACTIVE,
            AcademicYearStatus::CLOTURE_EN_COURS, AcademicYearStatus::CLOTUREE => self::STATUT_CLOTUREE,
            AcademicYearStatus::ARCHIVEE => self::STATUT_ARCHIVEE,
        };
    }

    protected static function booted(): void
    {
        static::saving(function (self $year): void {
            $originalStatus = $year->getRawOriginal('status');
            if (in_array($originalStatus, [AcademicYearStatus::CLOTUREE->value, AcademicYearStatus::ARCHIVEE->value], true)) {
                throw new AnneeVerrouilleeException((string) $year->getOriginal('libelle'), (string) $originalStatus);
            }

            if ($year->isDirty('statut') && ! $year->isDirty('status')) {
                $year->status = self::legacyStatusToCurrent((string) $year->statut);
            }

            $year->status ??= self::legacyStatusToCurrent((string) ($year->statut ?? self::STATUT_BROUILLON));
            $year->statut = self::currentStatusToLegacy($year->status);
        });

        static::deleting(function (self $year): void {
            if ($year->isReadonly()) {
                throw new AnneeVerrouilleeException($year->libelle, $year->status->value);
            }
        });

        static::saved(function (): void {
            Cache::forget('annee_scolaire_courante');
            Cache::forget('annee_scolaire_courante_id_v2');
        });
        static::deleted(function (): void {
            Cache::forget('annee_scolaire_courante');
            Cache::forget('annee_scolaire_courante_id_v2');
        });
    }
}
