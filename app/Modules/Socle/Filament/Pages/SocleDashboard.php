<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Pages;

use App\Filament\Support\ModuleAccess;
use App\Filament\Widgets\AdministrationOverview;
use App\Modules\Socle\Filament\Resources\ActivityLogResource;
use App\Modules\Socle\Filament\Resources\AnneeScolaireResource;
use App\Modules\Socle\Filament\Resources\CourrierResource;
use App\Modules\Socle\Filament\Resources\DocumentResource;
use App\Modules\Socle\Filament\Resources\PermissionResource;
use App\Modules\Socle\Filament\Resources\ReunionResource;
use App\Modules\Socle\Filament\Resources\TacheResource;
use App\Modules\Socle\Filament\Resources\UserResource;
use App\Modules\Socle\Filament\Widgets\AnneeScolaireOverview;
use App\Modules\Socle\Filament\Widgets\AuditOverview;
use App\Modules\Socle\Filament\Widgets\CourrierOverview;
use App\Modules\Socle\Filament\Widgets\DocumentOverview;
use App\Modules\Socle\Filament\Widgets\ParametrageOverview;
use App\Modules\Socle\Filament\Widgets\ReunionOverview;
use App\Modules\Socle\Filament\Widgets\TacheOverview;
use Filament\Pages\Dashboard;
use Illuminate\Contracts\Support\Htmlable;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

class SocleDashboard extends Dashboard
{
    protected static ?string $slug = 'socle';

    protected static string $routePath = '/socle';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static string|\UnitEnum|null $navigationGroup = null;

    protected static ?string $navigationLabel = 'Tableau de bord';

    protected string $view = 'socle::filament.pages.administration-dashboard';

    protected function getViewData(): array
    {
        $items = [
            [UserResource::class, 'Utilisateurs', 'Créer et gérer les comptes de la plateforme.', 'heroicon-o-users', 'blue', true],
            [ManageHabilitations::class, 'Habilitations', 'Organiser les accès par habilitation du personnel, groupe et utilisateur.', 'heroicon-o-shield-check', 'gold', true],
            [AdministrationSettings::class, 'Paramètres', 'Configurer les informations de votre établissement.', 'heroicon-o-cog-6-tooth', 'blue', true],
            [ActivityLogResource::class, 'Audit', 'Consulter la traçabilité des actions réalisées.', 'heroicon-o-document-magnifying-glass', 'gold', true],
            [AnneeScolaireResource::class, 'Années scolaires', 'Années et périodes de l’établissement.', 'heroicon-o-calendar-days', 'blue', false],
            [DocumentResource::class, 'Documents', 'Retrouver les documents administratifs.', 'heroicon-o-folder-open', 'blue', false],
            [CourrierResource::class, 'Courriers', 'Suivre la correspondance de l’établissement.', 'heroicon-o-envelope', 'blue', false],
            [TacheResource::class, 'Tâches', 'Organiser le travail administratif.', 'heroicon-o-clipboard-document-check', 'blue', false],
            [ReunionResource::class, 'Réunions', 'Préparer les rencontres et leur suivi.', 'heroicon-o-user-group', 'blue', false],
        ];
        $links = [];
        foreach ($items as [$class, $title, $description, $icon, $tone, $primary]) {
            $allowed = is_subclass_of($class, \Filament\Resources\Resource::class) ? $class::canViewAny() : $class::canAccess();
            if ($allowed && ModuleAccess::canAccessComponent(auth()->user(), $class)) {
                $links[] = compact('title', 'description', 'icon', 'tone', 'primary') + ['url' => $class::getUrl()];
            }
        }
        $stats = [];
        if (UserResource::canViewAny() && ModuleAccess::canAccessComponent(auth()->user(), UserResource::class)) {
            $stats[] = [
                'title' => 'Utilisateurs actifs',
                'value' => UserResource::getEloquentQuery()->where('statut', 'actif')->count(),
                'detail' => 'Comptes autorisés à accéder à la plateforme',
                'icon' => 'heroicon-o-users',
            ];
            $stats[] = [
                'title' => 'Comptes supprimés',
                'value' => Activity::where('log_name', 'utilisateurs')->where('event', 'deleted')->count(),
                'detail' => 'Comptes retirés de la plateforme',
                'icon' => 'heroicon-o-trash',
            ];
        }

        if (PermissionResource::canViewAny() && ModuleAccess::canAccessComponent(auth()->user(), PermissionResource::class)) {
            $stats[] = [
                'title' => 'Permissions',
                'value' => Permission::count(),
                'detail' => 'Autorisations métier configurées',
                'icon' => 'heroicon-o-key',
            ];
        }

        return compact('links', 'stats');
    }

    public function getHeading(): string|Htmlable
    {
        return 'Administration générale';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Socle, paramétrage, années scolaires, audit et organisation administrative.';
    }

    public function getWidgets(): array
    {
        return [AdministrationOverview::class, ParametrageOverview::class, AnneeScolaireOverview::class, AuditOverview::class, DocumentOverview::class, CourrierOverview::class, TacheOverview::class, ReunionOverview::class];
    }

    public function getColumns(): int|array
    {
        return [
            'sm' => 1,
            'md' => 2,
            'xl' => 4,
        ];
    }
}
