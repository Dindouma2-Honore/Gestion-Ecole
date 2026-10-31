<?php

declare(strict_types=1);

namespace App\Modules\Socle\Models;

use App\Models\User;
use App\Modules\Scolarite\Contracts\AcademicStructureReadContract;
use App\Modules\Scolarite\Data\AcademicStructureItemDTO;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reunion extends Model
{
    protected $table = 'reunions';

    protected $guarded = [];

    protected $casts = [
        'date_heure' => 'datetime',
    ];

    public function getNiveauAttribute(): ?AcademicStructureItemDTO
    {
        return $this->niveau_id === null ? null : app(AcademicStructureReadContract::class)->niveau((int) $this->niveau_id);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ReunionParticipant::class);
    }

    public function ordreDuJour(): HasMany
    {
        return $this->hasMany(ReunionOrdreDuJour::class)->orderBy('ordre');
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(Decision::class);
    }
}
