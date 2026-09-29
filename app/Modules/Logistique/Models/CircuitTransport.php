<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CircuitTransport extends Model
{
    protected $table = 'circuits_transport';

    public $timestamps = false;

    protected $fillable = [
        'nom', 'vehicule_id', 'chauffeur_id', 'accompagnateur_id',
    ];

    public function vehicule(): BelongsTo
    {
        return $this->belongsTo(Vehicule::class);
    }

    public function arrets(): HasMany
    {
        return $this->hasMany(ArretCircuit::class, 'circuit_id')->orderBy('ordre');
    }

    // chauffeur_id, accompagnateur_id : module Socle/RH (employés) -> pas
    // de relation Eloquent directe, même règle que partout ailleurs.
}
