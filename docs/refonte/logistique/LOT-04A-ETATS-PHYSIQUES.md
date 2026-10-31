# LOGISTIQUE — LOT 4A — ÉTATS PHYSIQUES DU MATÉRIEL

## Résumé
Le LOT 4A normalise la gestion de l'état physique des équipements (`bon`, `moyen`, `mauvais`, `hors_service`) en le séparant conceptuellement des futurs statuts opérationnels et du stock (prévus au LOT 4B). Il introduit l'Enum `EtatPhysiqueMateriel`, valide les états physiques côté Service, met à jour la ressource Filament et préserve la compatibilité avec les workflows existants de panne et de mise au rebut en attendant leur migration vers les cycles de vie au LOT 4B.

## Mémoires utilisées
- **LOT 1** : Audit initial de l'architecture Logistique, des modèles et des tables.
- **LOT 2** : Catalogue de matériel (`categories_materiel`, nature Durable/Consommable).
- **LOT 3A** : Identification des équipements durables.
- **LOT 3B** : Photos et GED Socle (`DocumentServiceContract`).

## État existant inspecté
| VALEUR ACTUELLE | USAGE | CLASSIFICATION | DÉCISION |
| :--- | :--- | :--- | :--- |
| `bon` | État initial ou équipement en parfait état | État physique (`BON`) | Conservé et normalisé |
| `a_reparer` | Déclaré lors d'une panne (`declarerPanne`) | Statut opérationnel / maintenance (Transitoire) | Conservé temporairement comme état legacy/transitoire, migré au LOT 4B |
| `hors_service` | Équipement temporairement hors service | État physique (`HORS_SERVICE`) | Conservé et normalisé |
| `mis_au_rebut` | Équipement réformé / mis au rebut | Statut opérationnel / cycle de vie (Transitoire) | Conservé temporairement comme état legacy/transitoire, migré au LOT 4B |

## Modèle d'état physique retenu
Enum `App\Modules\Logistique\Enums\EtatPhysiqueMateriel` :
- `BON ('bon')`
- `MOYEN ('moyen')`
- `MAUVAIS ('mauvais')`
- `HORS_SERVICE ('hors_service')`
- `AReparer ('a_reparer')` (legacy transitoire)
- `MisAuRebut ('mis_au_rebut')` (legacy transitoire)

## Transitions
Les états physiques canoniques (`bon`, `moyen`, `mauvais`, `hors_service`) peuvent être attribués et modifiés de manière contrôlée via le service (`modifierEtatPhysique()`), qui valide l'appartenance à l'Enum et trace les modifications via l'audit.

## Compatibilité legacy
- La colonne physique `equipements.etat` est réutilisée (type `varchar` assoupli par migration additive).
- Les équipements existants et les flux legacy continuent de fonctionner sans modification de données.

## Panne
- `declarerPanne()` positionne l'état à `a_reparer` (état transitoire de maintenance, séparé de l'état physique pur au LOT 4B).

## Maintenance
- `MaintenanceService` remet l'équipement en état `bon` à la fin d'une intervention curative.

## Mise au rebut
- `mettreAuRebut()` positionne l'état à `mis_au_rebut`. Cet état représente un cycle de vie / réforme et non une condition physique ; il est maintenu temporairement dans le champ `etat` pour des raisons de compatibilité et sera converti en statut opérationnel `REFORME` / `MIS_AU_REBUT` lors du LOT 4B.

## Migration
- Migration additive : `2026_10_03_000003_normaliser_etat_physique_equipements.php` (convertit la colonne restrictive `enum` en `varchar(50)` pour accepter `moyen` et `mauvais` sans erreur de contrainte SQLite/MySQL).

## Models
- `App\Modules\Logistique\Models\Equipement` (champ `etat` normalisé, supportant l'Enum et les valeurs string).

## Services / Contracts
- `App\Modules\Logistique\Contracts\EquipementServiceInterface` : ajout de `modifierEtatPhysique(int $equipementId, string $etat)`.
- `App\Modules\Logistique\Services\EquipementService` : implémentation de `modifierEtatPhysique()` avec validation d'Enum et enregistrement d'audit (`AuditServiceContract`).

## Filament
- `App\Modules\Logistique\Filament\Resources\EquipementResource` : formulaire et filtres de table mis à jour avec les options de `EtatPhysiqueMateriel::options()` et des badges colorés.

## Audit
- Utilisation d'`AuditServiceContract` pour tracer chaque modification d'état physique effectuée via le service.

## Fichiers créés
- `app/Modules/Logistique/Enums/EtatPhysiqueMateriel.php`
- `app/Modules/Logistique/database/migrations/2026_10_03_000003_normaliser_etat_physique_equipements.php`
- `tests/Feature/Logistique/EtatPhysiqueEquipementTest.php`
- `docs/refonte/logistique/LOT-04A-ETATS-PHYSIQUES.md`

## Fichiers modifiés
- `app/Modules/Logistique/Models/Equipement.php`
- `app/Modules/Logistique/Contracts/EquipementServiceInterface.php`
- `app/Modules/Logistique/Services/EquipementService.php`
- `app/Modules/Logistique/Filament/Resources/EquipementResource.php`

## Tests
- Commande exacte : `./vendor/bin/sail exec laravel.test ./vendor/bin/pest tests/Feature/Logistique`
- Nombre de tests Logistique : 64 tests
- Nombre d'assertions : 160 assertions
- Résultat : **PASS** (incluant `EtatPhysiqueEquipementTest` : 4 tests / 4 assertions).

## Pint
- PASS (exécuté sur l'ensemble du module Logistique et des tests).

## Deptrac
- Baseline historique : 22 violations (Scolarité).
- Nouvelles violations Logistique : **0**.

## Régressions
- Aucune régression (tous les tests du module Logistique passent avec succès).

## Décisions techniques
1. **Introduction de l'Enum `EtatPhysiqueMateriel`** pour centraliser les états physiques (`bon`, `moyen`, `mauvais`, `hors_service`) tout en conservant les états transitoires `a_reparer` et `mis_au_rebut` pour une rétrocompatibilité parfaite.
2. **Migration de la colonne `etat`** en `varchar(50)` pour permettre l'insertion de nouveaux états physiques sans contrainte rigide d'Enum SQL.

## Dettes restantes
- Migration future (LOT 4B) des états transitoires `a_reparer` et `mis_au_rebut` vers des statuts opérationnels dédiés.

## Risques
- Aucun risque identifié (rétrocompatibilité assurée).

## État final LOT 4A
- Opérationnel, validé, testé et conforme aux directives.

## Préparation LOT 4B
Pour le LOT 4B, l'agent devra introduire :
- Le concept de **Statut Opérationnel** (`EN_STOCK`, `AFFECTE`, `EN_MAINTENANCE`, `LOUE`, `REFORME`) séparé de l'état physique.
- L'historisation des changements de statuts.
- La gestion de la réforme et de la mise au rebut comme statuts opérationnels.
- (Toujours sans introduire de moteur de stock).
