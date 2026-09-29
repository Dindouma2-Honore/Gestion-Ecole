<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Models\User;
use App\Modules\Assiduite\Filament\Pages\AssiduiteDashboard;
use App\Modules\Communication\Filament\Pages\CommunicationDashboard;
use App\Modules\Finances\Filament\Pages\FinancesDashboard;
use App\Modules\Logistique\Filament\Pages\LogistiqueDashboard;
use App\Modules\Pedagogie\Filament\Pages\PedagogieDashboard;
use App\Modules\RH\Filament\Pages\RHDashboard;
use App\Modules\Scolarite\Filament\Resources\EleveResource;
use App\Modules\Scolarite\Filament\Resources\InscriptionResource;
use App\Modules\Socle\Filament\Resources\UserResource;
use App\Modules\VieScolaire\Filament\Pages\VieScolaireDashboard;

final class ModuleCatalog
{
    /** @return list<array{module: string, name: string, description: string, icon: string, color: string, url: string, active: bool}> */
    public static function visibleFor(?User $user): array
    {
        $modules = array_values(array_filter(
            self::all(),
            fn (array $module): bool => ModuleAccess::canSeeModuleEntry($user, $module['module']),
        ));

        $modules = array_map(function (array $module): array {
            $module['name'] = __($module['name']);
            $module['description'] = __($module['description']);

            return $module;
        }, $modules);

        return array_map(function (array $module) use ($user): array {
            if ($module['module'] === 'Scolarite') {
                $module['url'] = ModuleAccess::canAccessModule($user, 'Scolarite')
                    ? EleveResource::getUrl()
                    : InscriptionResource::getUrl();
            }

            return $module;
        }, $modules);
    }

    /** @return list<array{module: string, name: string, description: string, icon: string, color: string, url: string, active: bool}> */
    private static function all(): array
    {
        return [
            ['module' => 'Socle', 'name' => 'Administration', 'description' => 'Utilisateurs, paramétrage, années scolaires, audit et organisation.', 'icon' => 'heroicon-o-shield-check', 'color' => 'blue', 'url' => UserResource::getUrl(), 'active' => true],
            ['module' => 'RH', 'name' => 'Ressources humaines', 'description' => 'Employés, enseignants, contrats, congés, paie et formations.', 'icon' => 'heroicon-o-user-group', 'color' => 'violet', 'url' => RHDashboard::getUrl(), 'active' => true],
            ['module' => 'Scolarite', 'name' => 'Scolarité', 'description' => 'Élèves, inscriptions, classes et parcours scolaires.', 'icon' => 'heroicon-o-academic-cap', 'color' => 'cyan', 'url' => EleveResource::getUrl(), 'active' => true],
            ['module' => 'Pedagogie', 'name' => 'Pédagogie', 'description' => 'Matières, programmes, séances et suivi des appels.', 'icon' => 'heroicon-o-book-open', 'color' => 'amber', 'url' => PedagogieDashboard::getUrl(), 'active' => true],
            ['module' => 'Communication', 'name' => 'Communication', 'description' => 'Notifications, messagerie parents, rendez-vous et annonces.', 'icon' => 'heroicon-o-chat-bubble-left-right', 'color' => 'indigo', 'url' => CommunicationDashboard::getUrl(), 'active' => true],
            ['module' => 'Finances', 'name' => 'Finances', 'description' => 'Paiements, caisse, recouvrement, budgets et balance.', 'icon' => 'heroicon-o-banknotes', 'color' => 'emerald', 'url' => FinancesDashboard::getUrl(), 'active' => true],
            ['module' => 'Assiduite', 'name' => 'Assiduité', 'description' => 'Présences, retards, absences et pointages.', 'icon' => 'heroicon-o-clock', 'color' => 'rose', 'url' => AssiduiteDashboard::getUrl(), 'active' => false],
            ['module' => 'VieScolaire', 'name' => 'Vie scolaire', 'description' => 'Sorties des élèves, visiteurs, santé, incidents et réclamations.', 'icon' => 'heroicon-o-heart', 'color' => 'fuchsia', 'url' => VieScolaireDashboard::getUrl(), 'active' => true],
            ['module' => 'Logistique', 'name' => 'Logistique', 'description' => 'Infrastructures, équipements, transport, cantine et bibliothèque.', 'icon' => 'heroicon-o-building-office-2', 'color' => 'slate', 'url' => LogistiqueDashboard::getUrl(), 'active' => true],
        ];
    }

    private function __construct() {}
}
