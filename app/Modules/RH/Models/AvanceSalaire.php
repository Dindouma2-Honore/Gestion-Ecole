<?php

namespace App\Modules\RH\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvanceSalaire extends Model
{
    protected $table = 'avances_salaires';

    protected $fillable = [
        'employe_id',
        'montant',
        'date_demande',
        'motif',
        'demande_par',
        'statut',
        'montant_deja_deduit',
        'valide_par',
        'derogation_plafond',
        'motif_derogation',
        'validee_le',
        'decaissee_le',
    ];

    protected function casts(): array
    {
        return [
            'date_demande' => 'date',
            'montant' => 'decimal:2',
            'montant_deja_deduit' => 'decimal:2',
            'derogation_plafond' => 'boolean',
            'validee_le' => 'datetime',
            'decaissee_le' => 'datetime',
        ];
    }

    public function employe(): BelongsTo
    {
        return $this->belongsTo(Employe::class, 'employe_id');
    }

    public function validateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valide_par');
    }

    public function resteADeduire(): float
    {
        return max(0, (float) $this->montant - (float) $this->montant_deja_deduit);
    }
}
