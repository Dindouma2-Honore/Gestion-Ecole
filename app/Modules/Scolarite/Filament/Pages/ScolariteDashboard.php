<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Filament\Pages;

use App\Filament\Support\ModuleAccess;
use App\Modules\Scolarite\Filament\Resources\CategorieFraisResource;
use App\Modules\Scolarite\Filament\Resources\ClasseResource;
use App\Modules\Scolarite\Filament\Resources\EleveResource;
use App\Modules\Scolarite\Filament\Resources\FactureResource;
use App\Modules\Scolarite\Filament\Resources\FraisResource;
use App\Modules\Scolarite\Filament\Resources\GrilleTarifaireResource;
use App\Modules\Scolarite\Filament\Resources\InscriptionResource;
use App\Modules\Scolarite\Filament\Resources\PaiementResource;
use App\Modules\Scolarite\Filament\Resources\ParentTuteurResource;
use Filament\Pages\Page;

class ScolariteDashboard extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'scolarite';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static string|\UnitEnum|null $navigationGroup = 'Scolarité';

    protected static ?string $navigationLabel = 'Tableau de bord';

    protected static ?string $title = 'Scolarité';

    protected string $view = 'filament.pages.module-placeholder';

    public function mount(): void
    {
        $destination = ModuleAccess::canAccessModule(auth()->user(), 'Scolarite')
            ? EleveResource::getUrl()
            : InscriptionResource::getUrl();

        $this->redirect($destination, navigate: true);
    }

    public function getModuleIcon(): string
    {
        return 'heroicon-o-academic-cap';
    }

    public function getModuleDescription(): string
    {
        return 'Gérez les élèves, leurs responsables, les classes et les inscriptions depuis cet espace.';
    }

    public function getModuleLinks(): array
    {
        if (! ModuleAccess::canAccessModule(auth()->user(), 'Scolarite')) {
            return [
                ['label' => 'Inscriptions', 'description' => 'Démarrer et suivre une inscription scolaire', 'icon' => 'heroicon-o-document-check', 'url' => InscriptionResource::getUrl('index')],
            ];
        }

        return [
            ['label' => 'Élèves', 'description' => 'Dossiers et informations des élèves', 'icon' => 'heroicon-o-user-group', 'url' => EleveResource::getUrl('index')],
            ['label' => 'Parents et tuteurs', 'description' => 'Responsables et contacts familiaux', 'icon' => 'heroicon-o-users', 'url' => ParentTuteurResource::getUrl('index')],
            ['label' => 'Classes', 'description' => 'Capacités et organisation des classes', 'icon' => 'heroicon-o-building-library', 'url' => ClasseResource::getUrl('index')],
            ['label' => 'Inscriptions', 'description' => 'Inscriptions scolaires et matricules', 'icon' => 'heroicon-o-document-check', 'url' => InscriptionResource::getUrl('index')],
            ['label' => 'Catégories de frais', 'description' => 'CRUD libre des catégories de frais', 'icon' => 'heroicon-o-tag', 'url' => CategorieFraisResource::getUrl('index')],
            ['label' => 'Frais', 'description' => 'Frais de scolarité et frais divers', 'icon' => 'heroicon-o-banknotes', 'url' => FraisResource::getUrl('index')],
            ['label' => 'Grille tarifaire', 'description' => 'Tarifs par classe et année scolaire', 'icon' => 'heroicon-o-table-cells', 'url' => GrilleTarifaireResource::getUrl('index')],
            ['label' => 'Versements', 'description' => 'Registre des versements encaissés', 'icon' => 'heroicon-o-credit-card', 'url' => PaiementResource::getUrl('index')],
            ['label' => 'Factures', 'description' => 'Factures provisoires et définitives', 'icon' => 'heroicon-o-receipt-percent', 'url' => FactureResource::getUrl('index')],
        ];
    }
}
