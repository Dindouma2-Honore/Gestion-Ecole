<?php

declare(strict_types=1);

namespace App\Filament\Support;

final class HabilitationCatalog
{
    public static function features(): array
    {
        return self::data()['features'];
    }

    public static function components(): array
    {
        return self::data()['components'];
    }

    public static function featureFor(string $component): ?string
    {
        foreach (self::components() as $class => $feature) {
            if ($component === $class || str_starts_with($component, $class.'\\') || str_starts_with($component, $class.'@')) {
                return $feature;
            }
        }

        return null;
    }

    /**
     * Codes affichés dans les matrices d'habilitations.
     *
     * Le catalogue complet conserve volontairement les anciennes permissions
     * pour ne pas casser les contrôles d'accès déjà enregistrés en base. En
     * revanche, l'administration doit présenter uniquement les menus réellement
     * disponibles dans la navigation actuelle des modules.
     *
     * @return list<string>
     */
    public static function visibleMenuFeatureCodes(): array
    {
        return [
            // Accès global aux modules
            'module.socle',
            'module.rh',
            'module.scolarite',
            'module.pedagogie',
            'module.communication',
            'module.finances',
            'module.assiduite',
            'module.viescolaire',
            'module.logistique',
            'module.technique',

            // Administration
            'socle.utilisateurs',
            'socle.roles',
            'socle.parametres',
            'socle.audit',

            // Ressources humaines
            'rh.recrutement',
            'rh.employes',
            'rh.contrats',
            'rh.conges',
            'rh.paie',

            // Scolarité
            'scolarite.eleves',
            'scolarite.inscriptions',
            'scolarite.classes',
            'scolarite.paiements',

            // Pédagogie
            'pedagogie.enseignements',
            'pedagogie.programmes',
            'pedagogie.emplois_du_temps',
            'pedagogie.seances',
            'pedagogie.evaluations',
            'pedagogie.bulletins',

            // Communication
            'communication.annonces',
            'communication.rendezvous',
            'communication.notifications',
            'communication.templates',

            // Finances
            'finances.caisse',
            'finances.paiements',
            'finances.depenses',
            'finances.rapports',
            'finances.grilles_salariales',

            // Assiduité
            'assiduite.dashboard',
            'assiduite.eleves',
            'assiduite.absences',
            'assiduite.personnel',
            'assiduite.justifications',
            'assiduite.anomalies',
            'assiduite.rapports',

            // Vie scolaire
            'viescolaire.sorties',
            'viescolaire.visiteurs',
            'viescolaire.sante',
            'viescolaire.incidents',
            'viescolaire.reclamations',
            'viescolaire.calendrier',
            'viescolaire.evenements',
            'viescolaire.rapports',

            // Logistique
            'logistique.salles',
            'logistique.equipements',
            'logistique.transport',
            'logistique.cantine',
            'logistique.bibliotheque',

            // Opérations standard par module (consulter, modifier, exécuter, imprimer)
            'socle.consulter', 'socle.modifier', 'socle.executer', 'socle.imprimer',
            'rh.consulter', 'rh.modifier', 'rh.executer', 'rh.imprimer',
            'scolarite.consulter', 'scolarite.modifier', 'scolarite.executer', 'scolarite.imprimer',
            'pedagogie.consulter', 'pedagogie.modifier', 'pedagogie.executer', 'pedagogie.imprimer',
            'communication.consulter', 'communication.modifier', 'communication.executer', 'communication.imprimer',
            'finances.consulter', 'finances.modifier', 'finances.executer', 'finances.imprimer',
            'assiduite.consulter', 'assiduite.modifier', 'assiduite.executer', 'assiduite.imprimer',
            'viescolaire.consulter', 'viescolaire.modifier', 'viescolaire.executer', 'viescolaire.imprimer',
            'logistique.consulter', 'logistique.modifier', 'logistique.executer', 'logistique.imprimer',
            'technique.consulter', 'technique.modifier', 'technique.executer', 'technique.imprimer',
        ];
    }

    private static function data(): array
    {
        static $data;

        return $data ??= json_decode(file_get_contents(__DIR__.'/habilitations-catalogue.json'), true, flags: JSON_THROW_ON_ERROR);
    }
}
