# PC3 — Programme Validé, Séance, Progression Réelle & Devoir

## 1. Périmètre & Processus Réel
Le processus PC3 garantit la traçabilité de l'enseignement dispensé et le suivi de la progression :
1. **Consommation Exclusif d'un Programme Validé** :
   - Une séance ne peut renseigner de progression que sur un programme dont le statut est formellement `valide` (`statut: valide` et `analyse_statut: valide` ou null).
   - Tout programme en état `brouillon`, `analyse_en_cours`, `a_valider` ou `rejete` provoque un rejet immédiat (422 / exception métier).
2. **Séance & Déclaration de Progression Réelle** :
   - La séance doit avoir été préalablement dispensée (`statut: dispensee`).
   - L'enseignant sélectionne un élément terminal du programme (leçon ou objectif feuille sans sous-chapitre).
   - L'avancement est consigné (`etat_avancement: commencee|en_cours|terminee`).
   - La séance est marquée `progression_renseignee = true`.
3. **Calcul d'Avancement Exact & Invariant Anti-Double Comptage** :
   - `ProgressionCalculator::pourChapitres()` calcule le ratio des heures réalisées sur le volume prévu total (`round(100 * realise / total, 2)`).
   - **Garantie anti-double comptage** : `Progression::whereIn(...)->pluck('chapitre_id')->unique()` garantit qu'une notion terminée plusieurs fois (ex: consolidation ou révision) n'est comptabilisée qu'une seule fois.
   - Exclusion automatique des chapitres parents dans le calcul pour éviter d'additionner un chapitre et ses sous-leçons.
4. **Devoir Associé à la Progression** :
   - Rattachement transactionnel d'un devoir à la progression (`DevoirPedagogique` rattaché à `progression_id` et `seance_id`).
   - Contrôle strict de cohérence temporelle : la date de remise doit obligatoirement suivre ou égaler la date de la séance (`date_remise >= date_seance`).
   - Unicité de la progression par séance (une seule progression par séance autorisée).
5. **Clarification `Devoir` vs `DevoirPedagogique`** :
   - `DevoirPedagogique` (table `devoirs_pedagogiques`) : modèle canonique lié au flux de cours séance -> progression -> devoir dans l'UI Filament et `SessionProgressService`.
   - `Devoir` (table `devoirs`) : modèle direct lié à la séance pour les devoirs planifiés hors formulaire de progression.

## 2. Tests Exécutés
- Suite dédiée de test critique : `tests/Feature/Pedagogie/PC3SeanceProgressionDevoirTest.php` (4 tests, 20 assertions) :
  - `test_pc3_1_consommation_programme_valide_uniquement` : Rejet 422 si programme en brouillon, succès après validation humaine.
  - `test_pc3_2_seance_progression_reelle_et_calcul_pourcentage_sans_double_comptage` : Mesure exacte à 20%, validation de l'invariant anti-double comptage (reste à 20% malgré doublon de séance), passage à 50% après nouvelle leçon.
  - `test_pc3_3_attachement_devoir_a_la_seance_et_progression` : Rejet si date remise antérieure, création devoir pédagogique en base, verrouillage contre double progression sur la séance.
  - `test_pc3_4_coherence_devoir_vs_devoir_pedagogique` : Contrôle de persistance et relation `seance->devoirs()`.
- Suite globale combinée PC1 + PC2 + PC3 + Progression (43 tests, 160 assertions).

## 3. Écarts Constatés
- **Contrainte de clé étrangère `emplois_du_temps.salle_id`** : Les cours théoriques nécessitent `salle_id` et `creneau_id` pour respecter l'intégrité référentielle.
- **Sélection des éléments de progression** : Seules les feuilles de l'arbre du programme (`parent_id` non référencé comme parent d'un autre élément) peuvent faire l'objet d'une progression, conformément à la règle d'intégrité de `SessionProgressService`.
- **Typage des devoirs** : Alignement sur `TypeDevoirEnum` pour les devoirs de séance (`exercice`, `lecture`, `devoir_surveille`, `projet`, `recherche`).

## 4. Actions Réalisées
- Validation de l'isolation du calcul de progression dans `ProgressionCalculator`.
- Vérification et confirmation des règles anti-doublon et anti-double-comptage.
- Écriture de la suite E2E PC3 (`PC3SeanceProgressionDevoirTest.php`).

## 5. Résultat des Tests
- `PC3SeanceProgressionDevoirTest.php` : 4 tests PASS, 20 assertions.
- Suite combinée PC1 + PC2 + PC3 : 43 tests PASS, 160 assertions.
- 0 régression.

## 6. Dette & Points de Vigilance
- **Dualité `devoirs` / `devoirs_pedagogiques`** : Les deux tables coexistent harmonieusement sans conflit de schéma (l'une liée à la séance, l'autre au pivot progression-séance). Une unification future sous un contrat unique de devoirs pourra être envisagée en phase de rationalisation générale.
- **Contrôle de délai de modification** : La fenêtre de correction d'une progression est bornée à 24 heures par l'enseignant auteur (`SessionProgressService`), ce qui protège l'historique d'audit.

## 7. Conclusion
Le processus critique PC3 (Programme validé -> Séance -> Progression réelle sans double comptage -> Devoir) est entièrement fonctionnel, étanche et validé par l'ensemble de la suite de tests.
