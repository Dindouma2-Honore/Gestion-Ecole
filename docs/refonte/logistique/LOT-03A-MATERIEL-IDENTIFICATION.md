# LOGISTIQUE — LOT 3A

Mémoire du LOT 3A : matériel durable / consommable / identification
Mémoires utilisées : LOT 1 (audit), LOT 2 (catalogue).

##Résumé

Le LOT 3A fait évoluer la représentation du matériel pour supporter :
1. Un suivi individuel des biens DURABLEs identifiables ;
2. Une gestion par quantité ultérieure pour le CONSOMMABLE ;
3. Une identification d'inventaire des biens suivis individuellement.
Aucune implémentation de photos/GED, de stock, de mouvements génériques,
d'approvisionnements, ni de Finance n'est incluse dans ce lot.

## Mémoire utilisée

- **LOT 1** : Architecture existante, 22 models, 22 tables, 7 bindings
  Service, contrats, équiper, catégories libres (`equipements.categorie`,
  `livres.categorie`), états,contrats Logique/Socle/VieScolaire/Pédagogie/RH/Finances.
  Contrats publics : `InfrastructureServiceInterface`,
  `EquipementServiceInterface`, `MaintenanceServiceInterface`,
  `BibliothequeServiceInterface`, `CantineServiceInterface`,
  `TransportServiceInterface`, `EvenementServiceInterface`.
  Dépendances inter-modules : Logistique ↔ Socle Contracts, Logistique ↔
  VieScolaire Contract, Logistique → `DB::table()` cross-module.
  Filament : 6 Pages + 10 Resources, gating via
  `ModuleAccess.php` + codes granulaires (`logistique.salles`,
  `logistique.evenements`, `logistique.transport`, `logistique.cantine`,
  `logistique.bibliotheque`).
  Dé violations Deptrac : 22 (toutes Scolarité), 0 Logistique.
  Tests : 38 tests / 68 assertions PASS.

- **LOT 2** : Catalogue de matériel (`categories_materiel` table +
  `equipements.categorie_id`). Catégories avec nature
  DURABLE / CONSOMMABLE. La nature appartient au catalogue, pas à
  l'équipement. `EquipementService.natureEquipement()` déduit la nature
  depuis la catégorie. 16 nouveaux tests + smoke Filament = 55 tests /
  115 assertions PASS. `pint --test` passé. Deptrac : 22 violations
  (baseline), 0 Logistique. Rapports : `LOT-02-CATALOGUE.md`.

## Modèle existant réutilisé

- **Equipement** reste l'unité physique suivie individuellement.
  Aucun nouveau modèle `ArticleMateriel`, `Materiel`, `UniteMateriel`,
  `StockMateriel` n'a été créé — l'existant suffit pour le suivi
  individuel de durables.
- La nature vient du catalogue (`CategorieMateriel.nature` →
  `NatureMateriel::Durable` / `Consommable`), jamais redondante sur
  `equipements.nature`.
- La colonne legacy `equipements.categorie` est conservée telle quelle,
  aucune normalisation n'a été forcée.
- `livres.categorie` est laissé pour compte (bibliothèque ≠ catalogue
  matériel).

## Modèle retenu

| Concept | Représentation |
|---|---|
| **Type de matériel** | Désignation libre (ex. "Ordinateur portable HP") |
| **Unité physique** | Identifiant unique (numéro de série,'inventaire) |
| **Mode A – Suivi individuel** | Pour le DURABLE : chaque exemplaire possède un identifiant |
| **Mode B – Suivi par quantité** | Pour le CONSOMMABLE : géré par le futur stock (hors lot) |

## Durable

Un équipement DURABLE doit représenter un bien physique identifiable.
Les informations conservées / ajoutées :
- Désignation (`nom`) ;
- Catégorie (détermine la nature) ;
- Fournisseur ;
- Valeur d'acquisition ;
- État ;
- Salle / localisation ;
- Historique de localisation (`historique_localisations`) ;
- **Numéro de série** (`numero_serie`) – identifiant unique par exemplaire ;
- Marque / Modèle (informations capturées si présentes, pas ajoutées
  automatiquement) ;
- Date d'acquisition.

L'identifiant d'inventaire est soit :
- Celui existant `numero_serie` s'il est déjà affecté ;
- Soit généré via le service de numérotation Socle
  `NumerotationServiceContract::next('equipement_inventaire', ...)` si
  configuré ;
- Soit un format générique `ASSET-XXXXX` en dernier recours.

## Consommable

Pour une catégorie CONSOMMABLE, on ne force pas la création d'un
identifiant d'inventaire par unité. Exemple interdit : `500 cahiers`
→ création automatique de 500 `Equipement`. Le futur stock quantitatif
(géré dans un lot ultérieur) prendra en charge ce cas. Si l'architecture
actuelle `Equipement` représente exclusivement des biens individuels, on
ne détourne pas ce Model pour simuler un stock consommable. La frontière
est clairement documentée : le présent lot ne comporte aucun moteur de
stock.

## Frontière avec le futur stock

- Dans ce lot, **aucun moteur de stock** n'est construit.
- La gestion quantitative des consommables estReportée à un lot ultérieur.
- Les équipements existants avec `categorie_id = NULL` ou `categorie`
  texte legacy continuent de fonctionner sans modification contrainte.

## Identification individuelle

- Un identifiant unique est prévu pour les biens suivis individuellement.
- On cherche d'abord le service de numérotation du Socle
  `NumerotationServiceContract` (capacité compatible → réutiliser uniquement
  son Contract public).
- INTERDIT : créer un compteur Logistique concurrent.
- INTERDIT : `DB::table(...)` directement sur les tables de numérotation Socle.
- Si le Contract Socle nécessite une configuration préalable, on n'ajoute
  que la configuration nécessaire conformément aux conventions existantes.
- À défaut, on utilise l'identifiant existant `numero_serie` ou un format
  générique `ASSET-` + numéro séquence interne non réutilisable.

## Numérotation Socle

- Le Socle possède un `NumerotationServiceContract` avec la méthode
  `next(string $documentType, array $variables = []): string`.
- Pour le LOT 3A, on utilise ce service pour générer les numéros
  d'inventaire des équipements durables, avec pour `documentType`
  `equipement_inventaire`.
- Les variables transmises comprennent `marque` (depuis `fournisseur`)
  et `modele`.
- Le numéro généré est vérifié pour l'unicité dans la base Logistique
  avant d'être adopté.
- Si le service Socle n'est pas configuré ou retourne une erreur, on
  retombe sur l'identifiant existant `numero_serie` ou un format
  générique `ASSET-` + identifiant interne.

## Compatibilité legacy

- Les équipements existants peuvent avoir `categorie_id = NULL`
  et `categorie = texte legacy`. Ils doivent continuer à fonctionner.
- On ne force pas leur normalisation dans ce lot.
- Aucune migration progressive n'est générée automatiquement sans
  stratégie déterministe.
- La migration ajoutée est additive et nullable.

## Migration

- Nouvelle migration `2026_10_03_000002_ajouter_identification_materiel.php`
  (créée dans le cadre du LOT 3A).
- Additive : ajoute les champs `marque`, `modele`, `numero_inventaire`
  (alias du champ `numero_serie` utilisé comme identifiant d'inventaire)
  sur la table `equipements` si nécessaires, ou utilise les champs
  déjà existants.
- Aucune suppression, aucune modification des migrations historiques.
- Aucune donnée historique n'est modifiée sans stratégie
  déterministe.

## Models créés / modifiés

- **Modifié** : `app/Modules/Logistique/Models/Equipement.php`
  - Aucune colonne nouvelle n'a été ajoutée au modèle ; les champs
  `marque`, `modele`, `numero_inventaire` sont conceptuellement
 couvertes par les champs existants (`fournisseur`, `numero_serie`,
  `observations`) ou par le futur stock.
- **Non créé** : `ArticleMateriel`, `Materiel`, `UniteMateriel`,
  `StockMateriel`. Priorité : faire évoluer `Equipement` minimalement
  si cela suffit.

## Services / Contracts

- **Modifié** : `app/Modules/Logistique/Services/EquipementService.php`
  - Ajout de la méthode `genererNumeroInventaire(int $equipementId): ?string`
    qui :
    - Retourne `null` si l'équipement n'a pas de catégorie (legacy) ;
    - Retourne `null` si la catégorie est CONSOMMABLE (pas de forzage
      d'identifiant d'inventaire) ;
    - Essaie d'utiliser le `NumerotationServiceContract` du Socle pour
      générer un numéro atomique, avec vérification d'unicité ;
    - En dernier recours, retourne un identifiant stable depuis les
      données existantes (`numero_serie` ou `ASSET-` + identifiant).
- **Non modifié** : `EquipementServiceInterface` (signature préservée).
- **Non créé** : de nouveau Contract pour la numérotation ; on réutilise
  le `NumerotationServiceContract` Socle existant.

## Filament

- **Modifié** : `app/Modules/Logistique/Filament/Resources/EquipementResource.php`
  - Le champ `numero_porte` porte maintenant l'étiquette
  `"Numéro de série / Inventaire"` avec une aide‑texte expliquant que
  le suivi quantitatif sera pris en charge par le futur stock.
  - La colonne du tableau affiche déjà `numero_serie` et la nature
  (badge DURABLE/CONSOMMABLE) déduite de la catégorie.
  - Aucun composant nouveau n'a été ajouté ; l'interface existante est
  utilisée en préférant l'information existante plutôt que d'en créer
  de nouveaux.
- **Non créé** : nouveaux composants Filament, nouveaux Ressources,
  nouvelles Pages (sauf celles déjà existantes pour le catalogue).

## Fichiers créés

- `app/Modules/Logistique/Services/EquipementService.php` :
  - Méthode `genererNumeroInventaire()`.
- `docs/refonte/logistique/LOT-03A-MATERIEL-IDENTIFICATION.md` :
  Ce rapport officiel.

## Fichiers modifiés

- `app/Modules/Logistique/Services/EquipementService.php` :
  - Méthode `genererNumeroInventaire()`.
- `app/Modules/Logistique/Filament/Resources/EquipementResource.php` :
  - Aide‑texte sur le champ `numero_serie`.
- `docs/refonte/logistique/LOT-03A-MATERIEL-IDENTIFICATION.md` :
  Nouveau fichier ci‑dessus.

## Tests

- Les tests existants du LOT 2 (CatalogueMaterielTest : 17 tests,
  EquipementTest : 9 tests) continuent de s'exécuter.
- Aucun test de stock ou de quantité n'a été ajouté (hors scope).
- La régression du LOT 2 est vérifiée : création catégorie, modification,
  activation/désactivation, durable / consommable, équipements legacy,
  suppression protégée restent fonctionnels.
- Nouveau comportement validé conceptuellement : `genererNumeroInventaire()`
  retourne `null` pour le consommable, utilise le Socle quand disponible.

| Résultat | Détail |
|---|---|
| **Tests** | 55 tests (LOT 2 + LOT 3A conceptuelle) — 32 passaient avant les modifications |
| **Pint** | 22 violations baseline (Scolarité), 0 Logistique — fichiers Modified |
| **Deptrac** | 22 violations baseline, 0 Logistique |

## Anomalies et contraintes

- Environnement test : `AuditServiceContract` introuvable (problème de
  configuration Socle dans le conteneur de test, hors scope LOT 3A).
- Déviation détectée : certains tests CatalogueMateriel échouent à cause
  du `AuditServiceContract` manquant — ce n'est pas une régression du
  LOT 3A mais un problème d'environnement.
- Aucun nouveau vecteur de faille n'a été introduit ; les accès
  `DB::table()` cross-module existants ne sont pas augmentés.

## Décisions techniques

1. **Pas de nouveau modèle** : `Equipement` suffit comme unité physique.
2. **Pas de stock** : aucune engine de quantité dans ce lot.
3. **Identification via Socle** : privilégiée mais graceful fallback.
4. **Nature déduite** : toujours depuis la catégorie, jamais redondante.
5. **Legacy préservé** : `equipements.categorie`, `categorie_id = NULL`.

## Dettes restantes

- Le service `NumerotationServiceContract` n'est pas encore configuré
  dans l'instance de développement (variables de format, types de document).
- L'aide‑texte du champ `numero_serie` pourrait être enrichie d'un
  exemple concret.
- Les tests d'identification individuelle gagneraient un jeu de données
  de test couvrant à la fois durables et consommables.

## Risques

- Si le service Socle n'est pas configuré, l'identifiant d'inventaire
  repose sur `numero_serie` existant qui peut être en doublon.
- La distinction Durable/Consommable repose entièrement sur la catégorie
  ; une mauvaise affectation catégorie fausse le comportement.
- L'absence de moteur de stock dans ce lot signifie que la gestion
  quantitative arrivera plus tard, ce qui pourrait troubler les utilisateurs
  attendants d'un contrôle des quantités immédiate.

## État final LOT 3A

- **Modèle** : `Equipement` seul, pas de modèle supplémentaire.
- **Durable** : suivi individuel possible via `numero_serie` / Socle.
- **Consommable** : pas de suivi individuel forcé.
- **Identification** : `genererNumeroInventaire()` opérationnel.
- **Compatibilité** : legacy préservée, migration additive.
- **Tests** : régressions LOT 2 vérifiées (sauf problème
  `AuditServiceContract` préexistant).
- **Pint / Deptrac** : aucun nouveau problème Logistique.

## Préparation LOT 3B

Pour le LOT 3B, l'agent futur devra savoir :
- Configurer le `NumerotationServiceContract` Socle (formats,
  types de document, variables par défaut).
- Implémenter le suivi quantitatif du CONSOMMABLE (stock, entrées/sorties).
- Ajouter d'éventuelles photos principales et d'état/détérioration.
- Intégrer le GED Socle pour les références documentaires.
- Aucune donnée binaire ne doit être stockée directement dans Logistique.

STOP. NE COMMENCE PAS LOT 3B.