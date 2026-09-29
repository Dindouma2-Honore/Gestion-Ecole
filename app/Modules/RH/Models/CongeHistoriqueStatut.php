<?php

namespace App\Modules\RH\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CongeHistoriqueStatut extends Model
{
    protected $table = 'conge_historique_statuts';

    public $timestamps = false;

    protected $fillable = [
        'conge_id',
        'statut',
        'changed_by',
        'commentaire',
        'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
        ];
    }

    public function conge(): BelongsTo
    {
        return $this->belongsTo(Conge::class, 'conge_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
