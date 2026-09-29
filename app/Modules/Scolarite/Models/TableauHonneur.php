<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Models;

use App\Modules\Socle\Contracts\DocumentServiceContract;
use App\Modules\Socle\Contracts\RattacheAAnneeScolaire;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TableauHonneur extends Model implements RattacheAAnneeScolaire
{
    protected $table = 'tableau_honneur';

    protected $fillable = ['eleve_id', 'periode_id', 'annee_scolaire_id', 'mention', 'compose_par', 'document_id'];

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    /**
     * `documents` appartient au Socle (Models\*) : deptrac interdit de
     * l'importer directement ici — passage obligé par le Contract.
     */
    public function getUrlDocument(): ?string
    {
        return app(DocumentServiceContract::class)->getUrlTelechargement($this->document_id);
    }

    public function getAnneeScolaireId(): int
    {
        return (int) $this->annee_scolaire_id;
    }
}
