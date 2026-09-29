<?php

declare(strict_types=1);

namespace App\Modules\RH\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EtatVirement extends Model
{
    protected $table = 'etats_virement';

    protected $fillable = [
        'mois',
        'annee',
        'date_generation',
        'genere_par',
        'document_id',
    ];

    protected function casts(): array
    {
        return [
            'date_generation' => 'datetime',
            'mois' => 'integer',
            'annee' => 'integer',
        ];
    }

    public function generateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'genere_par');
    }

    public function bulletins(): HasMany
    {
        return $this->hasMany(BulletinPaie::class, 'mois', 'mois')
            ->whereIn('statut', ['valide', 'paye']);
    }
}
