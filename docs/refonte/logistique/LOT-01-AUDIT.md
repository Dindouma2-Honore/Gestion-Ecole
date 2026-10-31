# LOGISTIQUE — LOT 1 (audit de l'existant)

Mémoire du LOT 1, conservée pour les lots suivants.

## Architecture existante

Monolithe modulaire conforme : `Contracts/` + `Services/` + `Models/` + `Filament/` +
`database/migrations/`, bindings dans `LogistiqueServiceProvider` (7 binds), migrations
chargées par `loadMigrationsFrom`, vues `logistique::`. Pas de `Data/`, `Enums/`,
`Events/`, `DTO/`, `Jobs/`, `Http/`, `Widgets/`, `Policies/`.
`README.md` est périmé (décrit `Http/Controllers/`, `Widgets/`, `Providers/` inexistants).

## Models (22)

| MODEL | RESPONSABILITÉ | OWNER CIBLE | DÉCISION |
|---|---|---|---|
| `Salle` | Espace (code, batiment, zone, type, capacite, etat, disponible, `niveau_id`) | Logistique | KEEP |
| `Equipement` | Bien individuel traçable (`numero_identification`, `numero_serie`, etat, valeur, garantie, `salle_id`, `responsable_id`) | Logistique | MODIFY |
| `EquipementHistoriqueLocalisation` | Journal des déplacements d'équipement | Logistique | KEEP |
| `DemandeIntervention` / `PlanMaintenancePreventive` / `TravauxInfrastructure` | Maintenance corrective / préventive / travaux bâtiment | Logistique | KEEP |
| `Livre` / `ExemplaireLivre` / `Emprunt` / `ReservationLivre` | Catalogue biblio, prêts | Logistique | KEEP |
| `MenuCantine` / `AbonnementCantine` / `PresenceCantine` | Menus, abonnements, présences repas | Logistique | MODIFY |
| `Vehicule` / `CircuitTransport` / `ArretCircuit` / `InscriptionTransport` / `PresenceTransport` / `IncidentTransport` | Transport scolaire | Logistique | KEEP |
| `Evenement` / `EvenementParticipant` / `EvenementPhoto` | Événements, participants, photos (→ GED Socle) | Logistique | KEEP |

## Tables (22)

`salles`, `travaux_infrastructure`, `equipements`, `equipement_historique_localisations`,
`demandes_intervention`, `plans_maintenance_preventive`, `livres`, `exemplaires_livres`,
`emprunts`, `reservations_livres`, `abonnements_cantine`, `menus_cantine`,
`presences_cantine`, `vehicules`, `circuits_transport`, `arrets_circuit`,
`inscriptions_transport`, `presences_transport`, `incidents_transport`, `evenements`,
`evenement_participants`, `evenement_photos`.
22 `Schema::create` + 1 refonte `2026_09_13_100000` (ALTER + backfill).
Aucune FK inter-module (ids en `unsignedBigInteger` commentés `// module X`).

## Catégories / catalogue (état LOT 1)

- Aucune table de catalogue. Deux colonnes libres : `equipements.categorie`
  (varchar 100, nullable) et `livres.categorie` (varchar 100, nullable).
- Aucune seed, aucun enum, aucune contrainte, aucun code catégorie.
- Aucune distinction durable/consommable dans la donnée (vocabulaire UI uniquement).
- Décision LOT 1 : créer le catalogue en LOT 2, conserver `equipements.categorie` legacy.

## Matériel / états

- `Equipement` = bien individuel traçable uniquement (`EquipementResource:41` :
  « ne jamais mélanger avec le module Stocks (E.47) » — module inexistant).
- `equipements.etat ∈ {bon, a_reparer, hors_service, mis_au_rebut}` ;
  `salles.etat ∈ {bon, a_renover, hors_service}` + `disponible` ;
  `vehicules.etat ∈ {bon, en_maintenance, hors_service}` ;
  `demandes_intervention.statut ∈ {signalee, diagnostiquee, en_reparation, terminee}` ;
  `travaux.statut`, `exemplaires_livres.etat/statut`, `abonnements.statut`,
  `circuits.statut`, `evenements.statut`.
- Photos : `evenement_photos.document_id` → GED Socle ; aucun champ photo sur `Equipement`.

## Stock / affectations / approvisionnements / finance / locations / inventaires

- **Stock : aucun.** Mouvements = localisation uniquement (`equipement_historique_localisations`).
- **Affectation** = `equipement.salle_id` ; liens niveau : `salles.niveau_id`, `livres.niveau_id`.
- **Approvisionnements : absents.** `equipements.fournisseur` = string libre.
- **Casse/paiements : absents** (conforme). Montants purs : `abonnements_cantine.montant`,
  `emprunts.penalite`, `equipements.valeur_acquisition`, `demandes_intervention.cout`,
  `travaux.budget_prevu/cout_reel`. `CantineService` : `montant => 0` codé en dur ;
  `MaintenanceService` : appel `DepenseServiceContract` commenté.
- **Locations : absentes.**
- **Inventaires / bilan annuel / bilan par niveau : inexistants** (impossibles aujourd'hui).

## Contracts

| CONTRACT | OWNER | CONSUMER | CONFORME | ACTION |
|---|---|---|---|---|
| `InfrastructureServiceInterface` | Logistique | Pédagogie | OUI | KEEP |
| `EquipementServiceInterface` | Logistique | interne | OUI (`object`) | KEEP / DTO LOT+1 |
| `MaintenanceServiceInterface` | Logistique | `EquipementService` | OUI | KEEP |
| `BibliothequeServiceInterface` | Logistique | interne | NON (`Model $emprunteur`) | MODIFY LOT+1 |
| `CantineServiceInterface` / `TransportServiceInterface` / `EvenementServiceInterface` | Logistique | interne | OUI | KEEP |

Entrants : Socle (`AuditServiceContract`, `DocumentServiceContract`,
`AnneeScolaireServiceContract`, `ParametrageServiceContract`) + VieScolaire
`SanteServiceInterface`.

## Dépendances inter-modules

| SOURCE | DESTINATION | CONFORME | ACTION |
|---|---|---|---|
| Logistique | Socle Contracts | OUI | KEEP |
| Logistique | VieScolaire Contract | OUI code / non tracké Deptrac | déclarer layer |
| Logistique → `DB::table('employes')` ×7, `eleves` ×2, `annee_scolaires`, `users` | Filament | NON | MODIFY LOT+1 |
| Pédagogie → `InfrastructureServiceInterface` | Contract | OUI | KEEP |
| Pédagogie → `DB::table('salles')` (blade `voir-programme`) | table | NON | MODIFY LOT+1 |
| VieScolaire → `Logistique\Filament\EvenementResource` | internal | NON | MODIFY/SPLIT LOT+1 |

## Filament / habilitations / audit

- 6 Pages + 10 Resources (navigation masquée, liées par les Pages de section).
- Gating : `app/Filament/Support/ModuleAccess.php` → `module.logistique` + codes
  granulaires (`logistique.salles` pour `SalleResource|EquipementResource`,
  `logistique.evenements`, `logistique.transport`, `logistique.cantine`,
  `logistique.bibliotheque` — `LivreResource` non couvert, fallback module).
- Aucune Policy Logistique ; audit via `AuditServiceContract` sur
  `declarerHorsService` et `mettreAuRebut` seulement.

## Violations architecturales

- Deptrac : **22 violations historiques (toutes Scolarité), 0 liée à Logistique.**
- Non détectées : VieScolaire → `Logistique\Filament\EvenementResource` (VieScolaire
  n'est pas une layer) ; `LogistiqueInternal → VieScolaire\SanteServiceInterface` ;
  tous les `DB::table()` cross-module (strings) ; `BibliothequeServiceInterface(Model)`.
- `LogistiqueInternal` n'a pas accès à `FinancesContracts` dans le ruleset.

## Tests

`tests/Feature/Logistique/` : 8 fichiers, **38 tests / 68 assertions, PASS (Sail)**.
Faiblesses : 0 `expectException`, test « historique » n'asserte que `salle_id`,
aucun test d'unicité, aucun seed de matériau, aucune couverture habilitation/audit.

## Legacy

- `CantineService` : montant `0` codé en dur ; `MaintenanceService` : TODO Finances ;
  `EvenementService` : TODOs workflow/budget ; `InfrastructureService:58-63` : TODO D.26.
- Exceptions jamais levées : `NumeroIdentificationDejaExistantException`,
  `GarantieExpireeException`.
- Commentaires de migration faux : `annee_scolaire_id` annoncé « module Scolarité »
  alors que AcademicYear appartient au Socle.
