<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Modules\Communication\Filament\Pages\CommunicationDashboard;
use Filament\Resources\Resource;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ModuleExperience
{
    private const DASHBOARDS = [
        'RH' => 'RHDashboard', 'Scolarite' => 'ScolariteDashboard', 'Pedagogie' => 'PedagogieDashboard',
        'Finances' => 'FinancesDashboard', 'Communication' => 'CommunicationDashboard', 'Assiduite' => 'AssiduiteDashboard',
        'Logistique' => 'LogistiqueDashboard', 'VieScolaire' => 'VieScolaireDashboard', 'Technique' => 'TechnicalDashboard',
    ];

    private const GUIDES = [
        'RH' => 'Gérez séparément le recrutement, les dossiers du personnel, les contrats, les absences et la paie. Choisissez la rubrique concernée dans le menu latéral.',
        'Scolarite' => 'Consultez les dossiers élèves, leurs responsables et les inscriptions. Choisissez une rubrique, recherchez le dossier concerné, puis utilisez ses actions pour le consulter ou le mettre à jour.',
        'Pedagogie' => 'Organisez les matières, les programmes, les emplois du temps et les évaluations. Sur le tableau de bord, les filtres de classe et de période permettent de préciser les indicateurs.',
        'Finances' => 'Suivez les encaissements, la caisse, les dépenses et le recouvrement. Sélectionnez la rubrique et le périmètre souhaités avant de consulter ou enregistrer une opération.',
        'Communication' => 'Préparez vos annonces, messages et rendez-vous dans leurs rubriques respectives. Consultez l’historique des notifications pour vérifier les statuts d’envoi et le catalogue WhatsApp pour gérer les modèles.',
        'Assiduite' => 'Consultez les présences et retards, puis traitez les anomalies et les justificatifs dans leurs écrans dédiés. Vérifiez la date et les personnes concernées avant toute décision.',
        'VieScolaire' => 'Suivez les sorties, les visiteurs, la santé et les incidents. Utilisez la rubrique correspondant à la situation ; le tableau de bord signale les éléments prioritaires.',
        'Logistique' => 'Gérez les espaces, les équipements, le transport, la cantine et les emprunts dans leurs rubriques dédiées. Consultez les alertes et ouvrez le service concerné pour agir.',
        'Technique' => 'Consultez l’état de la plateforme, les sauvegardes, les intégrations et les journaux. Vérifiez la date des mesures avant de tirer une conclusion ou de lancer une opération.',
    ];

    private const MENU_HELP = [
        'CandidatureResource' => 'Suivez les candidatures, les entretiens et les décisions de recrutement.',
        'EmployeResource' => 'Consultez les dossiers du personnel et mettez à jour leurs informations professionnelles.',
        'EnseignantResource' => 'Gérez les informations et affectations des enseignants.',
        'ContratResource' => 'Consultez les contrats du personnel, leurs dates et leurs conditions de rémunération.',
        'AbsencePersonnelResource' => 'Enregistrez et suivez les absences du personnel et leurs justificatifs.',
        'CongeResource' => 'Consultez les demandes de congé et utilisez les actions autorisées pour les traiter.',
        'AvanceSalaireResource' => 'Suivez les demandes d’avances sur salaire et leur traitement.',
        'BulletinPaieResource' => 'Préparez et consultez les bulletins de paie pour le personnel et la période concernés.',
        'HistoriquePaies' => 'Retrouvez les paies antérieures et consultez le détail de la période recherchée.',
        'EleveResource' => 'Consultez les dossiers des élèves et leurs informations scolaires.',
        'ParentTuteurResource' => 'Gérez les responsables des élèves et leurs coordonnées.',
        'InscriptionResource' => 'Créez et suivez les inscriptions des élèves pour l’année scolaire concernée.',
        'ClasseResource' => 'Gérez les classes, leurs capacités et leur organisation.',
        'MatiereResource' => 'Créez les matières, domaines et activités dans le catalogue commun. Les coefficients annuels se règlent dans Offre pédagogique. Ouvrez une ligne pour consulter ou modifier ses informations.',
        'OffrePedagogiqueResource' => 'Choisissez l’année et le niveau. Une offre sans classe s’applique à toutes les classes du niveau ; une offre de classe constitue une adaptation prioritaire. Désactivez une offre pour conserver son historique.',
        'AffectationPedagogiqueResource' => 'Choisissez une classe et un enseignant RH. En organisation par classe, affectez un principal ou des assistants ; sans spécialité, un assistant est généraliste. En organisation par matière, choisissez une matière de l’offre. Les dates délimitent la responsabilité.',
        'ProgrammeResource' => 'Importez votre programme PDF ou Word depuis une offre autorisée. Corrigez la structure détectée puis validez-la ; chaque import conserve une nouvelle version.',
        'SuiviProgressionResource' => 'Comparez le programme réalisé au calendrier scolaire. Ouvrez une ligne pour voir les éléments et les séances à l’origine du calcul.',
        'ProgressionResource' => 'Après une séance réalisée, choisissez la leçon traitée, indiquez son avancement et le devoir éventuel. Seuls les éléments terminés comptent dans la progression.',
        'EmploiDuTempsResource' => 'Organisez les cours par classe, enseignant, salle et créneau.',
        'SeanceResource' => 'Consultez les séances prévues et renseignez leur déroulement.',
        'EvaluationResource' => 'Préparez les évaluations, leurs dates et leurs barèmes.',
        'NoteResource' => 'Saisissez et vérifiez les notes, puis utilisez les étapes de validation disponibles.',
        'BulletinResource' => 'Consultez et finalisez les bulletins scolaires pour la classe et la période concernées.',
        'PaiementResource' => 'Enregistrez et consultez les paiements et leurs pièces justificatives.',
        'DepenseResource' => 'Suivez les dépenses et leur validation avant décaissement.',
        'MouvementCaisseResource' => 'Consultez les entrées et sorties de caisse et leurs justificatifs.',
        'FacturePreinscriptionResource' => 'Contrôlez les factures liées aux inscriptions et leur règlement.',
        'CommunicationTemplateResource' => 'Préparez les modèles WhatsApp, soumettez-les à Meta et vérifiez leur validation avant utilisation.',
        'NotificationEnvoyeeResource' => 'Vérifiez les notifications envoyées et leurs statuts de livraison.',
        'MessageParentResource' => 'Préparez les messages destinés aux parents et choisissez les destinataires concernés.',
        'AnnonceResource' => 'Rédigez les annonces et choisissez leur audience avant publication.',
        'RendezVousResource' => 'Organisez les rendez-vous et suivez leurs confirmations et comptes rendus.',
        'PresencesEleves' => 'Consultez les présences des élèves pour la date et la classe recherchées.',
        'JustificationsAnomalies' => 'Vérifiez les anomalies d’assiduité et traitez les justificatifs associés.',
        'SortiesEleves' => 'Enregistrez et contrôlez les sorties des élèves.',
        'Visiteurs' => 'Suivez les entrées et sorties des visiteurs de l’établissement.',
        'SanteInfirmerie' => 'Consultez les dossiers de santé autorisés et suivez les passages à l’infirmerie.',
        'IncidentsReclamations' => 'Signalez les incidents, affectez leur traitement et suivez leur résolution.',
        'Infrastructures' => 'Gérez les espaces de l’établissement et les travaux qui les concernent.',
        'EquipementsMaintenance' => 'Consultez l’inventaire et organisez les interventions de maintenance.',
        'TransportScolaire' => 'Gérez les circuits, les véhicules et le service de transport scolaire.',
        'Cantine' => 'Gérez les abonnements et le service de restauration.',
        'Bibliotheque' => 'Consultez le catalogue et suivez les emprunts et les retours.',
        'Backups' => 'Consultez les sauvegardes et leurs résultats avant toute opération de restauration.',
        'TechnicalLogs' => 'Consultez les journaux techniques pour rechercher une erreur ou retracer un événement.',
        'Integrations' => 'Vérifiez l’état des connexions aux services externes.',
    ];

    public static function moduleForScopes(array $scopes): ?array
    {
        foreach ($scopes as $scope) {
            if (SchoolFinanceNavigation::contains($scope)) {
                return collect(ModuleCatalog::visibleFor(auth()->user()))->firstWhere('module', 'Scolarite');
            }
            if (preg_match('/^App\\\\Modules\\\\([^\\\\]+)\\\\/', $scope, $matches)) {
                return collect(ModuleCatalog::visibleFor(auth()->user()))->firstWhere('module', $matches[1]);
            }
        }

        return null;
    }

    public static function dashboard(array $scopes): ?array
    {
        $module = self::moduleForScopes($scopes);
        if (! $module || ! isset(self::DASHBOARDS[$module['module']])) {
            return null;
        }
        $class = 'App\\Modules\\'.$module['module'].'\\Filament\\Pages\\'.self::DASHBOARDS[$module['module']];

        return in_array($class, $scopes, true) ? $module : null;
    }

    public static function help(array $scopes): ?array
    {
        $specific = AdministrationHelp::forScopes($scopes);
        if ($specific) {
            return $specific;
        }
        $module = self::moduleForScopes($scopes);
        if (! $module || ! isset(self::GUIDES[$module['module']])) {
            return null;
        }
        $title = $module['name'];
        foreach ($scopes as $scope) {
            if (is_subclass_of($scope, Resource::class)) {
                $title = $scope::getPluralModelLabel();
                break;
            }
            if (method_exists($scope, 'getNavigationLabel')) {
                $title = $scope::getNavigationLabel();
                break;
            }
        }

        if (self::dashboard($scopes)) {
            return ['title' => $module['name'], 'text' => self::GUIDES[$module['module']]];
        }
        $text = __('Ce menu regroupe les informations relatives à « :menu ».', ['menu' => $title]);
        foreach ($scopes as $scope) {
            foreach (explode('\\', $scope) as $part) {
                if (isset(self::MENU_HELP[$part])) {
                    $text = __(self::MENU_HELP[$part]);
                    break 2;
                }
            }
        }
        $isForm = collect($scopes)->contains(fn (string $scope): bool => str_contains($scope, '\\Create') || str_contains($scope, '\\Edit'));
        $text .= $isForm
            ? ' '.__('Renseignez les champs demandés, vérifiez les informations puis enregistrez.')
            : ' '.__('Utilisez les filtres et la recherche lorsqu’ils sont disponibles, puis ouvrez le dossier ou l’action souhaitée.');

        return ['title' => $title, 'text' => $text];
    }

    public static function canOpenUrl(?string $url): bool
    {
        if (! $url) {
            return false;
        }
        try {
            $route = app('router')->getRoutes()->match(Request::create($url));
        } catch (NotFoundHttpException|MethodNotAllowedHttpException) {
            return false;
        }
        $component = explode('@', $route->getActionName())[0];
        if (! str_starts_with($component, 'App\\Modules\\') || ! class_exists($component)) {
            return false;
        }
        if (method_exists($component, 'getResource')) {
            $component = $component::getResource();
        }
        if (! ModuleAccess::canAccessComponent(auth()->user(), $component)) {
            return false;
        }

        return is_subclass_of($component, Resource::class) ? $component::canViewAny() : $component::canAccess();
    }

    public static function shortcuts(string $module): array
    {
        $links = [];
        foreach (ModuleNavigation::forModule($module)->getNavigation() as $group) {
            if ($group->getLabel() === __('AUTRES MODULES')) {
                continue;
            }
            foreach ($group->getItems() as $item) {
                $url = $item->getUrl();
                $entry = collect(ModuleCatalog::visibleFor(auth()->user()))->firstWhere('module', $module);
                if (! $url || $url === ($entry['url'] ?? null)) {
                    continue;
                }
                $children = array_map(fn ($child): string => $child->getLabel(), $item->getChildItems());
                $links[$url] = ['title' => $item->getLabel(), 'url' => $url, 'icon' => $item->getIcon() ?: 'heroicon-o-arrow-right-circle',
                    'description' => $children ? implode(' · ', $children) : __('Consulter et gérer : :rubrique.', ['rubrique' => mb_strtolower($item->getLabel())])];
            }
        }

        if ($module === 'Communication') {
            foreach (app(CommunicationDashboard::class)->getModuleLinks() as $link) {
                if (self::canOpenUrl($link['url'])) {
                    $links[$link['url']] = ['title' => $link['label'], 'description' => $link['description'], 'icon' => $link['icon'], 'url' => $link['url']];
                }
            }
        }

        return array_values($links);
    }
}
