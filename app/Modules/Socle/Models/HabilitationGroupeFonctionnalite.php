<?php

declare(strict_types=1);

namespace App\Modules\Socle\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HabilitationGroupeFonctionnalite extends Model
{
    protected $table = 'habilitations_groupes_fonctionnalites';

    protected $fillable = ['groupe_id', 'fonctionnalite_id', 'actif', 'modifie_par', 'dernier_motif', 'modifie_le'];

    protected function casts(): array
    {
        return ['actif' => 'boolean', 'modifie_le' => 'datetime'];
    }

    public function groupe(): BelongsTo
    {
        return $this->belongsTo(Groupe::class);
    }

    public function fonctionnalite(): BelongsTo
    {
        return $this->belongsTo(Fonctionnalite::class);
    }

    public function auteurModification(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modifie_par');
    }
}
