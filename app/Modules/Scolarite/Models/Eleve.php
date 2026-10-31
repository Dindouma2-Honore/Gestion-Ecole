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
        'lieu_naissance', 'sexe', 'nationalite', 'photo', 'statut', 'observation',
        'ancien_etablissement', 'derniere_classe_frequente', 'email', 'nom_tuteur', 'telephone_tuteur', 'telephone_secondaire',
        'contact_urgence_nom', 'contact_urgence_telephone', 'contact_urgence_lien',
        'nom_pere', 'nom_mere',
        'redoublant', 'transfere', 'numero_decision', 'exonere_frais_examen', 'demissionne',
        'exclu_scolaire', 'info_sms', 'moyenne_legacy', 'exclu_inscription', 'bonus_scolaire',
    ];

    protected $casts = [
        'date_naissance' => 'date',
        'redoublant' => 'boolean', 'transfere' => 'boolean', 'exonere_frais_examen' => 'boolean',
        'demissionne' => 'boolean', 'exclu_scolaire' => 'boolean', 'info_sms' => 'boolean',
        'exclu_inscription' => 'boolean', 'moyenne_legacy' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (Eleve $eleve): void {
            if (blank($eleve->matricule_permanent)) {
                $eleve->matricule_permanent = app(EleveServiceInterface::class)->genererMatricule();
            }
        });

        static::saved(function (Eleve $eleve): void {
            $eleve->synchroniserParentTuteur();
            $eleve->synchroniserContactUrgence();
        });
    }

    public function synchroniserContactUrgence(): ?EleveContactUrgence
    {
        $nom = trim((string) $this->contact_urgence_nom);
        $telephone = trim((string) $this->contact_urgence_telephone);
        $lien = trim((string) $this->contact_urgence_lien);

        if ($nom === '' && $telephone === '') {
            return null;
        }

        if ($telephone === '' && preg_match('/(\+?[0-9]{8,15})/', $nom, $matches)) {
            $telephone = $matches[1];
        }

        $contact = $this->contactsUrgence()->first();
        if ($contact) {
            $contact->update([
                'nom' => $nom !== '' ? $nom : $contact->nom,
                'telephone' => $telephone !== '' ? $telephone : $contact->telephone,
                'lien' => $lien !== '' ? $lien : $contact->lien,
            ]);

            return $contact;
        }

        return $this->contactsUrgence()->create([
            'nom' => $nom,
            'telephone' => $telephone,
            'lien' => $lien,
        ]);
    }

    public function synchroniserParentTuteur(): ?ParentTuteur
    {
        $nomParent = trim((string) ($this->nom_tuteur ?: $this->nom_pere ?: $this->nom_mere));
        $telephone = trim((string) $this->telephone_tuteur);
        $email = filter_var($this->email, FILTER_VALIDATE_EMAIL) ? $this->email : null;
        $lien = $this->nom_tuteur ? 'tuteur' : ($this->nom_pere ? 'pere' : ($this->nom_mere ? 'mere' : 'tuteur'));

        if ($nomParent === '' && $telephone === '' && ! $email) {
            return null;
        }

        if ($nomParent === '') {
            $nomParent = 'Tuteur de '.trim("{$this->nom} {$this->prenom}");
        }

        $isGenericPlaceholder = in_array(mb_strtolower($nomParent), ['tuteur à compléter', 'tuteur a completer', 'tuteur', 'parent', 'inconnu'], true)
            || str_starts_with(mb_strtolower($nomParent), 'tuteur de ');

        // 1. Rechercher si un parent existant porte déjà ce nom (ou ce téléphone)
        $matchedParent = null;
        if (! $isGenericPlaceholder && $nomParent !== '') {
            $matchedParent = ParentTuteur::query()
                ->whereRaw('LOWER(TRIM(nom)) = ?', [mb_strtolower($nomParent)])
                ->orWhereRaw('LOWER(TRIM(CONCAT(COALESCE(prenom,""), " ", COALESCE(nom,"")))) = ?', [mb_strtolower($nomParent)])
                ->orWhereRaw('LOWER(TRIM(CONCAT(COALESCE(nom,""), " ", COALESCE(prenom,"")))) = ?', [mb_strtolower($nomParent)])
                ->first();
        }

        if (! $matchedParent && $telephone !== '') {
            $matchedParent = ParentTuteur::where('telephone', $telephone)->first();
        }

        $currentParent = $this->parentsTuteurs()
            ->wherePivot('responsable_paiement', true)
            ->first()
            ?? $this->parentsTuteurs()->first();

        // 2. Si un parent existant avec ce nom/téléphone est trouvé
        if ($matchedParent) {
            $updates = [];
            if ($telephone !== '' && blank($matchedParent->telephone)) {
                $updates['telephone'] = $telephone;
            }
            if ($email && blank($matchedParent->email)) {
                $updates['email'] = $email;
            }
            if (! empty($updates)) {
                $matchedParent->update($updates);
            }

            // Si l'élève était lié à un parent temporaire distinct, le détacher
            if ($currentParent && $currentParent->id !== $matchedParent->id) {
                $this->parentsTuteurs()->detach($currentParent->id);
                if ($currentParent->eleves()->count() === 0 && (in_array(mb_strtolower($currentParent->nom), ['tuteur à compléter', 'tuteur a completer', 'tuteur', 'parent'], true) || str_starts_with(mb_strtolower($currentParent->nom), 'tuteur de '))) {
                    $currentParent->delete();
                }
            }

            $this->parentsTuteurs()->syncWithoutDetaching([
                $matchedParent->id => [
                    'lien' => $lien,
                    'responsable_legal' => true,
                    'responsable_paiement' => true,
                    'autorise_recuperation' => true,
                ],
            ]);

            return $matchedParent->fresh();
        }

        // 3. Si l'élève avait déjà un parent et qu'aucun autre parent n'a ce nom
        if ($currentParent) {
            $updates = [];
            if ($nomParent !== '' && $currentParent->nom !== $nomParent) {
                $updates['nom'] = $nomParent;
            }
            if ($telephone !== '' && $currentParent->telephone !== $telephone) {
                $updates['telephone'] = $telephone;
            }
            if ($email && $currentParent->email !== $email) {
                $updates['email'] = $email;
            }
            if (! empty($updates)) {
                $currentParent->update($updates);
            }

            $this->parentsTuteurs()->updateExistingPivot($currentParent->id, [
                'lien' => $lien,
                'responsable_legal' => true,
                'responsable_paiement' => true,
                'autorise_recuperation' => true,
            ]);

            return $currentParent->fresh();
        }

        // 4. Création d'un nouveau parent uniquement si aucun parent avec ce nom n'existe
        $parent = ParentTuteur::create([
            'nom' => $nomParent,
            'prenom' => '',
            'telephone' => $telephone ?: null,
            'email' => $email,
            'portail_actif' => false,
        ]);

        $this->parentsTuteurs()->syncWithoutDetaching([
            $parent->id => [
                'lien' => $lien,
                'responsable_legal' => true,
                'responsable_paiement' => true,
                'autorise_recuperation' => true,
            ],
        ]);

        return $parent;
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
