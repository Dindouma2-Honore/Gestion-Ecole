<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EleveContactUrgence extends Model
{
    protected $table = 'eleve_contacts_urgence';

    protected $fillable = ['eleve_id', 'nom', 'telephone', 'lien'];

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }
}
