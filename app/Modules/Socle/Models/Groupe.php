<?php

declare(strict_types=1);

namespace App\Modules\Socle\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Groupe extends Model
{
    protected $table = 'groupes';

    protected $fillable = ['nom', 'description'];

    /** Official access-group membership relation. */
    public function membres(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'groupe_user');
    }

    /** Official access-group feature grants. */
    public function accessMembers(): BelongsToMany
    {
        return $this->membres();
    }

    public function fonctionnalites(): BelongsToMany
    {
        return $this->belongsToMany(Fonctionnalite::class, 'habilitations_groupes_fonctionnalites')
            ->withPivot(['actif', 'modifie_par', 'dernier_motif', 'modifie_le']);
    }

    public function isAccessGroup(): bool
    {
        return true;
    }
}
