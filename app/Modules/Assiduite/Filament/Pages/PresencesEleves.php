<?php

declare(strict_types=1);

namespace App\Modules\Assiduite\Filament\Pages;

use App\Modules\Assiduite\Filament\Resources\AssiduiteJournaliereResource;

class PresencesEleves extends AssiduiteSection
{
    protected static ?string $slug = 'assiduite/presences-eleves';

    protected static ?string $title = 'Présences des élèves';

    public function getSectionDescription(): string
    {
        return 'Vue consolidée des appels pédagogiques par jour, classe, élève et période.';
    }

    public function getSectionLinks(): array
    {
        return [['label' => 'Présences consolidées', 'description' => 'Présences, absences, retards et statut journalier', 'icon' => 'heroicon-o-academic-cap', 'url' => AssiduiteJournaliereResource::getUrl('index', ['tableFilters' => ['personne_type' => ['value' => 'eleve']]])]];
    }
}
