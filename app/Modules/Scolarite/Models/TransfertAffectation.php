<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransfertAffectation extends Model
{
    protected $table = 'transferts_affectations';

    protected $fillable = [
        'inscription_id', 'eleve_id', 'classe_origine_id', 'classe_destination_id',
        'annee_scolaire_id', 'date_transfert', 'motif', 'effectue_par',
        'montant_scolarite_origine', 'montant_scolarite_destination',
        'solde_avant_transfert', 'solde_apres_transfert',
    ];

    protected $casts = [
        'date_transfert' => 'date',
        'montant_scolarite_origine' => 'decimal:2',
        'montant_scolarite_destination' => 'decimal:2',
        'solde_avant_transfert' => 'decimal:2',
        'solde_apres_transfert' => 'decimal:2',
    ];

    public function inscription(): BelongsTo { return $this->belongsTo(Inscription::class); }
    public function eleve(): BelongsTo { return $this->belongsTo(Eleve::class); }
    public function classeOrigine(): BelongsTo { return $this->belongsTo(Classe::class, 'classe_origine_id'); }
    public function classeDestination(): BelongsTo { return $this->belongsTo(Classe::class, 'classe_destination_id'); }
    public function auteur(): BelongsTo { return $this->belongsTo(User::class, 'effectue_par'); }
}
