<?php

declare(strict_types=1);

namespace App\Modules\Socle\Models;

use App\Modules\Socle\Contracts\HasWorkflow;
use App\Modules\Socle\Traits\HasWorkflowStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Courrier extends Model implements HasWorkflow
{
    use HasWorkflowStatus;

    protected $table = 'courriers';

    protected $guarded = [];

    protected $casts = [
        'date_limite_reponse' => 'date',
        'cible_config' => 'array',
        'envoye_le' => 'datetime',
    ];

    public function historiqueStatuts(): HasMany
    {
        return $this->hasMany(CourrierHistoriqueStatut::class);
    }

    public function piecesJointes(): HasMany
    {
        return $this->hasMany(CourrierPieceJointe::class);
    }

    public function modele(): BelongsTo
    {
        return $this->belongsTo(CourrierModele::class, 'modele_id');
    }

    public function destinataires(): HasMany
    {
        return $this->hasMany(CourrierDestinataire::class);
    }

    public function transitionsAutorisees(): array
    {
        return [
            'recu' => ['affecte'],
            'affecte' => ['en_traitement'],
            'en_traitement' => ['repondu'],
            'repondu' => ['archive'],
            'archive' => [],
        ];
    }
}
