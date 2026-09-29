<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Models;

use App\Modules\Scolarite\Contracts\EleveServiceInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Eleve extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'matricule_permanent', 'nom', 'prenom', 'date_naissance',
        'sexe', 'photo', 'statut',
    ];

    protected $casts = [
        'date_naissance' => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function (Eleve $eleve): void {
            if (blank($eleve->matricule_permanent)) {
                $eleve->matricule_permanent = app(EleveServiceInterface::class)->genererMatricule();
            }
        });
    }

    public function contactsUrgence(): HasMany
    {
        return $this->hasMany(EleveContactUrgence::class);
    }

    public function parentsTuteurs(): BelongsToMany
    {
        return $this->belongsToMany(ParentTuteur::class, 'eleve_parent', 'eleve_id', 'parent_id')
            ->withPivot(['lien', 'responsable_legal', 'responsable_paiement', 'autorise_recuperation'])
            ->withTimestamps();
    }

    public function inscriptions(): HasMany
    {
        return $this->hasMany(Inscription::class);
    }

    public function documentsAdmission(): HasMany
    {
        return $this->hasMany(DocumentEleve::class);
    }

    public function getDocumentsObligatoiresManquantsAttribute()
    {
        return TypeDocumentEleve::query()->where('actif', true)->where('obligatoire', true)
            ->whereNotIn('id', $this->documentsAdmission()->pluck('type_document_eleve_id'))->get();
    }
}
