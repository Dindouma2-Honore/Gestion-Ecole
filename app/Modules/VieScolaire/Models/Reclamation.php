<?php

declare(strict_types=1);

namespace App\Modules\VieScolaire\Models;

use App\Modules\Socle\Contracts\HasWorkflow;
use App\Modules\Socle\Traits\HasWorkflowStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reclamation extends Model implements HasWorkflow
{
    use HasWorkflowStatus;

    public $timestamps = false;

    protected $fillable = [
        'type', 'categorie', 'description', 'priorite',
        'signale_par_type', 'signale_par_id', 'responsable_id',
        'delai_reponse', 'statut', 'reponse', 'tache_id', 'created_at',
    ];
     protected $casts = [
    'delai_reponse' => 'date',
    'created_at' => 'datetime',
    ];
    public function historiqueStatuts(): HasMany
    {
        return $this->hasMany(ReclamationHistoriqueStatut::class);
    }

    public function transitionsAutorisees(): array
    {
        return [
            'ouverte' => ['affectee'],
            'affectee' => ['en_cours', 'resolue'],
            'en_cours' => ['resolue'],
            'resolue' => ['cloturee', 'en_cours'],
            'cloturee' => [],
        ];
    }
}
