# LOGISTIQUE — LOT 2 (catalogue de matériel)

Mémoire du LOT 2 : référentiel des catégories de matériel (code, nom, nature
DURABLE / CONSOMMABLE, activation, ordre) et reliement aux équipements.
Mémoire LOT 1 : `LOT-01-AUDIT.md`.

## Périmètre livré

- Table `categories_materiel` + colonne `equipements.categorie_id` (additive).
- `equipements.categorie` (texte libre) **conservée telle quelle** : aucune
  suppression, aucun backfill approximatif, aucun changement de sémantique.
- `livres.categorie` **non touché** (bibliothèque ≠ catalogue matériel).
- Contract public `CatalogueMaterielInterface` (scalars uniquement) + Service.
- `EquipementService` : validation de l'affectation + `natureEquipement()`.
- Filament : `CategorieMaterielResource` (CRUD complet) + intégration dans
  `EquipementResource` et dans la page `EquipementsMaintenance`.
- Tests : 17 nouveaux cas métier + smoke Filament.

## Modèle de données

Migration `2026_10_03_000001_creer_catalogue_categorie_materiel.php` :

| TABLE / COLONNE | TYPE | CONTRAINTE | REMARQUE |
|---|---|---|---|
| `categories_materiel.id` | bigint PK | | |
| `code` | varchar(60) | UNIQUE | identifiant métier, ex. `MAT-PC` |
| `nom` | varchar(120) | | libellé affiché |
| `nature` | enum | `durable` \| `consommable` | **appartient au catalogue**, pas à l'équipement |
| `actif` | bool | default `true` | désactivation ≠ suppression |
| `ordre` | unsigned int | default `0` | tri du catalogue |
| `equipements.categorie_id` | bigint NULL + index | `equipements_categorie_id_index` | **pas de FK** (convention module : ids sans contrainte inter-table) |

Aucune écriture dans l'historique des migrations ; `down()` recolle la colonne et
supprime la table.

## Contract & Service

`Contracts/CatalogueMaterielInterface.php` (aucun Model exposé) :

| MÉTHODE | RÔLE | EXCEPTIONS MÉTIER |
|---|---|---|
| `lister(bool $inclusInactives = false)` | catalogue trié `ordre`, `nom` | — |
| `creer(code, nom, nature, ordre = 0): int` | crée **active** | champs invalides, nature invalide, code déjà utilisé |
| `modifier(id, code, nom, nature, ordre)` | identité de la catégorie | inconnue, champs, nature, code déjà utilisé |
| `activer(id)` / `desactiver(id)` | activation (inactivée reste consultable) | inconnue |
| `supprimer(id)` | physique **seulement si non utilisée** | inconnue, catégorie utilisée |
| `nature(id): string` | `durable`\|`consommable`, activation indifférente | inconnue |
| `validerAffectation(id)` | doit exister **et** être active | inconnue, inactive |

- Nature acceptée sans tenir compte de la casse (`DURABLE`, `durable`…),
  normalisée vers `durable`/`consommable`.
- 6 exceptions métier créées dans `Modules/Logistique/Exceptions/`.
- Binding ajouté dans `LogistiqueServiceProvider` :
  `CatalogueMaterielInterface → CatalogueMaterielService`.
- `EquipementService` : ctor enrichi de `CatalogueMaterielInterface`,
  `enregistrerEquipement()` appelle `validerAffectation()` dès que
  `categorie_id` est rempli, et `natureEquipement(int): ?string` retourne
  `null` pour un équipement legacy sans catégorie.

## Filament

| FICHIER | ÉVOLUTION |
|---|---|
| `Resources/CategorieMaterielResource.php` + `Pages/` (list/create/edit) | **créé** : formulaire code/nom/nature/ordre, table + badges nature + colonne active, filtres nature/état, action bascule activation via le Service, `DeleteAction` via le Service et **masquée si la catégorie est utilisée** |
| `Resources/EquipementResource.php` | Select `categorie_id` (catalogue) + champ legacy en lecture seule non persisté (`dehydrated(false)`, masqué à la création), colonnes `Catégorie` / `Nature` / `Catégorie legacy`, filtre `categorie_id` + toggle **« Sans catégorie (à normaliser) »** |
| `Pages/EquipementsMaintenance.php` | lien **« Catégories de matériel »** ajouté |
| `Modules/Logistique` navigation | aucune nouvelle entrée : le Resource est lié par la page de section (convention LOT 1) |

Comportements clés :

- Seules les catégories **actives** sont proposées ; la catégorie déjà liée reste
  affichée (« — inactive ») même après désactivation → aucun lien perdu.
- Les colonnes « Catégorie » / « Nature » interrogent la relation
  `categorie()->first()` (et non l'attribut) pour éviter l'ombre d'attribut.
- L'UI n'écrit jamais dans `equipements.categorie` (champ désactivé + non
  déshydraté) : la valeur legacy ne peut pas être écrasée par l'écran.

## Habilitations

- `app/Filament/Support/habilitations-catalogue.json` : ajout d'une seule
  entrée `…CategorieMaterielResource → logistique.equipements` (même
  fonctionnalité que `EquipementResource`, `PlanMaintenancePreventiveResource`,
  `DemandeInterventionResource` et la page `EquipementsMaintenance`).
- **Aucun nouveau code de fonctionnalité** → aucune migration de données
  d'habilitation, aucune modification des matrices existantes.
- `ModuleAccess.php` : inchangé (le catalogue JSON fait autorité ; le fallback
  `match` ne s'applique qu'absent du catalogue).

## Décisions & arbitrages

1. **Ombre d'attribut** : `$equipement->categorie` reste la valeur legacy
   (string) ; la catégorie catalogue s'obtient par `$equipement->categorie()->first()`.
   Choix de ne pas renommer pour ne pas casser les 7 usages legacy.
2. **Double autorité assumée** : toutes les Resources Logistique passent par
   `RecordModal` (édition en modale = chemin CRUD le plus utilisé) ; le Service
   reste l'autorité programmatique (validation d'affectation, activation,
   suppression), la Resource porte les règles de formulaire (unikité/required).
3. **Désactivation ≠ suppression** : la catégorie désactivée reste dans
   l'historique et reste consultable (`lister(true)`), la suppression physique
   est refusée dès qu'un équipement la référence.
4. **Deptrac** : le Contract n'importe aucune Exception (règle
   `LogistiqueContracts` sans sorties) ; les `@throws` utilisent les noms courts,
   convention déjà portée par `TransportServiceInterface` /
   `BibliothequeServiceInterface`.

## Tests exécutés (résultats)

```
./vendor/bin/sail test tests/Feature/Logistique
→ 55 tests / 115 assertions — PASS   (LOT 1 : 38 tests / 68 assertions)
./vendor/bin/sail bin pint --test <23 fichiers créés/modifiés>
→ passed
./vendor/bin/sail bin deptrac analyse
→ 22 violations (baseline historique, toutes Scolarité) — 0 liée à Logistique
```

`tests/Feature/Logistique/CatalogueMaterielTest.php` (17 cas) : création,
tri par ordre, code déjà utilisé (création **et** modification), champs
obligatoires, modification complète, désactivation conservée + réactivation,
nature DURABLE, nature CONSOMMABLE, nature non autorisée, équipement avec
catégorie valide (+ `natureEquipement`), catégorie inconnue (service +
`nature()`), catégorie inactive, conservation de `equipements.categorie`
legacy, équipement legacy sans `categorie_id` (`natureEquipement() === null`),
suppression refusée si utilisée, suppression autorisée sinon, et smoke Filament
(pages liste/création/édition du catalogue + inventaire en **200 OK**).

## Anomalies constatées (préexistantes, hors périmètre)

- `tests/Feature/HabilitationCatalogTest` échoue sur
  `App\Modules\Pedagogie\Filament\Resources\DecisionPedagogiqueResource`
  absente du catalogue JSON : vérifié **sur le JSON du HEAD** — cette ressource
  manquait déjà avant le LOT 2 (la ressource Logistique est, elle, désormais
  présente). Non corrigé : hors module Logistique.
- Suite globale : échecs préexistants côté Scolarité (ressources Socle
  `CycleResource`/`NiveauResource`/… introuvables) et Pédagogie (500 sur
  l'analyse IA) — sans rapport avec ce lot.
- Base de développement MySQL indisponible (`getaddrinfo for mysql failed`) :
  la migration n'a pas pu être appliquée en local ; elle est validée par la
  suite de tests (`RefreshDatabase`, sqlite :memory:). À appliquer avec
  `./vendor/bin/sail artisan migrate` quand le conteneur sera relancé.

## Non couvert (lots suivants)

- Normalisation / backfill de `equipements.categorie` legacy (le toggle
  « Sans catégorie » sert justement à piloter ce travail).
- Stocks, mouvements, approvisionnements, locations, inventaires, Finance,
  photos d'équipement.
- `livres.categorie` (référentiel bibliothèque distinct).

## Fichiers créés / modifiés

Créés : `Enums/NatureMateriel.php`, `Models/CategorieMateriel.php`,
`Contracts/CatalogueMaterielInterface.php`, `Services/CatalogueMaterielService.php`,
6 `Exceptions/CategorieMateriel*.php` + `NatureMaterielInvalideException.php`,
migration `2026_10_03_000001_creer_catalogue_categorie_materiel.php`,
`Filament/Resources/CategorieMaterielResource.php` (+ `Pages/`),
`tests/Feature/Logistique/CatalogueMaterielTest.php`.

Modifiés : `Models/Equipement.php` (fillable + relation `categorie()`),
`Contracts/EquipementServiceInterface.php` (`natureEquipement`),
`Services/EquipementService.php` (ctor + validation + `natureEquipement`),
`LogistiqueServiceProvider.php` (binding),
`Filament/Resources/EquipementResource.php`, `Filament/Pages/EquipementsMaintenance.php`,
`app/Filament/Support/habilitations-catalogue.json`.
