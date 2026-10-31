<?php

declare(strict_types=1);

namespace App\Modules\VieScolaire\Filament\Pages;

use App\Modules\VieScolaire\Filament\Resources\DossierSanteResource;
use App\Modules\VieScolaire\Filament\Resources\ReclamationResource;
use App\Modules\VieScolaire\Filament\Resources\SortieEleveResource;
use App\Modules\VieScolaire\Filament\Resources\VisiteInfirmerieResource;
use App\Modules\VieScolaire\Filament\Resources\VisiteurResource;
use App\Modules\VieScolaire\Models\IncidentVieScolaire;
use App\Modules\VieScolaire\Models\Reclamation;
use App\Modules\VieScolaire\Models\SortieEleve;
use App\Modules\VieScolaire\Models\VisiteInfirmerie;
use App\Modules\VieScolaire\Models\Visiteur;
use Filament\Pages\Page;

class VieScolaireDashboard extends Page
{
    protected static ?string $slug = 'vie-scolaire';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static string|\UnitEnum|null $navigationGroup = 'Vie scolaire';

    protected static ?string $navigationLabel = 'Tableau de bord';

    protected static ?string $title = 'Vie scolaire et sécurité';

    protected string $view = 'vie-scolaire::filament.pages.dashboard';

    public function getIndicators(): array
    {
        return [
            ['label' => 'Élèves sortis aujourd’hui', 'value' => SortieEleve::whereDate('heure_sortie', today())->count(), 'icon' => 'heroicon-o-arrow-right-on-rectangle'],
            ['label' => 'Visiteurs présents', 'value' => Visiteur::whereNull('heure_sortie')->count(), 'icon' => 'heroicon-o-identification'],
            ['label' => 'Infirmerie aujourd’hui', 'value' => VisiteInfirmerie::whereDate('date_heure', today())->count(), 'icon' => 'heroicon-o-heart'],
            ['label' => 'Urgences médicales', 'value' => VisiteInfirmerie::whereDate('date_heure', today())->where('gravite', 'grave')->count(), 'icon' => 'heroicon-o-exclamation-circle'],
            ['label' => 'Incidents ouverts', 'value' => IncidentVieScolaire::whereNotIn('statut', ['resolu', 'cloture', 'rejete', 'annule'])->count(), 'icon' => 'heroicon-o-exclamation-triangle'],
            ['label' => 'Réclamations en retard', 'value' => Reclamation::whereNotIn('statut', ['resolue', 'cloturee'])->whereDate('delai_reponse', '<', today())->count(), 'icon' => 'heroicon-o-clock'],
        ];
    }

    public function getPriorityActions(): array
    {
        $actions = [];
        foreach (VisiteInfirmerie::whereDate('date_heure', today())->where('gravite', 'grave')->latest('date_heure')->limit(3)->get() as $item) {
            $actions[] = ['label' => 'Urgence médicale', 'detail' => 'Passage à l’infirmerie #'.$item->id];
        }
        foreach (IncidentVieScolaire::whereIn('priorite', ['haute', 'urgente'])->whereNotIn('statut', ['resolu', 'cloture'])->latest('date_incident')->limit(3)->get() as $item) {
            $actions[] = ['label' => $item->reference, 'detail' => $item->description];
        }
        foreach (Visiteur::whereNull('heure_sortie')->where('heure_entree', '<', now()->subHours(4))->limit(3)->get() as $item) {
            $actions[] = ['label' => 'Visiteur présent depuis longtemps', 'detail' => $item->nom];
        }

        return array_slice($actions, 0, 6);
    }

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
