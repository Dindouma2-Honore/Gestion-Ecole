<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ParentTuteur extends Model
{
    protected $table = 'parents_tuteurs';

    protected $fillable = [
        'nom', 'prenom', 'profession', 'telephone', 'email', 'portail_actif',
    ];

    protected $casts = [
        'portail_actif' => 'boolean',
    ];

    public function eleves(): BelongsToMany
    {
        return $this->belongsToMany(Eleve::class, 'eleve_parent', 'parent_id', 'eleve_id')
            ->withPivot(['lien', 'responsable_legal', 'responsable_paiement', 'autorise_recuperation'])
            ->withTimestamps();
    }
}
