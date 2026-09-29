<?php

namespace App\Modules\RH\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Enseignant extends Model
{
    protected $table = 'enseignants';

    protected $fillable = [
        'employe_id',
        'specialite',
        'statut_contractuel',
        'charge_horaire_hebdo',
    ];

    protected function casts(): array
    {
        return [
            'charge_horaire_hebdo' => 'decimal:1',
        ];
    }

    public function employe(): BelongsTo
    {
        return $this->belongsTo(Employe::class, 'employe_id');
    }

    public function affectations(): HasMany
    {
        return $this->hasMany(EnseignantMatiereNiveau::class, 'enseignant_id');
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(Inspection::class, 'enseignant_id');
    }

    public function remplacementsCommeAbsent(): HasMany
    {
        return $this->hasMany(Remplacement::class, 'enseignant_absent_id');
    }

    public function remplacementsCommeRemplacant(): HasMany
    {
        return $this->hasMany(Remplacement::class, 'enseignant_remplacant_id');
    }
}
