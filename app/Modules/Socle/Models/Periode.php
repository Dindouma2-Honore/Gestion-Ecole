<?php

declare(strict_types=1);

namespace App\Modules\Socle\Models;

use App\Modules\Socle\Contracts\RattacheAAnneeScolaire;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Periode extends Model implements RattacheAAnneeScolaire
{
    protected $table = 'periodes';

    protected $fillable = ['annee_scolaire_id', 'libelle', 'nom', 'type', 'date_debut', 'date_fin', 'ordre', 'cloturee'];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
        'cloturee' => 'boolean',
    ];

    public function anneeScolaire(): BelongsTo
    {
        return $this->belongsTo(AnneeScolaire::class);
    }

    public function getAnneeScolaireId(): int
    {
        return (int) $this->annee_scolaire_id;
    }

    protected static function booted(): void
    {
        static::saving(function (self $periode): void {
            $periode->nom = $periode->libelle ?? $periode->nom;
            $periode->libelle = $periode->libelle ?? $periode->nom;
        });
    }
}
