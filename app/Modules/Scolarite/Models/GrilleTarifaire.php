<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Models;

use App\Modules\Socle\Contracts\RattacheAAnneeScolaire;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrilleTarifaire extends Model implements RattacheAAnneeScolaire
{
    protected $table = 'grille_tarifaire';

    protected $fillable = ['frais_id', 'classe_id', 'annee_scolaire_id', 'montant'];

    protected $casts = [
        'montant' => 'decimal:2',
    ];

    public function getAnneeScolaireId(): int
    {
        return (int) $this->annee_scolaire_id;
    }

    public function frais(): BelongsTo
    {
        return $this->belongsTo(Frais::class);
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }
}
