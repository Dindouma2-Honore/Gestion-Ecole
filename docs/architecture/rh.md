# Ressources humaines

## Objectif

RH gère le personnel et son parcours : employés, enseignants, postes, contrats, absences, congés, paie, primes, formations, discipline et recrutement.

## Code

- **Modèles principaux** : `Employe`, `Enseignant`, `Contrat`, `Conge`, `AbsencePersonnel`, `Pointage`, `BulletinPaie`, `Prime`, `Candidature`, `Formation`, `SanctionPersonnel`, `Remplacement` et référentiels RH.
- **Services** : `EmployeService`, `EnseignantService`, `ContratService`, `CongeService`, `PaieService`, `PointageService`, `PrimeService`, `AbsenceService`, `FormationService` et `DisciplinePersonnelService`.
- **Contrats** : personnel, enseignants, congés, paie, absences, contrats et lecture pédagogique des enseignants.
- **Événements** : acceptation ou rejet de candidature.
- **Filament** : employés, enseignants, contrats, absences, congés, paie, pointage, primes, formations, sanctions et recrutement.

## Communications

L’employé peut être rattaché à un utilisateur du Socle. Pédagogie obtient l’enseignant connecté et la liste des enseignants actifs via `EnseignantServiceInterface` et `EnseignantsPedagogiquesReadContract`. Finances consomme les contrats et la paie via Contracts. RH ne possède ni élèves ni inscriptions.

## Règle métier

Un enseignant est un personnel RH. Les rôles de principal, assistant ou enseignant de matière sont des affectations de Pédagogie, jamais des catégories de personnel RH.
