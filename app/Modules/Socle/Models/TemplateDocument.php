<?php

declare(strict_types=1);

namespace App\Modules\Socle\Models;

use Illuminate\Database\Eloquent\Model;

class TemplateDocument extends Model
{
    protected $table = 'templates_documents';

    protected $fillable = ['code', 'nom', 'type', 'fichier_template', 'actif', 'version', 'contenu_html', 'variables_disponibles'];

    protected $casts = [
        'variables_disponibles' => 'array',
        'actif' => 'boolean',
        'version' => 'integer',
    ];
}
