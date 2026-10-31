<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Modules\Assiduite\Filament\Pages\AbsencesRetards;
use App\Modules\Assiduite\Filament\Pages\AssiduiteDashboard;
use App\Modules\Assiduite\Filament\Pages\JustificationsAnomalies;
use App\Modules\Assiduite\Filament\Pages\PointagePersonnel;
use App\Modules\Assiduite\Filament\Pages\PresencesEleves;
use App\Modules\Assiduite\Filament\Pages\RapportsAssiduite;
use App\Modules\Logistique\Filament\Pages\Bibliotheque as LogistiqueBibliotheque;
use App\Modules\Logistique\Filament\Pages\Cantine as LogistiqueCantine;
use App\Modules\Logistique\Filament\Pages\EquipementsMaintenance;
use App\Modules\Logistique\Filament\Pages\Infrastructures;
use App\Modules\Logistique\Filament\Pages\LogistiqueDashboard;
use App\Modules\Logistique\Filament\Pages\TransportScolaire;
use App\Modules\Pedagogie\Filament\Pages\BulletinsConseils;
use App\Modules\Pedagogie\Filament\Pages\EmploisDuTemps;
use App\Modules\Pedagogie\Filament\Pages\Enseignements;
use App\Modules\Pedagogie\Filament\Pages\EvaluationsResultats;
use App\Modules\Pedagogie\Filament\Pages\PedagogieDashboard;
use App\Modules\Pedagogie\Filament\Pages\ProgrammesProgression;
use App\Modules\Pedagogie\Filament\Pages\SeancesAssiduite;
use App\Modules\Pedagogie\Filament\Resources\AffectationPedagogiqueResource;
use App\Modules\Pedagogie\Filament\Resources\BulletinResource;
use App\Modules\Pedagogie\Filament\Resources\CategorieMatiereResource;
use App\Modules\Pedagogie\Filament\Resources\CreneauHoraireResource;
use App\Modules\Pedagogie\Filament\Resources\DecisionPedagogiqueResource;
use App\Modules\Pedagogie\Filament\Resources\EmploiDuTempsResource;
use App\Modules\Pedagogie\Filament\Resources\EvaluationResource;
use App\Modules\Pedagogie\Filament\Resources\MatiereResource;
use App\Modules\Pedagogie\Filament\Resources\NoteResource;
use App\Modules\Pedagogie\Filament\Resources\OffrePedagogiqueResource;
use App\Modules\Pedagogie\Filament\Resources\PresenceResource;
use App\Modules\Pedagogie\Filament\Resources\ProgrammeResource;
use App\Modules\Pedagogie\Filament\Resources\ProgressionResource;
use App\Modules\Pedagogie\Filament\Resources\SeanceResource;
use App\Modules\Pedagogie\Filament\Resources\SuiviProgressionResource;
use App\Modules\RH\Filament\Pages\HistoriquePaies;
use App\Modules\RH\Filament\Pages\RHDashboard;
use App\Modules\RH\Filament\Resources\AbsencePersonnelResource;
use App\Modules\RH\Filament\Resources\AvanceSalaireResource;
use App\Modules\RH\Filament\Resources\BulletinPaieResource;
use App\Modules\RH\Filament\Resources\CandidatureResource;
use App\Modules\RH\Filament\Resources\CongeResource;
use App\Modules\RH\Filament\Resources\ContratResource;
use App\Modules\RH\Filament\Resources\EmployeResource;
use App\Modules\RH\Filament\Resources\PersonnelPrimeResource;
use App\Modules\RH\Filament\Resources\TypeCongeResource;
use App\Modules\RH\Filament\Resources\TypePrimeResource;
use App\Modules\Scolarite\Filament\Pages\BilanJournalier;
use App\Modules\Scolarite\Filament\Resources\CycleResource;
use App\Modules\Scolarite\Filament\Resources\EleveResource;
use App\Modules\Scolarite\Filament\Resources\FiliereResource;
use App\Modules\Scolarite\Filament\Resources\GrilleTarifaireResource;
use App\Modules\Scolarite\Filament\Resources\InscriptionResource;
use App\Modules\Scolarite\Filament\Resources\NiveauResource;
use App\Modules\Scolarite\Filament\Resources\ParentTuteurResource;
use App\Modules\Scolarite\Filament\Resources\SectionScolaireResource;
use App\Modules\Socle\Filament\Pages\AdministrationSettings;
use App\Modules\Socle\Filament\Pages\ManageGroupePermissions;
use App\Modules\Socle\Filament\Pages\ManageHabilitations;
use App\Modules\Socle\Filament\Pages\ManageUserPermissions;
use App\Modules\Socle\Filament\Pages\SocleDashboard;
use App\Modules\Socle\Filament\Resources\ActivityLogResource;
use App\Modules\Socle\Filament\Resources\UserResource;
use App\Modules\VieScolaire\Filament\Pages\CalendrierEvenements;
use App\Modules\VieScolaire\Filament\Pages\IncidentsReclamations;
use App\Modules\VieScolaire\Filament\Pages\RapportsHistorique;
use App\Modules\VieScolaire\Filament\Pages\SanteInfirmerie;
use App\Modules\VieScolaire\Filament\Pages\SortiesEleves;
use App\Modules\VieScolaire\Filament\Pages\VieScolaireDashboard;
use App\Modules\VieScolaire\Filament\Pages\Visiteurs;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

final class ModuleNavigation
{
    public static function build(): NavigationBuilder
    {
        return self::forModule(self::currentModuleCode());
    }

    public static function currentModuleCode(): ?string
    {
        $namespace = self::currentModuleNamespace();

        return $namespace ? explode('\\', $namespace)[2] : null;
    }

    public static function forModule(?string $module): NavigationBuilder
    {
        if ($module === null || $module === 'Socle') {
            return self::buildLegacy($module ? 'App\\Modules\\'.$module.'\\' : null);
        }
        $entry = collect(ModuleCatalog::visibleFor(Auth::user()))->firstWhere('module', $module);
        if (! $entry) {
            return app(NavigationBuilder::class);
        }

        $builder = self::buildLegacy('App\\Modules\\'.$module.'\\');
        $result = app(NavigationBuilder::class);
        $result->group($entry['name'], [NavigationItem::make(__('Tableau de bord'))
            ->icon('heroicon-o-home')->url($entry['url'])
            ->isActiveWhen(fn (): bool => request()->url() === $entry['url'])], collapsible: false);

        foreach ($builder->getNavigation() as $group) {
            $items = [];
            foreach ($group->getItems() as $item) {
                if (in_array($item->getUrl(), [route('home'), $entry['url']], true)) {
                    continue;
                }
                if (! ModuleExperience::canOpenUrl($item->getUrl())) {
                    continue;
                }
                $item->childItems(array_values(array_filter($item->getChildItems(),
                    fn (NavigationItem $child): bool => ModuleExperience::canOpenUrl($child->getUrl()))));
                $items[] = $item;
            }
            if ($items !== []) {
                $result->group($group->getLabel() ?: $entry['name'], $items, collapsible: false);
            }
        }

        $others = [];
        foreach (ModuleCatalog::visibleFor(Auth::user()) as $other) {
            if ($other['module'] !== $module) {
                $others[] = NavigationItem::make($other['name'])->icon($other['icon'])->url($other['url']);
            }
        }

        return $result->group(__('AUTRES MODULES'), $others, collapsible: false);
    }

    private static function buildLegacy(?string $namespace): NavigationBuilder
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

        if ($namespace === null) {
            return $builder;
        }

        if ($namespace === 'App\\Modules\\Socle\\') {
            return self::administrationNavigation(app(NavigationBuilder::class));
        }

        if ($namespace === 'App\\Modules\\RH\\') {
            return self::personnelNavigation($builder);
        }

        if ($namespace === 'App\\Modules\\Scolarite\\') {
            return self::scolariteNavigation($builder);
        }

        if ($namespace === 'App\\Modules\\Pedagogie\\') {
            return self::pedagogieNavigation($builder);
        }

        if ($namespace === 'App\\Modules\\Logistique\\') {
            return self::logistiqueNavigation($builder);
        }

        if ($namespace === 'App\\Modules\\VieScolaire\\') {
            return self::vieScolaireNavigation($builder);
        }

        if ($namespace === 'App\\Modules\\Assiduite\\') {
            return self::assiduiteNavigation($builder);
        }

        $panel = Filament::getCurrentPanel();
        $components = [...$panel->getPages(), ...$panel->getResources()];

        $items = collect($components)
            ->filter(fn (string $component): bool => str_starts_with($component, $namespace) && ! SchoolFinanceNavigation::contains($component))
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

    public static function isAdministration(): bool
    {
        return self::currentModuleNamespace() === 'App\\Modules\\Socle\\';
    }

    private static function administrationNavigation(NavigationBuilder $builder): NavigationBuilder
    {
        $entries = [
            [SocleDashboard::class, 'Tableau de bord', 'heroicon-o-home', ['socle']],
            [UserResource::class, 'Utilisateurs', 'heroicon-o-users', ['users']],
            [ManageHabilitations::class, 'Habilitations', 'heroicon-o-shield-check', ['roles', 'permissions', 'groupes', 'manage-habilitations', 'manage-user-permissions', 'manage-groupe-permissions']],
            [AdministrationSettings::class, 'Paramètres', 'heroicon-o-cog-6-tooth', ['administration/parametres', 'manage-etablissement-settings', 'annee-scolaires', 'periodes', 'jour-feries', 'manage-system-settings', 'template-notifications', 'format-numerotations', 'seuil-validations']],
            [ActivityLogResource::class, 'Audit', 'heroicon-o-clipboard-document-list', ['activity-logs']],
        ];

        $items = [];
        foreach ($entries as [$component, $label, $icon, $paths]) {
            $canAccess = is_subclass_of($component, Resource::class)
                ? $component::canViewAny()
                : $component::canAccess();

            if (! $canAccess || ! ModuleAccess::canAccessComponent(Auth::user(), $component)) {
                continue;
            }

            $item = NavigationItem::make(__($label))
                ->icon($icon)
                ->url($component::getUrl())
                ->extraAttributes(['title' => __($label)])
                ->isActiveWhen(fn (): bool => self::routeCommencePar($paths));

            if ($component === ManageHabilitations::class) {
                $childItems = [];
                if (ManageHabilitations::canAccess() && ModuleAccess::canAccessComponent(Auth::user(), ManageHabilitations::class)) {
                    $childItems[] = NavigationItem::make(__('Habilitation du personnel'))
                        ->icon('heroicon-o-identification')
                        ->url(ManageHabilitations::getUrl())
                        ->isActiveWhen(fn (): bool => self::routeCommencePar(['manage-habilitations', 'roles', 'permissions']));
                }
                if (ManageGroupePermissions::canAccess() && ModuleAccess::canAccessComponent(Auth::user(), ManageGroupePermissions::class)) {
                    $childItems[] = NavigationItem::make(__('Groupes'))
                        ->icon('heroicon-o-user-group')
                        ->url(ManageGroupePermissions::getUrl())
                        ->isActiveWhen(fn (): bool => self::routeCommencePar(['manage-groupe-permissions', 'groupes']));
                }
                if (ManageUserPermissions::canAccess() && ModuleAccess::canAccessComponent(Auth::user(), ManageUserPermissions::class)) {
                    $childItems[] = NavigationItem::make(__('Utilisateurs'))
                        ->icon('heroicon-o-user')
                        ->url(ManageUserPermissions::getUrl())
                        ->isActiveWhen(fn (): bool => self::routeCommencePar(['manage-user-permissions']));
                }
                $item->childItems($childItems);
            }

            if ($component === AdministrationSettings::class) {
                $item->childItems(array_map(
                    fn (array $section): NavigationItem => NavigationItem::make(__($section['label']))
                        ->url(AdministrationSettings::sectionUrl($section['id'])),
                    AdministrationSettings::sections(),
                ));
            }
            $items[] = $item;
        }

        $modules = [];
        foreach (ModuleCatalog::visibleFor(Auth::user()) as $module) {
            if ($module['module'] === 'Socle') {
                continue;
            }

            $modules[] = NavigationItem::make($module['name'])
                ->icon($module['icon'])
                ->url($module['url'])
                ->extraAttributes(['title' => $module['description']]);
        }

        return $builder
            ->group(__('ADMINISTRATION'), $items, collapsible: false)
            ->group(__('AUTRES MODULES'), $modules, collapsible: false);
    }

    private static function personnelNavigation(NavigationBuilder $builder): NavigationBuilder
    {
        $items = [
            NavigationItem::make(__('Tableau de bord'))
                ->icon('heroicon-o-squares-2x2')
                ->url(RHDashboard::getUrl())
                ->isActiveWhen(fn (): bool => self::routeCommencePar(['rh'])),
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
                ->childItems([
                    NavigationItem::make(__('Absences'))
                        ->icon('heroicon-o-calendar-days')
                        ->url(AbsencePersonnelResource::getUrl())
                        ->isActiveWhen(fn (): bool => self::routeCommencePar(['absence-personnels'])),
                    NavigationItem::make(__('Congés'))
                        ->icon('heroicon-o-calendar')
                        ->url(CongeResource::getUrl())
                        ->isActiveWhen(fn (): bool => self::routeCommencePar(['conges'])),
                    NavigationItem::make(__('Types de congés'))
                        ->icon('heroicon-o-tag')
                        ->url(TypeCongeResource::getUrl())
                        ->isActiveWhen(fn (): bool => self::routeCommencePar(['type-conges'])),
                ])
                ->isActiveWhen(fn (): bool => self::routeCommencePar(['absence-personnels', 'conges', 'pointages'])),
            NavigationItem::make(__('Primes'))
                ->icon('heroicon-o-gift')
                ->url(TypePrimeResource::getUrl())
                ->childItems([
                    NavigationItem::make(__('Types de primes'))
                        ->icon('heroicon-o-tag')
                        ->url(TypePrimeResource::getUrl())
                        ->isActiveWhen(fn (): bool => self::routeCommencePar(['type-primes'])),
                    NavigationItem::make(__('Primes du personnel'))
                        ->icon('heroicon-o-user-plus')
                        ->url(PersonnelPrimeResource::getUrl())
                        ->isActiveWhen(fn (): bool => self::routeCommencePar(['personnel-primes'])),
                ])
                ->isActiveWhen(fn (): bool => self::routeCommencePar(['primes', 'type-primes', 'personnel-primes'])),
            NavigationItem::make(__('Avances de salaire'))
                ->icon('heroicon-o-banknotes')
                ->url(AvanceSalaireResource::getUrl())
                ->isActiveWhen(fn (): bool => self::routeCommencePar(['avance-salaires'])),
            NavigationItem::make(__('Paie'))
                ->icon('heroicon-o-calculator')
                ->url(BulletinPaieResource::getUrl())
                ->isActiveWhen(fn (): bool => self::routeCommencePar(['bulletin-paies'])),
            NavigationItem::make(__('Historique des paies'))
                ->icon('heroicon-o-clock')
                ->url(HistoriquePaies::getUrl())
                ->isActiveWhen(fn (): bool => self::routeCommencePar(['historique-paies'])),
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
            NavigationItem::make(__('Admissions'))
                ->icon('heroicon-o-user-plus')
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
            NavigationItem::make(__('Grilles tarifaires'))
                ->icon('heroicon-o-table-cells')
                ->url(GrilleTarifaireResource::getUrl())
                ->isActiveWhen(fn (): bool => self::routeCommencePar(['grille-tarifaires', 'classes'])),
            NavigationItem::make(__('Bilan journalier'))
                ->icon('heroicon-o-clipboard-document-list')
                ->url(BilanJournalier::getUrl())
                ->isActiveWhen(fn (): bool => self::routeCommencePar(['scolarite/bilan-journalier'])),
        ];

        foreach ([CycleResource::class, NiveauResource::class, FiliereResource::class, SectionScolaireResource::class] as $resource) {
            if ($resource::canViewAny() && ModuleAccess::canAccessComponent(Auth::user(), $resource)) {
                array_push($items, ...$resource::getNavigationItems());
            }
        }

        foreach (SchoolFinanceNavigation::COMPONENTS as $component => $label) {
            $items[] = NavigationItem::make($label)
                ->icon($component::getNavigationIcon() ?? 'heroicon-o-banknotes')
                ->url($component::getUrl())
                ->isActiveWhen(fn (): bool => str_starts_with(request()->route()?->getActionName() ?? '', $component));
        }

        foreach ($items as $item) {
            $item->extraAttributes([
                'title' => $item->getLabel(),
                'x-tooltip.placement.right' => $item->getLabel(),
            ]);
        }

        return $builder->group(__('SCOLARITÉ'), $items);
    }

    private static function pedagogieNavigation(NavigationBuilder $builder): NavigationBuilder
    {
        $items = [
            NavigationItem::make(__('Tableau de bord'))->icon('heroicon-o-squares-2x2')->url(PedagogieDashboard::getUrl()),
            NavigationItem::make(__('Organisation pédagogique'))
                ->icon('heroicon-o-book-open')
                ->url(Enseignements::getUrl())
                ->isActiveWhen(fn (): bool => self::routeCommencePar(['pedagogie/enseignements']))
                ->childItems([
                    NavigationItem::make(__('Matières & domaines'))->icon('heroicon-o-book-open')->url(MatiereResource::getUrl())->isActiveWhen(fn (): bool => self::routeCommencePar(['matieres'])),
                    NavigationItem::make(__('Catégories'))->icon('heroicon-o-tag')->url(CategorieMatiereResource::getUrl())->isActiveWhen(fn (): bool => self::routeCommencePar(['categorie-matieres'])),
                    NavigationItem::make(__('Offre pédagogique'))->icon('heroicon-o-adjustments-horizontal')->url(OffrePedagogiqueResource::getUrl())->isActiveWhen(fn (): bool => self::routeCommencePar(['offre-pedagogiques'])),
                    NavigationItem::make(__('Affectations'))->icon('heroicon-o-user-plus')->url(AffectationPedagogiqueResource::getUrl())->isActiveWhen(fn (): bool => self::routeCommencePar(['affectation-pedagogiques'])),
                ]),
            NavigationItem::make(__('Programmes & progression'))->icon('heroicon-o-clipboard-document-list')->url(ProgrammesProgression::getUrl())
                ->isActiveWhen(fn (): bool => self::routeCommencePar(['pedagogie/programmes-progression', 'pedagogie/mes-progressions', 'pedagogie/supervision-progressions']))
                ->childItems([
                    NavigationItem::make(__('Mes programmes'))->icon('heroicon-o-clipboard-document-list')->url(ProgrammeResource::getUrl())->isActiveWhen(fn (): bool => self::routeCommencePar(['programmes'])),
                    NavigationItem::make(__('Progression des séances'))->icon('heroicon-o-chart-bar-square')->url(ProgressionResource::getUrl())->isActiveWhen(fn (): bool => self::routeCommencePar(['progressions'])),
                    NavigationItem::make(__('Suivi des progressions'))->icon('heroicon-o-chart-bar')->url(SuiviProgressionResource::getUrl())->isActiveWhen(fn (): bool => self::routeCommencePar(['suivi-progressions'])),
                ]),
            NavigationItem::make(__('Emplois du temps'))->icon('heroicon-o-calendar-days')->url(EmploisDuTemps::getUrl())
                ->isActiveWhen(fn (): bool => self::routeCommencePar(['pedagogie/emplois-du-temps']))
                ->childItems([
                    NavigationItem::make(__('Grille des cours'))->icon('heroicon-o-calendar-days')->url(EmploiDuTempsResource::getUrl())->isActiveWhen(fn (): bool => self::routeCommencePar(['emploi-du-temps'])),
                    NavigationItem::make(__('Créneaux horaires'))->icon('heroicon-o-clock')->url(CreneauHoraireResource::getUrl())->isActiveWhen(fn (): bool => self::routeCommencePar(['creneaux-horaires'])),
                ]),
            NavigationItem::make(__('Séances & appels'))->icon('heroicon-o-user-group')->url(SeancesAssiduite::getUrl())
                ->isActiveWhen(fn (): bool => self::routeCommencePar(['pedagogie/seances-assiduite']))
                ->childItems([
                    NavigationItem::make(__('Séances du jour'))->icon('heroicon-o-presentation-chart-bar')->url(SeanceResource::getUrl())->isActiveWhen(fn (): bool => self::routeCommencePar(['seances'])),
                    NavigationItem::make(__('Appel des élèves'))->icon('heroicon-o-check-badge')->url(PresenceResource::getUrl())->isActiveWhen(fn (): bool => self::routeCommencePar(['presences'])),
                    NavigationItem::make(__('Contenu réellement enseigné'))->icon('heroicon-o-book-open')->url(ProgressionResource::getUrl())->isActiveWhen(fn (): bool => self::routeCommencePar(['progressions'])),
                ]),
            NavigationItem::make(__('Évaluations & notes'))->icon('heroicon-o-pencil-square')->url(EvaluationsResultats::getUrl())
                ->isActiveWhen(fn (): bool => self::routeCommencePar(['pedagogie/evaluations-resultats']))
                ->childItems([
                    NavigationItem::make(__('Évaluations & validations'))->icon('heroicon-o-clipboard-document-check')->url(EvaluationResource::getUrl())->isActiveWhen(fn (): bool => self::routeCommencePar(['evaluations'])),
                    NavigationItem::make(__('Saisie & validation des notes'))->icon('heroicon-o-pencil-square')->url(NoteResource::getUrl())->isActiveWhen(fn (): bool => self::routeCommencePar(['notes'])),
                ]),
            NavigationItem::make(__('Résultats & bulletins'))->icon('heroicon-o-document-chart-bar')->url(BulletinsConseils::getUrl())
                ->isActiveWhen(fn (): bool => self::routeCommencePar(['pedagogie/bulletins-conseils']))
                ->childItems([
                    NavigationItem::make(__('Calcul, appréciations, bulletins & publication'))->icon('heroicon-o-document-chart-bar')->url(BulletinResource::getUrl())->isActiveWhen(fn (): bool => self::routeCommencePar(['bulletins'])),
                    NavigationItem::make(__('Décisions de passage'))->icon('heroicon-o-academic-cap')->url(DecisionPedagogiqueResource::getUrl())->isActiveWhen(fn (): bool => self::routeCommencePar(['decision-pedagogiques', 'conseils-classe'])),
                ]),
        ];

        foreach ($items as $item) {
            $item->extraAttributes([
                'title' => $item->getLabel(),
                'x-tooltip.placement.right' => $item->getLabel(),
            ]);
        }

        return $builder->group(__('PÉDAGOGIE'), $items);
    }

    private static function logistiqueNavigation(NavigationBuilder $builder): NavigationBuilder
    {
        $items = [
            NavigationItem::make(__('Vue logistique'))->icon('heroicon-o-squares-2x2')->url(LogistiqueDashboard::getUrl()),
            NavigationItem::make(__('Infrastructures'))->icon('heroicon-o-building-office-2')->url(Infrastructures::getUrl()),
            NavigationItem::make(__('Équipements & maintenance'))->icon('heroicon-o-wrench-screwdriver')->url(EquipementsMaintenance::getUrl()),
            NavigationItem::make(__('Transport scolaire'))->icon('heroicon-o-truck')->url(TransportScolaire::getUrl()),
            NavigationItem::make(__('Cantine'))->icon('heroicon-o-cake')->url(LogistiqueCantine::getUrl()),
            NavigationItem::make(__('Bibliothèque'))->icon('heroicon-o-book-open')->url(LogistiqueBibliotheque::getUrl()),
        ];

        foreach ($items as $item) {
            $item->extraAttributes(['title' => $item->getLabel(), 'x-tooltip.placement.right' => $item->getLabel()]);
        }

        return $builder->group(__('LOGISTIQUE'), $items);
    }

    private static function vieScolaireNavigation(NavigationBuilder $builder): NavigationBuilder
    {
        $items = [
            NavigationItem::make(__('Vue vie scolaire'))->icon('heroicon-o-squares-2x2')->url(VieScolaireDashboard::getUrl()),
            NavigationItem::make(__('Sorties des élèves'))->icon('heroicon-o-arrow-right-on-rectangle')->url(SortiesEleves::getUrl()),
            NavigationItem::make(__('Visiteurs'))->icon('heroicon-o-identification')->url(Visiteurs::getUrl()),
            NavigationItem::make(__('Santé & infirmerie'))->icon('heroicon-o-heart')->url(SanteInfirmerie::getUrl()),
            NavigationItem::make(__('Incidents & réclamations'))->icon('heroicon-o-exclamation-triangle')->url(IncidentsReclamations::getUrl()),
            NavigationItem::make(__('Calendrier & événements'))->icon('heroicon-o-calendar-days')->url(CalendrierEvenements::getUrl()),
            NavigationItem::make(__('Rapports & historique'))->icon('heroicon-o-chart-bar')->url(RapportsHistorique::getUrl()),
        ];

        foreach ($items as $item) {
            $item->extraAttributes(['title' => $item->getLabel(), 'x-tooltip.placement.right' => $item->getLabel()]);
        }

        return $builder->group(__('VIE SCOLAIRE'), $items);
    }

    private static function assiduiteNavigation(NavigationBuilder $builder): NavigationBuilder
    {
        $items = [
            NavigationItem::make(__('Vue d’assiduité'))->icon('heroicon-o-squares-2x2')->url(AssiduiteDashboard::getUrl()),
            NavigationItem::make(__('Présences des élèves'))->icon('heroicon-o-academic-cap')->url(PresencesEleves::getUrl()),
            NavigationItem::make(__('Absences & retards'))->icon('heroicon-o-clock')->url(AbsencesRetards::getUrl()),
            NavigationItem::make(__('Pointage du personnel'))->icon('heroicon-o-finger-print')->url(PointagePersonnel::getUrl()),
            NavigationItem::make(__('Justifications & anomalies'))->icon('heroicon-o-document-check')->url(JustificationsAnomalies::getUrl()),
            NavigationItem::make(__('Rapports d’assiduité'))->icon('heroicon-o-chart-bar')->url(RapportsAssiduite::getUrl()),
        ];

        foreach ($items as $item) {
            $item->extraAttributes(['title' => $item->getLabel(), 'x-tooltip.placement.right' => $item->getLabel()]);
        }

        return $builder->group(__('ASSIDUITÉ'), $items);
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

        if (SchoolFinanceNavigation::contains($action)) {
            return 'App\\Modules\\Scolarite\\';
        }

        foreach (['Technique', 'Socle', 'RH', 'Scolarite', 'Pedagogie', 'Finances', 'Assiduite', 'Communication', 'VieScolaire', 'Logistique'] as $module) {
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
