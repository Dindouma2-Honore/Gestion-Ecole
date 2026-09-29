<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Note extends Model
{
    protected $fillable = ['evaluation_id', 'eleve_id', 'valeur', 'absent', 'verrouillee', 'motif_correction', 'saisie_par', 'document_copie_id', 'premiere_saisie_at', 'debloque_le', 'deblocage_consomme_le', 'deblocage_autorise_par', 'motif_deblocage'];

    protected $casts = ['valeur' => 'float', 'absent' => 'boolean', 'verrouillee' => 'boolean', 'premiere_saisie_at' => 'datetime', 'debloque_le' => 'datetime', 'deblocage_consomme_le' => 'datetime'];

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }
}
