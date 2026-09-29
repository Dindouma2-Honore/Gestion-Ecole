<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Models;

use App\Modules\Socle\Contracts\DocumentServiceContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FactureGeneree extends Model
{
    protected $table = 'factures_generees';

    protected $fillable = [
        'inscription_id', 'type', 'numero', 'montant', 'date_emission',
        'parent_id', 'nom_destinataire', 'telephone_destinataire', 'email_destinataire',
        'document_id', 'statut_envoi', 'canal_envoi', 'envoyee_le', 'echec_raison',
    ];

    protected $casts = [
        'montant' => 'decimal:2',
        'date_emission' => 'date',
        'envoyee_le' => 'datetime',
    ];

    public function inscription(): BelongsTo
    {
        return $this->belongsTo(Inscription::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ParentTuteur::class, 'parent_id');
    }

    /**
     * `documents` appartient au Socle (Models\*) : deptrac interdit de
     * l'importer directement ici — pas de relation Eloquent vers ce
     * modèle, seulement le passage obligé par le Contract
     * (DocumentServiceContract::getUrlTelechargement()), la seule porte
     * d'entrée sanctionnée.
     */
    public function getUrlDocument(): ?string
    {
        return app(DocumentServiceContract::class)->getUrlTelechargement($this->document_id);
    }

    public function estProvisoire(): bool
    {
        return $this->type === 'provisoire';
    }

    public function estDefinitive(): bool
    {
        return $this->type === 'definitive';
    }
}
