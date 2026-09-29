<?php

namespace App\Modules\RH\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SanctionPersonnel extends Model
{
    protected $table = 'sanctions_personnel';

    protected $fillable = [
        'employe_id',
        'type',
        'motif',
        'date_sanction',
        'duree_jours',
        'document_justificatif_id',
        'statut',
        'valide_par',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date_sanction' => 'date',
        ];
    }

    public function employe(): BelongsTo
    {
        return $this->belongsTo(Employe::class, 'employe_id');
    }

    public function validePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valide_par');
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
