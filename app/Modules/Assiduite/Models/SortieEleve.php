<?php

declare(strict_types=1);

namespace App\Modules\Assiduite\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SortieEleve extends Model
{
    protected $table = 'sorties_eleves';

    protected $fillable = [
        'eleve_id',
        'parent_id',
        'personne_autorisee_nom',
        'type',
        'heure_sortie',
        'justificatif_document_id',
        'remis_par',
    ];

    protected $casts = [
        'heure_sortie' => 'datetime',
    ];

    public function eleve(): BelongsTo
    {
        return $this->belongsTo('App\Modules\Scolarite\Models\Eleve', 'eleve_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo('App\Modules\Scolarite\Models\ParentTuteur', 'parent_id');
    }

    public function remisPar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'remis_par');
    }
}
