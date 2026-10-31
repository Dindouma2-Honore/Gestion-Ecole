<?php

namespace App\Modules\Pedagogie\Filament\Pages;

use App\Modules\Pedagogie\Filament\Resources\EvaluationResource;
use App\Modules\Pedagogie\Filament\Resources\NoteResource;

class EvaluationsResultats extends PedagogieSection
{
    protected static ?string $slug = 'pedagogie/evaluations-resultats';

    protected static ?string $title = 'Évaluations & notes';

    public function getSectionDescription(): string
    {
        return 'Préparez les évaluations, validez-les et sécurisez la saisie des notes.';
    }

    public function getSectionLinks(): array
    {
        return [
            ['label' => 'Évaluations & validations', 'description' => 'Devoirs, contrôles, compositions et examens', 'icon' => 'heroicon-o-clipboard-document-check', 'url' => EvaluationResource::getUrl()],
            ['label' => 'Saisie & validation des notes', 'description' => 'Saisie, validation et verrouillage des notes', 'icon' => 'heroicon-o-pencil-square', 'url' => NoteResource::getUrl()],
        ];
    }
}
