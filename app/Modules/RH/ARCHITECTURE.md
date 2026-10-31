# Architecture du module Ressources humaines

## Objectif

Gérer le personnel administratif et enseignant, son dossier professionnel, ses contrats, absences/congés, formations, discipline, pointages et paie.

## Organisation du code

- `Models/` : `Employe`, `Enseignant`, contrats et avenants, absences/congés, pointages, formations/participants, primes, paie, carrière et candidatures.
- `Services/` : services d’employés et d’enseignants, contrats, congés, absences, formations, discipline, primes, avances, pointage, paie et documents PDF.
- `Contracts/` : interfaces publiques notamment pour employés, enseignants, contrats, absences, paie, congés et pointages.
- `Filament/Resources/` : ressources de personnel, enseignants, contrats, congés, candidatures, formations, paies, primes et autres référentiels RH; Pages/Widgets de tableau de bord et historique.
- `Events/` et `Listeners/` : événements de candidature acceptée/rejetée consommés par le listener de communication; le listener déclenche le contrat d’envoi WhatsApp.
- `Observers/` : `BulletinPaieObserver` applique les réactions prévues aux changements de bulletin de paie.
- `Traits/HasWorkflowStatus.php` fournit un comportement de statut réutilisable dans le module.
- `RHServiceProvider` lie les contrats, charge migrations/vues et enregistre l’observer.
- Aucun dossier `Jobs/` n’est présent actuellement.

## Communications

Pédagogie obtient les enseignants via `EnseignantServiceInterface`, et non par un référentiel local. RH consomme les contrats du Socle pour année scolaire, audit et paramétrage; la paie utilise aussi le contrat de caisse Finances lorsqu’un mouvement de caisse est requis. Les événements candidat isolent RH du canal de notification. Les règles RH restent dans les services et ne doivent pas être transférées dans les Resources Filament.
