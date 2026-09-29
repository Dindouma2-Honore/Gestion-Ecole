<?php

declare(strict_types=1);

namespace App\Modules\Communication\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RendezVousHistoriqueStatut extends Model
{
    protected $table = 'rendez_vous_historique_statuts';

    protected $fillable = [
        'rendez_vous_id',
        'statut',
        'changed_by',
        'commentaire',
        'changed_at',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function rendezVous(): BelongsTo
    {
        return $this->belongsTo(RendezVous::class, 'rendez_vous_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
