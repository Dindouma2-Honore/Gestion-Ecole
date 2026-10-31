# PC1 — Organisation Pédagogique, Offres & Affectations Enseignants

## 1. Périmètre & Processus Réel
Le processus PC1 couvre le cycle initial de configuration pédagogique de l'établissement :
1. **Matières & Natures** : Création/configuration des matières (`nature` : `matiere`, `discipline`, `activite`) rattachées aux catégories.
2. **Offres Pédagogiques** : Définition des offres par niveau scolaire ou par classe spécifique (`niveau_id`, `classe_id`, `matiere_id`, `coefficient_matiere`, `heures_hebdo`).
3. **Résolution d'Offre Effective** : Priorité classe > niveau (`OffrePedagogiqueService::effective()`).
4. **Affectations Pédagogiques Multi-Cycles** :
   - Mode `classe` (Maternelle/Primaire) : affectation principale (`principal`) ou d'assistance spécialisée (`assistant` avec pivot `affectation_pedagogique_matieres`).
   - Mode `matiere` (Secondaire/Collège/Lycée) : affectation par matière (`matiere`), requérant obligatoirement une offre pédagogique active.
5. **Vérification d'Autorisation Pédagogique** : Service canonique `AffectationPedagogiqueService::estAutorise($enseignantId, $classeId, $matiereId, $anneeId, $date)` vérifiant l'activité de l'enseignant, l'existence d'une offre effective et l'affectation temporelle valide.

## 2. Tests Exécutés
- Suite dédiée de test critique : `tests/Feature/Pedagogie/PC1ProcessusCritiqueTest.php` (5 tests, 31 assertions).
  - `test_pc1_1_matieres_crud_et_nature` : CRUD Matière, contraintes de validation sur `nature`.
  - `test_pc1_2_offres_pedagogiques_niveau_et_classe` : Héritage niveau vs classe, surcharge spécifique, non-régression coefficient.
  - `test_pc1_3_affectations_maternelle_primaire_secondaire_et_est_autorise` : Affectations multi-cycles et vérification stricte de `estAutorise()`.
  - `test_pc1_4_filament_resources_et_methodes` : Accès Filament, présence des formulaires et méthodes de listing contextuelles (`classesDisponibles`, `enseignantsDisponibles`, `matieresDisponibles`).
  - `test_pc1_5_binding_canonique_vs_legacy` : Vérification du binding DI de `AffectationPedagogiqueServiceInterface` vers `AffectationPedagogiqueService` (remplaçant le legacy `AffectationService`).
- Suite globale de non-régression PC1 (40 tests, 246 assertions) :
  - `tests/Feature/Pedagogie/MatiereTypeTest.php`
  - `tests/Feature/Pedagogie/OffresPedagogiquesMultiCycleTest.php`
  - `tests/Feature/Pedagogie/AffectationCyclesTest.php`
  - `tests/Feature/Pedagogie/AffectationsTousCyclesTest.php`
  - `tests/Feature/Pedagogie/OrganisationPedagogiqueTest.php`

## 3. Écarts Constatés
- **Legacy `AffectationService` vs Service Canonique** :
  L'ancienne classe `AffectationService` subsistait dans le dossier `Services/`. Une inspection a confirmé qu'aucun contrôleur ni ressource Filament ne l'appelait directement dans `app/`. L'interface `AffectationPedagogiqueServiceInterface` est correctement bindée à `AffectationPedagogiqueService`.
- **Signature `estAutorise`** :
  L'ordre des paramètres dans `AffectationPedagogiqueServiceInterface::estAutorise(int $enseignantId, int $classeId, int $matiereId, int $anneeId, string $date)` nécessite rigoureusement `matiereId` avant `anneeId`. L'existence d'une offre pédagogique effective est un invariant prérequis pour toute autorisation, y compris pour les titulaires de classe.

## 4. Actions Réalisées
- Finalisation et validation de la suite de test E2E `PC1ProcessusCritiqueTest.php`.
- Validation de l'isolation modulaire : les interfaces du module Pédagogie consomment les données Scolarite/RH au travers des contrats et contextes (`PedagogieContextInterface`).
- Vérification du comportement des resources Filament (`MatiereResource`, `OffrePedagogiqueResource`, `AffectationPedagogiqueResource`).

## 5. Résultat des Tests
- `PC1ProcessusCritiqueTest.php` : 5 tests PASS, 31 assertions.
- Suite complète PC1 : 40 tests PASS, 246 assertions.
- 0 régression.

## 6. Dette & Points de Vigilance
- **Nettoyage résiduel** : `app/Modules/Pedagogie/Services/AffectationService.php` est un reliquat legacy non utilisé qui pourra être déprécié ou archivé lors d'une future passe de maintenance générale hors batch critique.
- **Règles d'offre** : En mode classe (Maternelle/Primaire), s'assurer que les matières/activités sont bien enregistrées en offre au niveau du Niveau d'enseignement pour que `estAutorise` valide les séances.

## 7. Conclusion
Le processus critique PC1 (Organisation -> Offre pédagogique -> Affectation enseignant) est entièrement fonctionnel, étanche et validé par 40 tests automatisés. Le passage à PC2 est autorisé.
