<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Models;

use App\Modules\Socle\Traits\AuditsSensitiveChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Note extends Model
{
    use AuditsSensitiveChanges;

    protected $fillable = [
        'evaluation_id', 'eleve_id', 'valeur', 'competence_niveau', 'appreciation',
        'absent', 'statut_presence', 'verrouillee', 'statut', 'soumis_par', 'soumis_le',
        'valide_par', 'valide_le', 'motif_correction', 'saisie_par', 'document_copie_id',
        'premiere_saisie_at', 'debloque_le', 'deblocage_consomme_le', 'deblocage_autorise_par', 'motif_deblocage',
    ];

    protected $casts = [
        'valeur' => 'float',
        'absent' => 'boolean',
        'verrouillee' => 'boolean',
        'premiere_saisie_at' => 'datetime',
        'soumis_le' => 'datetime',
        'valide_le' => 'datetime',
        'debloque_le' => 'datetime',
        'deblocage_consomme_le' => 'datetime',
    ];

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }
}
