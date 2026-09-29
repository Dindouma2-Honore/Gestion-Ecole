<?php

declare(strict_types=1);

namespace App\Modules\Communication\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MessageParent extends Model
{
    protected $table = 'messages_parents';

    protected $fillable = [
        'type',
        'expediteur_id',
        'sujet',
        'contenu',
        'cible_type',
        'cible_id',
    ];

    public function expediteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'expediteur_id');
    }

    public function destinataires(): HasMany
    {
        return $this->hasMany(MessageDestinataire::class, 'message_id');
    }

    public function reponses(): HasMany
    {
        return $this->hasMany(ReponseParent::class, 'message_id');
    }
}
