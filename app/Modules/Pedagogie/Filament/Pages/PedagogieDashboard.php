<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Filament\Pages;

use App\Modules\Pedagogie\Filament\Resources\AnomalieAppelResource;
use App\Modules\Pedagogie\Filament\Resources\EmploiDuTempsResource;
use App\Modules\Pedagogie\Filament\Resources\EvaluationResource;
use App\Modules\Pedagogie\Filament\Resources\MatiereResource;
use App\Modules\Pedagogie\Filament\Resources\NoteResource;
use App\Modules\Pedagogie\Filament\Resources\PresenceResource;
use App\Modules\Pedagogie\Filament\Resources\ProgrammeResource;
use App\Modules\Pedagogie\Filament\Resources\SeanceResource;
use Filament\Pages\Page;

class PedagogieDashboard extends Page
{
    protected static ?string $slug = 'pedagogie';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static string|\UnitEnum|null $navigationGroup = 'Pédagogie';

    protected static ?string $navigationLabel = 'Tableau de bord';

    protected static ?string $title = 'Pédagogie';

    protected string $view = 'filament.pages.module-placeholder';

    public function getModuleIcon(): string
    {
        return 'heroicon-o-book-open';
    }

    public function getModuleDescription(): string
    {
        return 'Pilotez les matières, programmes, emplois du temps, séances et appels depuis cet espace.';
    }

    public function getModuleLinks(): array
    {
        return [
            ['label' => 'Matières', 'description' => 'Catalogue des matières enseignées', 'icon' => 'heroicon-o-book-open', 'url' => MatiereResource::getUrl('index')],
            ['label' => 'Évaluations', 'description' => 'Devoirs, examens et barèmes', 'icon' => 'heroicon-o-clipboard-document-check', 'url' => EvaluationResource::getUrl('index')],
            ['label' => 'Notes', 'description' => 'Saisie contrôlée et verrouillage des notes', 'icon' => 'heroicon-o-pencil-square', 'url' => NoteResource::getUrl('index')],
            ['label' => 'Programmes', 'description' => 'Programmes et objectifs pédagogiques', 'icon' => 'heroicon-o-clipboard-document-list', 'url' => ProgrammeResource::getUrl('index')],
            ['label' => 'Emplois du temps', 'description' => 'Cours, créneaux, classes et salles', 'icon' => 'heroicon-o-calendar-days', 'url' => EmploiDuTempsResource::getUrl('index')],
            ['label' => 'Séances', 'description' => 'Séances programmées et dispensées', 'icon' => 'heroicon-o-presentation-chart-bar', 'url' => SeanceResource::getUrl('index')],
            ['label' => 'Présences', 'description' => 'Appels et suivi de présence', 'icon' => 'heroicon-o-check-badge', 'url' => PresenceResource::getUrl('index')],
            ['label' => 'Anomalies d’appel', 'description' => 'Appels manquants ou en retard', 'icon' => 'heroicon-o-exclamation-triangle', 'url' => AnomalieAppelResource::getUrl('index')],
        ];
    }
}
