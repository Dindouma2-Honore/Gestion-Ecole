<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Models;

use App\Models\User;
use App\Modules\Pedagogie\Enums\TypeDevoirEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Devoir extends Model
{
    protected $table = 'devoirs';

    protected $fillable = [
        'seance_id',
        'titre',
        'description',
        'type_devoir',
        'date_limite',
        'document_joint_id',
        'donne_par',
    ];

    protected $casts = [
        'type_devoir' => TypeDevoirEnum::class,
        'date_limite' => 'date',
    ];

    public function seance(): BelongsTo
    {
        return $this->belongsTo(Seance::class);
    }

    public function donneur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'donne_par');
    }
}
