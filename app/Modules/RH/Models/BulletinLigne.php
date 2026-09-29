<?php

namespace App\Modules\RH\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BulletinLigne extends Model
{
    protected $table = 'bulletin_lignes';

    protected $fillable = [
        'bulletin_paie_id',
        'type',
        'libelle',
        'montant',
    ];

    protected function casts(): array
    {
        return [
            'montant' => 'decimal:2',
        ];
    }

    public function bulletin(): BelongsTo
    {
        return $this->belongsTo(BulletinPaie::class, 'bulletin_paie_id');
    }
}
