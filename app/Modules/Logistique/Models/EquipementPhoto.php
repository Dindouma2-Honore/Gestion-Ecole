<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Models;

use App\Modules\Socle\Contracts\DocumentServiceContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipementPhoto extends Model
{
    protected $table = 'equipement_photos';

    protected $fillable = [
        'equipement_id',
        'document_id',
        'type',
        'description',
        'principal',
    ];

    protected $casts = [
        'principal' => 'boolean',
    ];

    public function equipement(): BelongsTo
    {
        return $this->belongsTo(Equipement::class);
    }

    public function url(): ?string
    {
        try {
            return app(DocumentServiceContract::class)->getUrlTelechargement($this->document_id);
        } catch (\Throwable) {
            return null;
        }
    }
}
