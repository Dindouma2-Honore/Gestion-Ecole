<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Models\User;
use App\Modules\Socle\Contracts\HabilitationServiceContract;
use App\Modules\Socle\Exceptions\FonctionnaliteIntrouvableException;
use App\Modules\Socle\Models\HabilitationRoleFonctionnalite;

final class ModuleAccess
{
    /** @var array<string, list<string>> */
    private const MODULE_ROLES = [
        'Socle' => ['Fondateur'],
        'RH' => ['Fondateur'],
        'Scolarite' => ['Fondateur'],
        'Pedagogie' => ['Fondateur'],
        'Finances' => ['Fondateur'],
        'Assiduite' => ['Fondateur'],
        'Communication' => ['Fondateur'],
        'VieScolaire' => ['Fondateur'],
        'Logistique' => ['Fondateur'],
    ];

    private const STAFF_ROLES = [
        'Fondateur',
        'Directeur',
        'Comptable',
        'SurveillantGeneral',
        'ChargeLogistique',
        'Enseignant',
    ];

    public static function canAccessModule(?User $user, string $module): bool
    {
        if (! $user || $user->statut !== 'actif') {
            return false;
        }

        // Fondateur, Admin, Administrateur & Super Admin have full module access
        if ($user->hasAnyRole(['Fondateur', 'Admin', 'Administrateur', 'Super Admin'])) {
            return true;
        }

        // Check if explicitly disabled in dynamic habilitations
        if (! self::dynamicAccess($user, $module)) {
            return false;
        }

        $moduleKey = strtolower($module);

        if ($user->can("module.{$moduleKey}") || $user->can("access_module_{$moduleKey}") || self::groupAccess($user, $module)) {
            return true;
        }

        return self::explicitlyActivatedInHabilitations($user, $module);
    }

    private static function explicitlyActivatedInHabilitations(User $user, string $module): bool
    {
        try {
            $userRoles = $user->getRoleNames()->reject(fn (string $role): bool => $role === 'Fondateur');
            if ($userRoles->isEmpty()) {
                return false;
            }

            $moduleCode = 'module.'.strtolower($module);

            return HabilitationRoleFonctionnalite::query()
                ->whereHas('role', fn ($q) => $q->whereIn('name', $userRoles))
                ->whereHas('fonctionnalite', fn ($q) => $q->where('code', $moduleCode))
                ->where('actif', true)
                ->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    public static function canStartRegistration(?User $user): bool
    {
        return $user?->statut === 'actif' && $user->hasAnyRole(self::STAFF_ROLES);
    }

    public static function canSeeModuleEntry(?User $user, string $module): bool
    {
        if (! $user || $user->statut !== 'actif') {
            return false;
        }

        if (! self::dynamicAccess($user, $module)) {
            return false;
        }

        if ($module === 'Scolarite' && self::canStartRegistration($user)) {
            return true;
        }

        return self::canAccessModule($user, $module);
    }

    public static function canAccessComponent(?User $user, string $component): bool
    {
        if (! $user || $user->statut !== 'actif') {
            return false;
        }

        $module = self::moduleFromComponent($component);

        if ($module === null) {
            return true;
        }

        if (! self::dynamicAccess($user, $module)) {
            return false;
        }

        $featureCode = self::featureCodeFromComponent($component);

        if ($featureCode !== null && ! $user->hasRole('Fondateur')) {
            try {
                if (! app(HabilitationServiceContract::class)->moduleActifPourUser($user, $featureCode)) {
                    return false;
                }
            } catch (FonctionnaliteIntrouvableException) {
                // Si le code de fonctionnalité granulaire n'existe pas en BDD, on garde l'accès module
            }
        }

        if ($module === 'Scolarite' && self::isRegistrationComponent($component)) {
            return self::canStartRegistration($user);
        }

        if ($module === 'Scolarite' && str_contains($component, '\\Filament\\Pages\\ScolariteDashboard')) {
            return self::canSeeModuleEntry($user, $module);
        }

        if ($module === 'Finances' && self::isPreRegistrationInvoiceComponent($component)) {
            return $user->hasAnyRole(['Fondateur', 'Comptable']);
        }

        return self::canAccessModule($user, $module);
    }

    public static function featureCodeFromComponent(string $component): ?string
    {
        return match (true) {
            // Référentiels propres au module Personnel (avant les noms génériques du Socle).
            str_contains($component, 'PersonnelRoleResource') || str_contains($component, 'PosteAdministratifResource') || str_contains($component, 'CategoriePersonnelResource') => 'rh.employes',

            // Socle
            str_contains($component, 'UserResource') => 'socle.utilisateurs',
            str_contains($component, 'RoleResource') || str_contains($component, 'PermissionResource') => 'socle.roles',
            str_contains($component, 'ActivityLogResource') => 'socle.audit',
            str_contains($component, 'FormatNumerotationResource') || str_contains($component, 'JourFerieResource') || str_contains($component, 'SeuilValidationResource') || str_contains($component, 'AnneeScolaireResource') => 'socle.parametres',

            // RH
            str_contains($component, 'EmployeResource') => 'rh.employes',
            str_contains($component, 'EnseignantResource') => 'rh.enseignants',
            str_contains($component, 'CongeResource') => 'rh.conges',
            str_contains($component, 'BulletinPaieResource') || str_contains($component, 'FichePaieResource') || str_contains($component, 'PaieResource') => 'rh.paie',

            // Scolarité
            str_contains($component, 'RemiseExonerationResource') => 'scolarite.inscriptions',
            str_contains($component, 'EleveResource') => 'scolarite.eleves',
            str_contains($component, 'InscriptionResource') => 'scolarite.inscriptions',
            str_contains($component, 'ClasseResource') || str_contains($component, 'NiveauResource') => 'scolarite.classes',

            // Pédagogie
            str_contains($component, 'MatiereResource') || str_contains($component, 'ProgrammeResource') => 'pedagogie.programmes',
            str_contains($component, 'SeanceResource') || str_contains($component, 'CahierTexteResource') => 'pedagogie.seances',
            str_contains($component, 'EvaluationResource') || str_contains($component, 'NoteResource') => 'pedagogie.evaluations',
            str_contains($component, 'DisciplineEleveResource') => 'pedagogie.discipline',

            // Finances
            str_contains($component, 'FactureResource') || str_contains($component, 'FacturePreinscriptionResource') || str_contains($component, 'CreateFrais') => 'finances.factures',
            str_contains($component, 'PaiementResource') || str_contains($component, 'ReglementResource') => 'finances.paiements',
            str_contains($component, 'CaisseResource') || str_contains($component, 'MouvementCaisseResource') => 'finances.caisse',
            str_contains($component, 'BudgetResource') || str_contains($component, 'LigneBudgetaireResource') => 'finances.budget',

            // Communication
            str_contains($component, 'AnnonceResource') || str_contains($component, 'CommunicationResource') => 'communication.annonces',
            str_contains($component, 'RendezVousResource') => 'communication.rendezvous',
            str_contains($component, 'NotificationResource') || str_contains($component, 'TemplateNotificationResource') => 'communication.notifications',

            // Assiduité & Vie Scolaire
            str_contains($component, 'PointageResource') || str_contains($component, 'AbsenceResource') => 'assiduite.pointage',
            str_contains($component, 'ReclamationResource') || str_contains($component, 'IncidentResource') || str_contains($component, 'SanctionResource') => 'assiduite.reclamations',
            str_contains($component, 'VisiteurResource') || str_contains($component, 'VisiteInfirmerieResource') => 'viescolaire.visiteurs',
            str_contains($component, 'DossierSanteResource') => 'viescolaire.sante',
            str_contains($component, 'SortieEleveResource') => 'viescolaire.sorties',

            // Logistique
            str_contains($component, 'SalleResource') || str_contains($component, 'EquipementResource') => 'logistique.salles',
            str_contains($component, 'EvenementResource') => 'logistique.evenements',
            str_contains($component, 'TransportResource') => 'logistique.transport',
            str_contains($component, 'CantineResource') => 'logistique.cantine',
            str_contains($component, 'BibliothequeResource') => 'logistique.bibliotheque',

            default => null,
        };
    }

    private static function dynamicAccess(?User $user, string $module): bool
    {
        if (! $user) {
            return false;
        }

        try {
            return app(HabilitationServiceContract::class)
                ->moduleActifPourUser($user, 'module.'.strtolower($module));
        } catch (FonctionnaliteIntrouvableException) {
            return false;
        }
    }

    private static function groupAccess(User $user, string $module): bool
    {
        try {
            return app(HabilitationServiceContract::class)
                ->accesAccordeParGroupe($user, 'module.'.strtolower($module));
        } catch (FonctionnaliteIntrouvableException) {
            return false;
        }
    }

    public static function moduleFromComponent(string $component): ?string
    {
        if (str_contains($component, 'RemiseExonerationResource')) {
            return 'Scolarite';
        }

        foreach (array_keys(self::MODULE_ROLES) as $module) {
            if (str_starts_with($component, "App\\Modules\\{$module}\\")) {
                return $module;
            }
        }

        return null;
    }

    private static function isRegistrationComponent(string $component): bool
    {
        return str_starts_with(
            $component,
            'App\\Modules\\Scolarite\\Filament\\Resources\\InscriptionResource',
        );
    }

    private static function isPreRegistrationInvoiceComponent(string $component): bool
    {
        return str_starts_with(
            $component,
            'App\\Modules\\Finances\\Filament\\Resources\\FacturePreinscriptionResource',
        );
    }

    private function __construct() {}
}
