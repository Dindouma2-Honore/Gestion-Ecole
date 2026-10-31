<?php

declare(strict_types=1);

namespace App\Modules\Assiduite\Filament\Pages;

use App\Modules\Assiduite\Filament\Resources\AnomalieAssiduiteResource;
use App\Modules\Assiduite\Filament\Resources\JustificationAssiduiteResource;

class JustificationsAnomalies extends AssiduiteSection
{
    protected static ?string $slug = 'assiduite/justifications-anomalies';

    protected static ?string $title = 'Justifications & anomalies';

    public function getSectionDescription(): string
    {
        return 'Traitez les justificatifs et les incohérences détectées par le moteur.';
    }

    public function getSectionLinks(): array
    {
        return [
            ['label' => 'Justifications', 'description' => 'Soumission, acceptation ou refus', 'icon' => 'heroicon-o-document-check', 'url' => JustificationAssiduiteResource::getUrl()],
            ['label' => 'Anomalies', 'description' => 'Détection, vérification, correction et clôture', 'icon' => 'heroicon-o-exclamation-triangle', 'url' => AnomalieAssiduiteResource::getUrl()],
        ];
    }
}
