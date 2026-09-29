<?php

declare(strict_types=1);

namespace App\Modules\Socle\Services;

use App\Modules\Socle\Contracts\DocumentTemplateServiceContract;
use App\Modules\Socle\Models\DocumentTemplate;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;

class DocumentTemplateService implements DocumentTemplateServiceContract
{
    public function getTemplateActif(string $code): object
    {
        return DocumentTemplate::query()
            ->where('code', strtoupper(trim($code)))
            ->where('actif', true)
            ->whereDate('date_effet', '<=', today())
            ->latest('version')
            ->firstOrFail();
    }

    public function publierNouvelleVersion(string $code, array $attributs, ?int $createdBy = null): object
    {
        return DB::transaction(function () use ($code, $attributs, $createdBy): DocumentTemplate {
            $code = strtoupper(trim($code));
            $derniere = DocumentTemplate::query()->where('code', $code)->lockForUpdate()->latest('version')->first();
            DocumentTemplate::query()->where('code', $code)->where('actif', true)->update(['actif' => false]);

            return DocumentTemplate::query()->create(array_merge($attributs, [
                'code' => $code,
                'version' => ($derniere?->version ?? 0) + 1,
                'actif' => true,
                'created_by' => $createdBy,
            ]));
        });
    }

    public function render(object $template, array $donnees): string
    {
        return Blade::render((string) $template->contenu, $donnees, deleteCachedView: true);
    }
}
