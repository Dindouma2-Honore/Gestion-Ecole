<?php

declare(strict_types=1);

namespace App\Modules\Socle\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourrierDestinataire extends Model
{
    protected $table = 'courrier_destinataires';
    protected $guarded = [];
    public function courrier(): BelongsTo { return $this->belongsTo(Courrier::class); }
}
