<?php

declare(strict_types=1);

namespace App\Modules\Socle\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role;

class HabilitationRoleFonctionnalite extends Model
{
    protected $table = 'habilitations_roles_fonctionnalites';

    protected $fillable = ['role_id', 'fonctionnalite_id', 'actif', 'modifie_par', 'dernier_motif', 'modifie_le'];

    protected function casts(): array
    {
        return ['actif' => 'boolean', 'modifie_le' => 'datetime'];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
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
