<?php

namespace App\Modules\RH\Models;

use App\Models\User;
use App\Modules\Socle\Traits\AuditsSensitiveChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbsencePersonnel extends Model
{
    use AuditsSensitiveChanges;

    protected $table = 'absences_personnel';

    protected $guarded = [];

    protected $casts = ['date_debut' => 'date', 'date_fin' => 'date', 'date_validation' => 'datetime'];

    public function employe(): BelongsTo
    {
        return $this->belongsTo(Employe::class);
    }

    public function validePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valide_par');
    }

    public function creePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
