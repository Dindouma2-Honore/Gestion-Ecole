<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BulletinMatiere extends Model
{
    protected $table = 'bulletin_matieres';

    protected $fillable = [
        'bulletin_id',
        'matiere_id',
        'moyenne',
        'coefficient',
        'rang',
        'moyenne_classe',
        'appreciation',
    ];

    protected $casts = [
        'moyenne' => 'float',
        'coefficient' => 'float',
        'rang' => 'integer',
        'moyenne_classe' => 'float',
    ];

    public function bulletin(): BelongsTo
    {
        return $this->belongsTo(Bulletin::class);
    }

    public function matiere(): BelongsTo
    {
        return $this->belongsTo(Matiere::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(BulletinMatiereNote::class);
    }
}
