<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Models;

use App\Modules\Socle\Contracts\RattacheAAnneeScolaire;
use App\Modules\Socle\Contracts\ScopedByNiveau;
use App\Modules\Socle\Contracts\Scopes\NiveauScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Classe extends Model implements RattacheAAnneeScolaire, ScopedByNiveau
{
    protected $fillable = [
        'nom', 'code', 'niveau_id', 'filiere_id', 'section_id', 'salle_principale_id',
        'annee_scolaire_id', 'capacite_max', 'statut', 'professeur_principal_id', 'frais',
    ];

    protected $casts = [
        'frais' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new NiveauScope);

        static::creating(function (Classe $classe): void {
            if (filled($classe->code)) {
                return;
            }

            $base = Str::upper(Str::slug((string) $classe->nom));
            $code = $base;
            $suffixe = 2;
            while (self::query()->where('annee_scolaire_id', $classe->annee_scolaire_id)->where('code', $code)->exists()) {
                $code = $base.'-'.$suffixe++;
            }
            $classe->code = $code;
        });
    }

    public function inscriptions(): HasMany
    {
        return $this->hasMany(Inscription::class);
    }

    public function getAnneeScolaireId(): int
    {
        return (int) $this->annee_scolaire_id;
    }

    public function getNiveauScopeColumn(): string
    {
        return 'niveau_id';
    }

    public function grilleTarifaire(): HasMany
    {
        return $this->hasMany(GrilleTarifaire::class);
    }

    // niveau (Socle), annee_scolaire (Socle), professeur_principal (RH) :
    // autres modules -> pas de relation Eloquent directe.
}
