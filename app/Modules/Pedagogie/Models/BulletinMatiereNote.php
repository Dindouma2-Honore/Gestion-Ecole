<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BulletinMatiereNote extends Model
{
    protected $table = 'bulletin_matiere_notes';

    protected $fillable = [
        'bulletin_matiere_id',
        'titre_evaluation',
        'date_evaluation',
        'valeur',
        'bareme',
        'note_sur_20',
    ];

    protected $casts = [
        'date_evaluation' => 'date',
        'valeur' => 'float',
        'bareme' => 'float',
        'note_sur_20' => 'float',
    ];

    public function bulletinMatiere(): BelongsTo
    {
        return $this->belongsTo(BulletinMatiere::class);
    }
}
