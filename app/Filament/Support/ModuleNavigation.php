<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Modules\Finances\Filament\Pages\SituationFinanciere;
use App\Modules\Finances\Filament\Resources\RemiseExonerationResource;
use App\Modules\RH\Filament\Resources\AbsencePersonnelResource;
use App\Modules\RH\Filament\Resources\AvanceSalaireResource;
use App\Modules\RH\Filament\Resources\BulletinPaieResource;
use App\Modules\RH\Filament\Resources\CandidatureResource;
use App\Modules\RH\Filament\Resources\ContratResource;
use App\Modules\RH\Filament\Resources\EmployeResource;
use App\Modules\RH\Filament\Resources\TypePrimeResource;
use App\Modules\Scolarite\Filament\Pages\BilanJournalier;
use App\Modules\Scolarite\Filament\Resources\ClasseResource;
use App\Modules\Scolarite\Filament\Resources\EleveResource;
use App\Modules\Scolarite\Filament\Resources\InscriptionResource;
use App\Modules\Scolarite\Filament\Resources\ParentTuteurResource;
use App\Modules\Scolarite\Filament\Resources\TypeDocumentEleveResource;
use App\Modules\Socle\Filament\Pages\ManageEtablissementSettings;
use App\Modules\Socle\Filament\Pages\ManageHabilitations;
use App\Modules\Socle\Filament\Resources\ActivityLogResource;
use App\Modules\Socle\Filament\Resources\AnneeScolaireResource;
use App\Modules\Socle\Filament\Resources\DocumentTemplateResource;
use App\Modules\Socle\Filament\Resources\WorkflowDefinitionResource;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

final class ModuleNavigation
{
    public static function build(): NavigationBuilder
    {
        $builder = app(NavigationBuilder::class)
            ->item(
                NavigationItem::make(__('Accueil des modules'))
                    ->icon('heroicon-o-home')
                    ->extraAttributes([
                        'title' => 'Accueil des modules',
                        'x-tooltip.placement.right' => 'Accueil des modules',
                    ])
                    ->isActiveWhen(fn (): bool => request()->routeIs('home'))
                    ->url(route('home')),
            );

        $namespace = self::currentModuleNamespace();

        if ($namespace === null) {
            return $builder;
        }

        if ($namespace === 'App\\Modules\\Socle\\') {
            return self::administrationNavigation($builder);
        }

        if ($namespace === 'App\\Modules\\RH\\') {
            return self::personnelNavigation($builder);
        }

        if ($namespace === 'App\\Modules\\Scolarite\\') {
            return self::scolariteNavigation($builder);
        }

        $panel = Filament::getCurrentPanel();
        $components = [...$panel->getPages(), ...$panel->getResources()];

        $items = collect($components)
            ->filter(fn (string $component): bool => str_starts_with($component, $namespace))
            ->filter(fn (string $component): bool => ModuleAccess::canAccessComponent(Auth::user(), $component))
            ->filter(fn (string $component): bool => $component::shouldRegisterNavigation() && $component::canAccess())
            ->flatMap(fn (string $component): array => $component::getNavigationItems())
            ->map(function (NavigationItem $item): NavigationItem {
                $item->label(__($item->getLabel()));

                return $item->extraAttributes([
                    'title' => $item->getLabel(),
                    'x-tooltip.placement.right' => $item->getLabel(),
                ]);
            });

        self::addGroupedItems($builder, $items);

        return $builder;
    }

    private static function administrationNavigation(NavigationBuilder $builder): NavigationBuilder
    {
        $items = [
            NavigationItem::make(__('Utilisateurs & habilitations'))
                ->icon('heroicon-o-users')
                ->url(ManageHabilitations::getUrl())
                ->isActiveWhen(fn (): bool => self::routeCommencePar(['users', 'roles', 'permissions', 'groupes', 'manage-habilitations', 'manage-user-permissions', 'manage-groupe-permissions'])),
            NavigationItem::make(__('Structure de l’établissement'))
                ->icon('heroicon-o-building-office-2')
                ->url(ManageEtablissementSettings::getUrl())
                ->isActiveWhen(fn (): bool => self::routeCommencePar(['manage-etablissement-settings', 'manage-system-settings', 'cycles', 'niveaux', 'filieres', 'section-scolaires'])),
            NavigationItem::make(__('Années & périodes'))
                ->icon('heroicon-o-calendar-days')
                ->url(AnneeScolaireResource::getUrl())
                ->isActiveWhen(fn (): bool => self::routeCommencePar(['annee-scolaires', 'periodes', 'jour-feries'])),
            NavigationItem::make(__('Modèles de documents'))
                ->icon('heroicon-o-document-duplicate')
                ->url(DocumentTemplateResource::getUrl())
                ->isActiveWhen(fn (): bool => self::routeCommencePar(['document-templates'])),
            NavigationItem::make(__('Workflows & validations'))
                ->icon('heroicon-o-arrows-right-left')
                ->url(WorkflowDefinitionResource::getUrl())
                ->isActiveWhen(fn (): bool => self::routeCommencePar(['workflow-definitions', 'workflow-instances', 'seuil-validations', 'template-notifications'])),
            NavigationItem::make(__('Audit & sécurité'))
                ->icon('heroicon-o-shield-check')
                ->url(ActivityLogResource::getUrl())
                ->isActiveWhen(fn (): bool => self::routeCommencePar(['activity-logs'])),
        ];

        foreach ($items as $item) {
            $item->extraAttributes([
                'title' => $item->getLabel(),
                'x-tooltip.placement.right' => $item->getLabel(),
            ]);
        }

        return $builder->group(__('ADMINISTRATION'), $items);
    }

    private static function personnelNavigation(NavigationBuilder $builder): NavigationBuilder
    {
        $items = [
            NavigationItem::make(__('Recrutement'))
                ->icon('heroicon-o-briefcase')
                ->url(CandidatureResource::getUrl())
                ->isActiveWhen(fn (): bool => self::routeCommencePar(['candidatures'])),
            NavigationItem::make(__('Personnel'))
                ->icon('heroicon-o-user-group')
                ->url(EmployeResource::getUrl())
                ->isActiveWhen(fn (): bool => self::routeCommencePar(['employes', 'enseignants', 'categorie-personnels', 'personnel-roles', 'poste-administratifs'])),
            NavigationItem::make(__('Contrats'))
                ->icon('heroicon-o-document-text')
                ->url(ContratResource::getUrl())
                ->isActiveWhen(fn (): bool => self::routeCommencePar(['contrats'])),
            NavigationItem::make(__('Absences & congés'))
                ->icon('heroicon-o-calendar-days')
                ->url(AbsencePersonnelResource::getUrl())
                ->isActiveWhen(fn (): bool => self::routeCommencePar(['absence-personnels', 'conges', 'pointages'])),
            NavigationItem::make(__('Primes'))
                ->icon('heroicon-o-gift')
                ->url(TypePrimeResource::getUrl())
                ->isActiveWhen(fn (): bool => self::routeCommencePar(['primes', 'type-primes', 'personnel-primes'])),
            NavigationItem::make(__('Avances de salaire'))
                ->icon('heroicon-o-banknotes')
                ->url(AvanceSalaireResource::getUrl())
                ->isActiveWhen(fn (): bool => self::routeCommencePar(['avance-salaires'])),
            NavigationItem::make(__('Paie'))
                ->icon('heroicon-o-calculator')
                ->url(BulletinPaieResource::getUrl())
                ->isActiveWhen(fn (): bool => self::routeCommencePar(['bulletin-paies'])),
        ];

        foreach ($items as $item) {
            $item->extraAttributes([
                'title' => $item->getLabel(),
                'x-tooltip.placement.right' => $item->getLabel(),
            ]);
        }

        return $builder->group(__('PERSONNEL'), $items);
    }

    private static function scolariteNavigation(NavigationBuilder $builder): NavigationBuilder
    {
        $items = [
            NavigationItem::make(__('Élèves & admissions'))
                ->icon('heroicon-o-user-group')
                ->url(EleveResource::getUrl())
                ->isActiveWhen(fn (): bool => self::routeCommencePar(['eleves'])),
            NavigationItem::make(__('Parents & tuteurs'))
                ->icon('heroicon-o-users')
                ->url(ParentTuteurResource::getUrl())
                ->isActiveWhen(fn (): bool => self::routeCommencePar(['parent-tuteurs'])),
            NavigationItem::make(__('Inscriptions'))
                ->icon('heroicon-o-clipboard-document-check')
                ->url(InscriptionResource::getUrl())
                ->isActiveWhen(fn (): bool => self::routeCommencePar(['inscriptions'])),
            NavigationItem::make(__('Classes'))
                ->icon('heroicon-o-building-library')
                ->url(ClasseResource::getUrl())
                ->isActiveWhen(fn (): bool => self::routeCommencePar(['classes'])),
            NavigationItem::make(__('Documents d’admission'))
                ->icon('heroicon-o-paper-clip')
                ->url(TypeDocumentEleveResource::getUrl())
                ->isActiveWhen(fn (): bool => self::routeCommencePar(['type-document-eleves'])),
            NavigationItem::make(__('Bilan journalier'))
                ->icon('heroicon-o-clipboard-document-list')
                ->url(BilanJournalier::getUrl())
                ->isActiveWhen(fn (): bool => self::routeCommencePar(['scolarite/bilan-journalier'])),
            NavigationItem::make(__('Statut des paiements'))
                ->icon('heroicon-o-chart-bar-square')
                ->url(SituationFinanciere::getUrl())
                ->isActiveWhen(fn (): bool => self::routeCommencePar(['finances/situation-eleves'])),
            NavigationItem::make(__('Remises & exonérations'))
                ->icon('heroicon-o-receipt-percent')
                ->url(RemiseExonerationResource::getUrl())
                ->isActiveWhen(fn (): bool => self::routeCommencePar(['remise-exonerations'])),
        ];

        foreach ($items as $item) {
            $item->extraAttributes([
                'title' => $item->getLabel(),
                'x-tooltip.placement.right' => $item->getLabel(),
            ]);
        }

        return $builder->group(__('SCOLARITÉ'), $items);
    }

    /** @param list<string> $segments */
    private static function routeCommencePar(array $segments): bool
    {
        $path = request()->path();

        return collect($segments)->contains(fn (string $segment): bool => str_starts_with($path, "admin/{$segment}"));
    }

    private static function currentModuleNamespace(): ?string
    {
        $action = request()->route()?->getActionName() ?? '';

        if (str_contains($action, 'RemiseExonerationResource')) {
            return 'App\\Modules\\Scolarite\\';
        }

        foreach (['Socle', 'RH', 'Scolarite', 'Pedagogie', 'Finances', 'Assiduite', 'Communication', 'VieScolaire', 'Logistique'] as $module) {
            $namespace = "App\\Modules\\{$module}\\";

            if (str_starts_with($action, $namespace)) {
                return $namespace;
            }
        }

        return null;
    }

    /** @param Collection<int, NavigationItem> $items */
    private static function addGroupedItems(NavigationBuilder $builder, Collection $items): void
    {
        $items
            ->groupBy(fn (NavigationItem $item): string => (string) ($item->getGroup() ?? ''))
            ->each(function (Collection $groupItems, string $group) use ($builder): void {
                if ($group === '') {
                    $builder->items($groupItems->all());

                    return;
                }

                $builder->group(__($group), $groupItems->all());
            });
    }

    private function __construct() {}
}
