<?php

declare(strict_types=1);

namespace App\Modules\Communication\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Annonce extends Model
{
    protected $table = 'annonces';

    protected $fillable = [
        'titre',
        'contenu',
        'cible_type',
        'cible_id',
        'date_publication',
        'date_expiration',
        'document_id',
        'publie_par',
    ];

    protected $casts = [
        'date_publication' => 'date',
        'date_expiration' => 'date',
    ];

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'publie_par');
    }

    public function meLectures(): HasMany
    {
        return $this->hasMany(AnnonceAccuseLecture::class, 'annonce_id');
    }
}
