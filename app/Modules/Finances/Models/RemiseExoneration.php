<?php

declare(strict_types=1);

namespace App\Modules\Finances\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * `eleve_id` référence un élève du module Scolarité mais n'a volontairement
 * PAS de relation Eloquent : ce module ne connaît un élève qu'à travers
 * `EleveServiceInterface` (existe(), getEleve(), getNiveauId()).
 */
class RemiseExoneration extends Model
{
    protected $table = 'remises_exonerations';

    protected $guarded = [];

    protected $casts = [
        'valeur' => 'decimal:2',
    ];

    public function approbateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approuve_par');
    }
}
