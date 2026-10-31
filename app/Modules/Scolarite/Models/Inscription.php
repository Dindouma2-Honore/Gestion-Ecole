<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Models;

use App\Models\User;
use App\Modules\Finances\Models\FacturePreinscription;
use App\Modules\Scolarite\Contracts\EnrollmentNotificationRecipient;
use App\Modules\Scolarite\Contracts\EnrollmentValidated;
use App\Modules\Scolarite\Contracts\InscriptionFacturationPort;
use App\Modules\Socle\Contracts\RattacheAAnneeScolaire;
use App\Modules\Socle\Contracts\ScopedByNiveau;
use App\Modules\Socle\Contracts\Scopes\NiveauScope;
use App\Modules\Socle\Models\AnneeScolaire;
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
        static::saved(function (self $inscription): void {
            if ($inscription->wasChanged('statut') || $inscription->wasRecentlyCreated) {
                if (in_array($inscription->statut, ['validee', 'active'], true)) {
                    $telephone = app(EnrollmentNotificationRecipient::class)->telephone($inscription->eleve_id);
                    event(new EnrollmentValidated($inscription->id, $telephone));
                }
            }
        });
    }

    protected $fillable = [
        'eleve_id', 'classe_id', 'annee_scolaire_id', 'type',
        'date_inscription', 'statut',
        'motif_annulation',
        'annulation_demandee_par', 'annulation_demandee_le',
        'annulation_visee_par', 'annulation_visee_le', 'visa_comptable_notes',
        'annulation_validee_par', 'annulation_validee_le',
    ];

    protected $casts = [
        'date_inscription' => 'date',
        'annulation_demandee_le' => 'datetime',
        'annulation_visee_le' => 'datetime',
        'annulation_validee_le' => 'datetime',
    ];

    public function demandeurAnnulation(): BelongsTo
    {
        return $this->belongsTo(User::class, 'annulation_demandee_par');
    }

    public function viseurAnnulation(): BelongsTo
    {
        return $this->belongsTo(User::class, 'annulation_visee_par');
    }

    public function validateurAnnulation(): BelongsTo
    {
        return $this->belongsTo(User::class, 'annulation_validee_par');
    }

    public function aVisaComptable(): bool
    {
        return $this->annulation_visee_par !== null || $this->annulation_visee_le !== null;
    }

    public function estEnAttenteAnnulation(): bool
    {
        return $this->statut === 'en_attente_annulation';
    }

    public function estAnnulee(): bool
    {
        return $this->statut === 'annulee';
    }

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

    public function transferts(): HasMany
    {
        return $this->hasMany(TransfertAffectation::class, 'inscription_id')->latest('date_transfert');
    }

    public function factures(): HasMany
    {
        return $this->hasMany(FactureGeneree::class);
    }

    public function facturePreinscription(): HasOne
    {
        return $this->hasOne(FacturePreinscription::class, 'inscription_id')->latestOfMany();
    }

    public function factureProvisoire(): HasOne
    {
        return $this->hasOne(FactureGeneree::class)->where('type', 'provisoire');
    }

    public function factureDefinitive(): HasOne
    {
        return $this->hasOne(FactureGeneree::class)->where('type', 'definitive');
    }

    public function anneeScolaire(): BelongsTo
    {
        return $this->belongsTo(AnneeScolaire::class, 'annee_scolaire_id');
    }

    public function getAnneeScolaireId(): int
    {
        return (int) $this->annee_scolaire_id;
    }

    public function getNiveauScopeColumn(): string
    {
        return 'classe.niveau_id';
    }

    protected ?array $memoizedFraisDivers = null;

    /** @return array<int, array{id: int, nom: string, montant: float}> */
    public function getFraisDiversSouscritsAttribute(): array
    {
        if ($this->memoizedFraisDivers !== null) {
            return $this->memoizedFraisDivers;
        }

        try {
            if ($this->relationLoaded('facturePreinscription') && $this->facturePreinscription && $this->facturePreinscription->relationLoaded('lignes')) {
                $divers = $this->facturePreinscription->lignes
                    ->filter(fn ($l) => str_starts_with((string) $l->type_frais, 'divers-'))
                    ->map(fn ($l) => [
                        'id' => (int) str_replace('divers-', '', (string) $l->type_frais),
                        'nom' => (string) $l->libelle,
                        'montant' => (float) $l->montant,
                    ])
                    ->values()
                    ->all();

                if (! empty($divers)) {
                    return $this->memoizedFraisDivers = $divers;
                }
            }

            return $this->memoizedFraisDivers = app(InscriptionFacturationPort::class)->getFraisDiversSouscrits((int) $this->id);
        } catch (\Throwable) {
            return $this->memoizedFraisDivers = [];
        }
    }

    public function getParentPrincipalAttribute(): ?ParentTuteur
    {
        $eleve = $this->eleve;
        if (! $eleve) {
            return null;
        }

        if ($eleve->relationLoaded('parentsTuteurs')) {
            return $eleve->parentsTuteurs->first(fn ($p) => (bool) ($p->pivot->responsable_paiement ?? false))
                ?? $eleve->parentsTuteurs->first();
        }

        return $eleve->parentsTuteurs()->wherePivot('responsable_paiement', true)->first()
            ?? $eleve->parentsTuteurs()->first();
    }

    public function getParentPrincipalNomAttribute(): ?string
    {
        $parent = $this->parent_principal;
        $eleve = $this->eleve;

        $nom = trim((string) ($parent?->nom ?: ($eleve?->nom_tuteur ?: ($eleve?->nom_pere ?: $eleve?->nom_mere))));

        return $nom !== '' ? $nom : null;
    }

    public function getParentPrincipalTelephoneAttribute(): ?string
    {
        $parent = $this->parent_principal;
        $eleve = $this->eleve;

        $tel = trim((string) ($parent?->telephone ?: $eleve?->telephone_tuteur));

        return $tel !== '' ? $tel : null;
    }
}
