<?php

declare(strict_types=1);

namespace App\Modules\VieScolaire\Filament\Pages;

use App\Modules\VieScolaire\Filament\Resources\DossierSanteResource;
use App\Modules\VieScolaire\Filament\Resources\ReclamationResource;
use App\Modules\VieScolaire\Filament\Resources\SortieEleveResource;
use App\Modules\VieScolaire\Filament\Resources\VisiteInfirmerieResource;
use App\Modules\VieScolaire\Filament\Resources\VisiteurResource;
use Filament\Pages\Page;

class VieScolaireDashboard extends Page
{
    protected static ?string $slug = 'vie-scolaire';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static string|\UnitEnum|null $navigationGroup = 'Vie scolaire';

    protected static ?string $navigationLabel = 'Tableau de bord';

    protected static ?string $title = 'Vie scolaire et sécurité';

    protected string $view = 'filament.pages.module-placeholder';

    public function getModuleIcon(): string
    {
        return 'heroicon-o-shield-check';
    }

    public function getModuleDescription(): string
    {
        return 'Gérez les sorties des élèves, visiteurs, santé et réclamations depuis un espace unique.';
    }

    public function getModuleLinks(): array
    {
        return [
            [
                'label' => 'Sorties des élèves',
                'description' => 'Contrôle et historique des sorties',
                'icon' => 'heroicon-o-arrow-right-on-rectangle',
                'url' => SortieEleveResource::getUrl('index'),
            ],
            [
                'label' => 'Visiteurs',
                'description' => 'Registre des entrées et sorties',
                'icon' => 'heroicon-o-identification',
                'url' => VisiteurResource::getUrl('index'),
            ],
            [
                'label' => 'Dossiers santé',
                'description' => 'Données médicales strictement protégées',
                'icon' => 'heroicon-o-heart',
                'url' => DossierSanteResource::getUrl('index'),
            ],
            [
                'label' => 'Visites infirmerie',
                'description' => 'Suivi des passages et incidents de santé',
                'icon' => 'heroicon-o-plus-circle',
                'url' => VisiteInfirmerieResource::getUrl('index'),
            ],
            [
                'label' => 'Incidents et réclamations',
                'description' => 'Signalement, affectation et suivi',
                'icon' => 'heroicon-o-exclamation-triangle',
                'url' => ReclamationResource::getUrl('index'),
            ],
        ];
    }
}
