<?php

declare(strict_types=1);

namespace App\Modules\Communication\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReponseParent extends Model
{
    protected $table = 'reponses_parents';

    protected $fillable = [
        'message_id',
        'parent_id',
        'contenu',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(MessageParent::class, 'message_id');
    }
}
