<?php

declare(strict_types=1);

namespace App\Modules\Socle\Models;

use Illuminate\Database\Eloquent\Model;

class TemplateNotification extends Model
{
    protected $table = 'templates_notifications';

    protected $fillable = ['code', 'canal', 'sujet', 'contenu', 'actif', 'corps', 'variables_disponibles'];

    protected $casts = [
        'variables_disponibles' => 'array',
        'actif' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $template): void {
            // Maintient l'ancienne colonne pendant la transition additive A2.
            $template->corps = $template->contenu;
        });
    }
}
