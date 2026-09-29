<?php

namespace App\Modules\RH\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BulletinPaie extends Model
{
    protected $table = 'bulletins_paie';

    protected $fillable = [
        'employe_id',
        'contrat_id',
        'mois',
        'annee',
        'annee_scolaire_id',
        'salaire_base',
        'total_primes',
        'total_heures_supplementaires',
        'total_retenues',
        'total_cotisations',
        'avances_deduites',
        'net_a_payer',
        'statut',
        'date_paiement',
        'document_pdf_id',
    ];

    protected function casts(): array
    {
        return [
            'salaire_base' => 'decimal:2',
            'total_primes' => 'decimal:2',
            'total_heures_supplementaires' => 'decimal:2',
            'total_retenues' => 'decimal:2',
            'total_cotisations' => 'decimal:2',
            'avances_deduites' => 'decimal:2',
            'net_a_payer' => 'decimal:2',
            'date_paiement' => 'date',
        ];
    }

    public function employe(): BelongsTo
    {
        return $this->belongsTo(Employe::class, 'employe_id');
    }

    public function contrat(): BelongsTo
    {
        return $this->belongsTo(Contrat::class, 'contrat_id');
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(BulletinLigne::class, 'bulletin_paie_id');
    }
}
