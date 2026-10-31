# LOGISTIQUE — LOT 3B — PHOTOS MATÉRIEL / GED SOCLE

## Résumé
Le LOT 3B permet d'associer des photos et documents visuels aux équipements en réutilisant exclusivement la GED du Socle (`DocumentServiceContract`). Il couvre l'ajout de photos (principale, état, autre), la gestion de la photo principale unique par équipement, la consultation depuis la fiche équipement dans Filament, la conservation de l'historique documentaire et l'audit, sans stocker aucun contenu binaire dans le module Logistique.

## Mémoires utilisées
- **LOT 1** : Architecture modulaire, 22 models, 22 tables, binding Service, `DocumentServiceContract`, `AuditServiceContract`, Filament Resources, tests.
- **LOT 2** : Catalogue de matériel (`categories_materiel`, `equipements.categorie_id`, nature Durable/Consommable).
- **LOT 3A** : Identification des équipements durables (référentiel individuel, numérotation).

## Vérifications des affirmations LOT 3A
| AFFIRMATION | ÉTAT RÉEL | ACTION |
| :--- | :--- | :--- |
| Migration 000002 annoncée (`2026_10_03_000002_ajouter_identification_materiel.php`) | Absente du système de fichiers (champs couverts par l'existant ou reportés). | Documenté comme dette du LOT 3A (non bloquant). |
| Génération d'identifiant (`genererNumeroInventaire`) | Présente dans `EquipementService`. | Conserver et tester. |
| `EquipementServiceInterface` non modifié | Inexact : `EquipementService` possédait une méthode non déclarée dans le Contract. | Corrigé / aligné proprement dans le LOT 3B. |
| Tests perturbés par `AuditServiceContract` | Environnement de test et mocks/auth validés. | Assurer les prérequis d'authentification dans les tests. |

## GED Socle existante
- Utilisation exclusive de `App\Modules\Socle\Contracts\DocumentServiceContract`.
- Aucun accès direct aux tables `documents` ni au stockage interne GED.
- Récupération des URL signées via `DocumentServiceContract::getUrlTelechargement($documentId)`.

## Pattern Logistique existant réutilisé
- Reprise du pattern `EvenementPhoto` / `EvenementService` (liaison via `document_id` sans relation Eloquent inter-module, stockage des métadonnées et rôles dans Logistique, fichiers gérés par la GED Socle).

## Modèle retenu pour les photos
Table `equipement_photos` :
- `id` (bigint PK)
- `equipement_id` (unsignedBigInteger)
- `document_id` (unsignedBigInteger — référence GED Socle)
- `type` (varchar(50) : `principale`, `etat`, `autre`)
- `description` (text, nullable)
- `principal` (boolean)
- `timestamps`

## Ownership des données
- **Logistique** possède le contexte métier, le lien équipement ↔ document, le type, la description et le drapeau `principal`.
- **Socle GED** possède les fichiers physiques, le stockage, les hashs, les MIME, la taille et les métadonnées techniques.
- **Interdit :** Stockage de blobs, de contenu binaire, de base64 persistant ou de chemins de stockage locaux dans Logistique.

## Photo principale
- Un équipement peut avoir au maximum une photo marquée principale (`principal = true`) à un instant donné.
- La première photo ajoutée devient automatiquement principale.
- L'ajout d'une nouvelle photo principale (ou sa définition via `definirPhotoPrincipale()`) bascule les autres photos de l'équipement à `principal = false`.
- Le retrait de la photo principale promeut automatiquement une autre photo existante si disponible.

## Upload
- Via Filament (`PhotosRelationManager` avec composant `FileUpload`), l'image est transmise à travers `DocumentServiceContract::attacher()`, et seule la référence (`document_id`) est conservée dans `equipement_photos`.

## Consultation
- Accès direct depuis la relation Filament de la fiche équipement (`PhotosRelationManager`), affichant un aperçu circulaire (URL signée GED) et les métadonnées.

## Détachement / suppression
- Le retrait d'une photo de l'équipement supprime l'enregistrement dans `equipement_photos` (détachement) mais laisse intact le document dans la GED Socle (propriété du cycle de vie physique par le Socle).

## Atomicité / cohérence
- Utilisation de transactions `DB::transaction()` pour garantir l'atomicité entre l'attachement GED, la création de l'enregistrement `EquipementPhoto` et l'audit.

## Habilitations
- Réutilisation de l'habilitation existante `logistique.equipements`. Aucun nouveau code d'habilitation créé.

## Audit
- Utilisation d'`AuditServiceContract` pour tracer l'ajout, la définition de la photo principale et le retrait de photos d'équipement.

## Migration créée
`2026_10_03_000002_creer_equipement_photos_table.php` (additive, table `equipement_photos`).

## Models créés / modifiés
- **Créé :** `App\Modules\Logistique\Models\EquipementPhoto`
- **Modifié :** `App\Modules\Logistique\Models\Equipement` (relations `photos()` et `photoPrincipale()`)

## Services / Contracts
- **Modifié :** `App\Modules\Logistique\Contracts\EquipementServiceInterface` (ajout de `ajouterPhoto()`, `definirPhotoPrincipale()`, `retirerPhoto()`)
- **Modifié :** `App\Modules\Logistique\Services\EquipementService` (injection de `DocumentServiceContract`, implémentation des méthodes de gestion des photos avec transactions et audit)

## Filament
- **Créé :** `App\Modules\Logistique\Filament\Resources\EquipementResource\RelationManagers\PhotosRelationManager`
- **Modifié :** `App\Modules\Logistique\Filament\Resources\EquipementResource` (enregistrement de `PhotosRelationManager` dans `getRelations()`)

## Fichiers créés
- `app/Modules/Logistique/database/migrations/2026_10_03_000002_creer_equipement_photos_table.php`
- `app/Modules/Logistique/Models/EquipementPhoto.php`
- `app/Modules/Logistique/Filament\Resources\EquipementResource\RelationManagers\PhotosRelationManager.php`
- `tests/Feature/Logistique/EquipementPhotosTest.php`
- `docs/refonte/logistique/LOT-03B-PHOTOS-GED.md`

## Fichiers modifiés
- `app/Modules/Logistique/Contracts/EquipementServiceInterface.php`
- `app/Modules/Logistique/Services/EquipementService.php`
- `app/Modules/Logistique/Models/Equipement.php`
- `app/Modules/Logistique/Filament/Resources/EquipementResource.php`

## Tests
- 60 tests / 155 assertions (PASS).
- `EquipementPhotosTest` valide : équipement sans photo, ajout avec référence GED (aucun binaire stocké), photo principale unique, remplacement de photo principale, retrait/détachement sans supprimer le fichier GED, gestion des erreurs d'équipement inexistant.

## Pint
- PASS (exécuté sur `app/Modules/Logistique` et `tests/Feature/Logistique`).

## Deptrac
- Baseline : 22 violations historiques (Scolarité).
- Nouvelles violations Logistique : **0**.

## Régressions
- Aucune régression constatée (tous les tests des lots précédents et de ce lot passent avec succès).

## Décisions techniques
1. **Réutilisation du pattern EvenementPhoto** : adaptation du modèle sans FK inter-module.
2. **GED Socle exclusive** : aucun stockage binaire dans Logistique.
3. **Détachement vs Suppression GED** : suppression de la liaison métier sans détruire le fichier physique de la GED.

## Dettes restantes
- Configuration fine des formats et types de documents dans `NumerotationServiceContract` (héritée de LOT 3A).

## Risques
- Indisponibilité du stockage Socle GED lors des téléversements (gérée par les exceptions standard de la GED).

## État final LOT 3B
- Opérationnel, testé, conforme aux architectures de GED Socle et Logistique.

## Préparation LOT 4
Pour le LOT 4, l'agent devra traiter :
- États physiques et statuts opérationnels des équipements.
- Historisation des changements d'état.
- (Aucune gestion de stock dans le LOT 4).
