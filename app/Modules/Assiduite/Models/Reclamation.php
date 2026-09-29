<?php

declare(strict_types=1);

namespace App\Modules\Assiduite\Models;

use App\Models\User;
use App\Modules\Socle\Contracts\HasWorkflow;
use App\Modules\Socle\Traits\HasWorkflowStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Reclamation extends Model implements HasWorkflow
{
    use HasWorkflowStatus;

    protected $table = 'reclamations';

    protected $fillable = [
        'type',
        'categorie',
        'description',
        'priorite',
        'signale_par_type',
        'signale_par_id',
        'responsable_id',
        'delai_reponse',
        'statut',
        'reponse',
        'tache_id',
    ];

    protected $casts = [
        'delai_reponse' => 'date',
    ];

    public function signalePar(): MorphTo
    {
        return $this->morphTo();
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    public function historiqueStatuts(): HasMany
    {
        return $this->hasMany(ReclamationHistoriqueStatut::class, 'reclamation_id');
    }

    public function transitionsAutorisees(): array
    {
        return [
            'ouverte' => ['affectee'],
            'affectee' => ['en_cours'],
            'en_cours' => ['resolue'],
            'resolue' => ['cloturee', 'en_cours'],
            'cloturee' => [],
        ];
    }
}
