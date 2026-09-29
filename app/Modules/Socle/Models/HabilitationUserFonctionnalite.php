<?php

declare(strict_types=1);

namespace App\Modules\Socle\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HabilitationUserFonctionnalite extends Model
{
    protected $table = 'habilitations_users_fonctionnalites';

    protected $fillable = ['user_id', 'fonctionnalite_id', 'actif', 'modifie_par', 'dernier_motif', 'modifie_le'];

    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
            'modifie_le' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
