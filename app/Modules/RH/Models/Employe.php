<?php

namespace App\Modules\RH\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Permission\Models\Role;

class Employe extends Model
{
    public ?string $motifChangementPoste = null;

    protected $table = 'employes';

    protected $fillable = [
        'user_id',
        'matricule',
        'nom',
        'prenom',
        'date_naissance',
        'sexe',
        'telephone',
        'email',
        'photo',
        'role_id',
        'poste_administratif_id',
        'categorie_anciennete_id',
        'contrat_id',
        'poste',
        'departement',
        'date_embauche',
        'niveau_id',
        'statut',
    ];

    protected function casts(): array
    {
        return [
            'date_naissance' => 'date',
            'date_embauche' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (self $employe): void {
            if ($employe->poste_administratif_id) {
                $employe->historiquePostes()->create(['poste_administratif_id' => $employe->poste_administratif_id, 'date_debut' => today(), 'motif' => 'Affectation initiale']);
            }
        });
        static::updating(function (self $employe): void {
            if ($employe->isDirty('poste_administratif_id') && trim((string) $employe->motifChangementPoste) === '') {
                throw new \DomainException('Le motif du changement de poste est obligatoire.');
            }
        });
        static::updated(function (self $employe): void {
            if (! $employe->wasChanged('poste_administratif_id')) {
                return;
            }
            $employe->historiquePostes()->whereNull('date_fin')->update(['date_fin' => today()->subDay()]);
            if ($employe->poste_administratif_id) {
                $employe->historiquePostes()->create(['poste_administratif_id' => $employe->poste_administratif_id, 'date_debut' => today(), 'motif' => $employe->motifChangementPoste]);
            }
        });
    }

    public function getNomCompletAttribute(): string
    {
        return trim("{$this->nom} {$this->prenom}");
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function niveau(): BelongsTo
    {
        return $this->belongsTo('App\Modules\Socle\Models\Niveau', 'niveau_id');
    }

    public function historiqueCarriere(): HasMany
    {
        return $this->hasMany(EmployeHistoriqueCarriere::class, 'employe_id');
    }

    public function enseignant(): HasOne
    {
        return $this->hasOne(Enseignant::class, 'employe_id');
    }

    public function contrats(): HasMany
    {
        return $this->hasMany(Contrat::class, 'employe_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function posteAdministratif(): BelongsTo
    {
        return $this->belongsTo(PosteAdministratif::class);
    }

    public function contratPrincipal(): BelongsTo
    {
        return $this->belongsTo(Contrat::class, 'contrat_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(CategoriePersonnel::class, 'personnel_categorie', 'employe_id', 'categorie_personnel_id');
    }

    public function categorieAnciennete(): BelongsTo
    {
        return $this->belongsTo(CategoriePersonnel::class, 'categorie_anciennete_id');
    }

    public function primesAttribuees(): HasMany
    {
        return $this->hasMany(PersonnelPrime::class);
    }

    public function absencesPersonnel(): HasMany
    {
        return $this->hasMany(AbsencePersonnel::class);
    }

    public function diplomes(): HasMany
    {
        return $this->hasMany(DiplomePersonnel::class, 'personnel_id');
    }

    public function historiquePostes(): HasMany
    {
        return $this->hasMany(PersonnelPosteHistorique::class);
    }

    public function conges(): HasMany
    {
        return $this->hasMany(Conge::class, 'employe_id');
    }

    public function sanctions(): HasMany
    {
        return $this->hasMany(SanctionPersonnel::class, 'employe_id');
    }

    public function pointages(): HasMany
    {
        return $this->hasMany(Pointage::class, 'employe_id');
    }

    public function bulletins(): HasMany
    {
        return $this->hasMany(BulletinPaie::class, 'employe_id');
    }
}
