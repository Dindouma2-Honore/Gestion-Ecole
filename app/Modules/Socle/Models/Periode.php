<?php

declare(strict_types=1);

namespace App\Modules\Socle\Models;

use App\Modules\Socle\Contracts\RattacheAAnneeScolaire;
use App\Modules\Socle\Enums\AcademicPeriodStatus;
use App\Modules\Socle\Enums\AcademicPeriodType;
use App\Modules\Socle\Services\AcademicPeriodValidator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Periode extends Model implements RattacheAAnneeScolaire
{
    protected $table = 'periodes';

    protected $attributes = [
        'status' => 'open',
    ];

    protected $fillable = [
        'annee_scolaire_id', 'parent_id', 'libelle', 'nom', 'type', 'date_debut', 'date_fin', 'ordre', 'cloturee', 'status',
    ];

    protected $casts = [
        'type' => AcademicPeriodType::class,
        'date_debut' => 'date',
        'date_fin' => 'date',
        'cloturee' => 'boolean',
        'status' => AcademicPeriodStatus::class,
    ];

    public function anneeScolaire(): BelongsTo
    {
        return $this->belongsTo(AnneeScolaire::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function sousPeriodes(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('ordre');
    }

    /** @deprecated Use status === AcademicPeriodStatus::CLOSED. */
    public function getIsClosedAttribute(): bool
    {
        return $this->status === AcademicPeriodStatus::CLOSED;
    }

    public function getAnneeScolaireId(): int
    {
        return (int) $this->annee_scolaire_id;
    }

    protected static function booted(): void
    {
        static::saving(function (self $period): void {
            if ($period->isDirty('cloturee') && ! $period->isDirty('status')) {
                $period->status = $period->cloturee ? AcademicPeriodStatus::CLOSED : AcademicPeriodStatus::OPEN;
            }
            $period->status ??= $period->cloturee ? AcademicPeriodStatus::CLOSED : AcademicPeriodStatus::OPEN;
            $period->cloturee = $period->status === AcademicPeriodStatus::CLOSED;
            $period->nom = $period->libelle ?? $period->nom;
            $period->libelle = $period->libelle ?? $period->nom;

            app(AcademicPeriodValidator::class)->validate($period);
        });
    }
}
