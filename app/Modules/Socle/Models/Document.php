<?php

declare(strict_types=1);

namespace App\Modules\Socle\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Document extends Model
{
    use LogsActivity;

    protected $table = 'documents';

    protected $guarded = [];

    protected $casts = [
        'date_expiration' => 'date',
    ];

    protected static function booted(): void
    {
        static::deleting(function (self $document): void {
            if ($document->categorie === 'recu_paiement') {
                throw new LogicException('Un reçu de paiement ne peut jamais être supprimé.');
            }
        });
    }

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('documents')
            ->logOnly(['nom', 'categorie', 'fichier_path', 'mime_type', 'taille', 'date_expiration', 'niveau_confidentialite', 'documentable_type', 'documentable_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName): string => "Document {$eventName}");
    }
}
