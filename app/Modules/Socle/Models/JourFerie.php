<?php

declare(strict_types=1);

namespace App\Modules\Socle\Models;

use App\Modules\Scolarite\Contracts\AcademicStructureReadContract;
use App\Modules\Scolarite\Data\AcademicStructureItemDTO;
use Illuminate\Database\Eloquent\Model;

class JourFerie extends Model
{
    protected $table = 'jours_feries';

    protected $fillable = ['libelle', 'type', 'date', 'niveau_id', 'date_debut', 'date_fin', 'description', 'affecte_cours', 'affecte_personnel', 'etablissement_ferme', 'notifier', 'statut', 'recurrent'];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
        'date' => 'date',
        'recurrent' => 'boolean',
        'affecte_cours' => 'boolean', 'affecte_personnel' => 'boolean',
        'etablissement_ferme' => 'boolean', 'notifier' => 'boolean',
    ];

    public function getNiveauAttribute(): ?AcademicStructureItemDTO
    {
        return $this->niveau_id === null ? null : app(AcademicStructureReadContract::class)->niveau((int) $this->niveau_id);
    }
}
