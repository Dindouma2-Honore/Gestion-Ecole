<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Models;

use App\Modules\Socle\Contracts\RattacheAAnneeScolaire;
use App\Modules\Socle\Contracts\ScopedByNiveau;
use App\Modules\Socle\Contracts\Scopes\NiveauScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Inscription extends Model implements RattacheAAnneeScolaire, ScopedByNiveau
{
    use SoftDeletes;

    protected static function booted(): void
    {
        // Directeur/Enseignant : ne voient que les inscriptions des classes
        // de leur niveau (chemin relationnel classe.niveau_id).
        static::addGlobalScope(new NiveauScope);
    }

    protected $fillable = [
        'eleve_id', 'classe_id', 'annee_scolaire_id', 'type',
        'date_inscription', 'statut',
    ];

    protected $casts = [
        'date_inscription' => 'date',
    ];

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    /** Charges effectivement rattachées à ce dossier d'inscription. */
    public function fraisCharges(): HasMany
    {
        return $this->hasMany(FraisEleve::class);
    }

    public function paiements(): HasMany
    {
        return $this->hasMany(Paiement::class);
    }

    public function factures(): HasMany
    {
        return $this->hasMany(FactureGeneree::class);
    }

    public function factureProvisoire(): HasOne
    {
        return $this->hasOne(FactureGeneree::class)->where('type', 'provisoire');
    }

    public function factureDefinitive(): HasOne
    {
        return $this->hasOne(FactureGeneree::class)->where('type', 'definitive');
    }

    // annee_scolaire (Socle) : pas de relation directe, accès via
    // Socle\Contracts\AnneeScolaireServiceInterface uniquement.

    public function getAnneeScolaireId(): int
    {
        return (int) $this->annee_scolaire_id;
    }

    public function getNiveauScopeColumn(): string
    {
        return 'classe.niveau_id';
    }
}
