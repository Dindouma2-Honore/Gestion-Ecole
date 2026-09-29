<?php

declare(strict_types=1);

namespace App\Modules\Finances\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class GrilleSalariale extends Model
{
    protected $table = 'grilles_salariales';

    protected $guarded = [];

    protected $casts = ['salaire_base' => 'decimal:2', 'taux_horaire' => 'decimal:2', 'date_effet' => 'date', 'actif' => 'boolean'];

    public function getContexteAttribute(): string
    {
        return match ($this->base_calcul) {
            'matiere' => DB::table('matieres')->where('id', $this->matiere_id)->value('nom') ?? 'Matière non définie',
            'fonction' => DB::table('poste_administratifs')->where('id', $this->poste_administratif_id)->value('nom') ?? 'Fonction non définie',
            'tache' => $this->tache ?: 'Tâche non définie',
            default => 'Tout le personnel',
        };
    }

    public function getCategorieNomAttribute(): string
    {
        return DB::table('categories_personnel')->where('id', $this->categorie_personnel_id)->value('nom') ?? 'Non définie';
    }
}
