<?php

declare(strict_types=1);

namespace App\Modules\Assiduite\Filament\Pages;

use App\Modules\Assiduite\Models\AnomalieAssiduite;
use App\Modules\Assiduite\Models\AssiduiteJournaliere;
use App\Modules\Assiduite\Models\JustificationAssiduite;
use Filament\Pages\Page;

class AssiduiteDashboard extends Page
{
    protected static ?string $slug = 'assiduite';

    protected static bool $shouldRegisterNavigation = false;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static ?string $title = 'Vue d’assiduité';

    protected string $view = 'assiduite::filament.pages.dashboard';

    /** @return array<string, int> */
    public function indicateurs(): array
    {
        $assiduites = AssiduiteJournaliere::query()->whereDate('date_assiduite', today());

        return [
            'presents' => (clone $assiduites)->where('statut', 'present')->count(),
            'retards' => (clone $assiduites)->where('statut', 'retard')->count(),
            'absences' => (clone $assiduites)->whereIn('statut', ['absent_presume', 'absent_confirme'])->count(),
            'a_verifier' => (clone $assiduites)->where('statut', 'a_verifier')->count(),
            'anomalies' => AnomalieAssiduite::query()->whereIn('statut', ['detectee', 'a_verifier'])->count(),
            'justifications' => JustificationAssiduite::query()->where('decision', 'en_attente')->count(),
        ];
    }
}
