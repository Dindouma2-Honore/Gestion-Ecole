<?php

declare(strict_types=1);

namespace App\Modules\Communication\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class NotificationEnvoyee extends Model
{
    protected $table = 'notifications_envoyees';

    protected $fillable = [
        'canal',
        'code_template',
        'destinataire_type',
        'destinataire_id',
        'destinataire_contact',
        'contenu_final',
        'statut',
        'tentatives',
        'accuse_reception',
        'erreur_message',
        'envoyee_le',
    ];

    protected $casts = [
        'accuse_reception' => 'boolean',
        'tentatives' => 'integer',
        'envoyee_le' => 'datetime',
    ];

    public function fileAttente(): HasOne
    {
        return $this->hasOne(FileAttenteNotification::class, 'notification_id');
    }
}
