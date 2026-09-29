<?php

declare(strict_types=1);

namespace App\Modules\Socle\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class DocumentTemplate extends Model
{
    protected $fillable = ['code', 'nom', 'type_document', 'module_proprietaire', 'cycle', 'contenu', 'mise_en_page', 'orientation', 'format_papier', 'version', 'actif', 'date_effet', 'created_by'];

    protected $casts = ['mise_en_page' => 'array', 'version' => 'integer', 'actif' => 'boolean', 'date_effet' => 'date'];

    protected static function booted(): void
    {
        static::updating(function (self $template): void {
            $champsModifies = array_diff(array_keys($template->getDirty()), ['actif', 'updated_at']);
            if ($champsModifies && $template->aEteUtilise()) {
                throw new \DomainException('Un modèle déjà utilisé est immuable : publiez une nouvelle version.');
            }
        });
        static::deleting(function (self $template): void {
            if ($template->aEteUtilise()) {
                throw new \DomainException('Un modèle déjà utilisé ne peut pas être supprimé.');
            }
        });
    }

    public function aEteUtilise(): bool
    {
        return DB::table('bulletins')->where('document_template_id', $this->getKey())->exists();
    }
}
