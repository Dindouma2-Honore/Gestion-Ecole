<?php

declare(strict_types=1);

namespace App\Modules\Communication\Models;

use App\Models\User;
use App\Modules\Socle\Contracts\HasWorkflow;
use App\Modules\Socle\Traits\HasWorkflowStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RendezVous extends Model implements HasWorkflow
{
    use HasWorkflowStatus;

    protected $table = 'rendez_vous';

    protected $fillable = [
        'parent_id',
        'responsable_id',
        'motif',
        'date_heure_demandee',
        'date_heure_confirmee',
        'statut',
        'compte_rendu',
    ];

    protected $casts = [
        'date_heure_demandee' => 'datetime',
        'date_heure_confirmee' => 'datetime',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo('App\Modules\Scolarite\Models\ParentTuteur', 'parent_id');
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    public function historiqueStatuts(): HasMany
    {
        return $this->hasMany(RendezVousHistoriqueStatut::class, 'rendez_vous_id');
    }

    public function transitionsAutorisees(): array
    {
        return [
            'demande' => ['confirme', 'annule'],
            'confirme' => ['effectue', 'annule'],
            'effectue' => [],
            'annule' => [],
        ];
    }
}
