<?php

declare(strict_types=1);

namespace App\Modules\Socle\Models;

use App\Models\User;
use App\Modules\Socle\Contracts\HasWorkflow;
use App\Modules\Socle\Traits\HasWorkflowStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Tache extends Model implements HasWorkflow
{
    use HasWorkflowStatus;

    protected $table = 'taches';

    protected $guarded = [];

    protected $casts = [
        'echeance' => 'datetime',
    ];

    public function taskable(): MorphTo
    {
        return $this->morphTo();
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'createur_id');
    }

    public function historiqueStatuts(): HasMany
    {
        return $this->hasMany(TacheHistoriqueStatut::class);
    }

    public function validations(): HasMany
    {
        return $this->hasMany(TacheValidation::class)->orderBy('niveau_validation');
    }

    public function transitionsAutorisees(): array
    {
        return [
            'a_faire' => ['en_cours', 'en_attente_validation', 'cloturee'],
            'en_cours' => ['en_attente_validation', 'cloturee'],
            'en_attente_validation' => ['validee', 'rejetee'],
            'validee' => ['cloturee'],
            'rejetee' => ['en_cours'],
            'cloturee' => [],
        ];
    }

    public function etapeSuivanteAValider(): ?TacheValidation
    {
        return $this->validations()->where('statut', 'en_attente')->first();
    }
}
