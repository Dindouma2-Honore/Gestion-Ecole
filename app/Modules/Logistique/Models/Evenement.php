<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Evenement extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'titre', 'description', 'date_debut', 'date_fin', 'lieu',
        'budget_prevu', 'necessite_transport', 'necessite_autorisation_parentale',
        'responsable_id', 'statut',
    ];

    protected $casts = [
        'date_debut' => 'datetime',
        'date_fin' => 'datetime',
        'budget_prevu' => 'decimal:2',
        'necessite_transport' => 'boolean',
        'necessite_autorisation_parentale' => 'boolean',
    ];

    public function participants(): HasMany
    {
        return $this->hasMany(EvenementParticipant::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(EvenementPhoto::class);
    }

    // responsable_id : users.id (module Socle) -> pas de relation Eloquent
    // directe, même règle que partout ailleurs dans le projet.
}
