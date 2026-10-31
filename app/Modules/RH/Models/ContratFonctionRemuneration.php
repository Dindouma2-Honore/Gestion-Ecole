<?php

declare(strict_types=1);

namespace App\Modules\RH\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContratFonctionRemuneration extends Model
{
    protected $table = 'contrat_fonction_remunerations';

    protected $fillable = [
        'contrat_id',
        'poste_administratif_id',
        'mode_remuneration',
        'montant',
        'date_effet',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'montant' => 'decimal:2',
            'date_effet' => 'date',
        ];
    }

    public function contrat(): BelongsTo
    {
        return $this->belongsTo(Contrat::class);
    }

    public function fonction(): BelongsTo
    {
        return $this->belongsTo(PosteAdministratif::class, 'poste_administratif_id');
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
