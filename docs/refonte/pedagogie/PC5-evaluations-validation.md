# PC5 — Planification, Soumission & Validation des Évaluations

## 1. Périmètre & Processus Réel
Le processus PC5 structure le cycle de vie d'une évaluation sommative ou formative :
1. **Planification & Enseignant Affecté** :
   - L'évaluation ne peut être planifiée que par un enseignant affecté à la classe et à la matière (`AffectationPedagogique` active).
   - Tout tiers ou enseignant non affecté est strictement refoulé (`InvalidArgumentException`).
   - Les profils administratifs habilités (`Fondateur`, `Directeur`, `Admin`, `Coordinateur`) conservent la capacité d'administration pédagogique.
2. **Période & Année Scolaire Ouverte** :
   - Vérification du verrou d'écriture de l'année scolaire via `AnneeScolaireServiceContract::ecritureAutorisee`.
   - Cohérence stricte de la séquence/trimestre : la période doit obligatoirement appartenir à l'année scolaire sélectionnée.
3. **Barème & Coefficient Cohérents** :
   - Le barème doit être strictement positif (`bareme > 0`).
   - Le coefficient d'évaluation doit être strictement positif (`coefficient > 0`).
4. **Statut Initial & Soumission** :
   - Toute création génère une évaluation au statut `soumis` (en attente de validation).
   - Notification automatique envoyée aux directeurs/fondateurs dans Filament.
5. **Validation par Autorité (Fondateur / Directeur)** :
   - Seuls les utilisateurs pourvus des rôles décisionnels (`Directeur`, `Directrice`, `Fondateur`, `Super Admin`, `Admin`, `Direction`, `Coordinateur Pédagogique`) ou permission dédiée peuvent approuver le sujet.
   - Les utilisateurs non autorisés (enseignants, élèves, personnel non décisionnaire) sont bloqués (`AuthorizationException`).
   - La validation passe le statut à `valide`, enregistre l'identifiant du validateur (`valide_par`), et trace l'opération dans le contrat d'audit (`AuditServiceContract`).
6. **Rejet avec Motif Obligatoire** :
   - Le rejet exige un motif explicite non vide (`motif_rejet`).
   - Un rejet sans motif est rejeté (`InvalidArgumentException`).
   - Le statut passe à `rejete`, l'enseignant auteur est notifié, et l'événement est audité avec motif.
7. **Barrière d'Autorisation de Saisie des Notes (Gatekeeping)** :
   - Tant que l'évaluation est au statut `soumis` ou `rejete`, toute saisie individuelle ou par lot de notes est strictement interdite (`SujetExamenNonValideException`).
   - Dès la validation formelle (`valide`), la saisie des notes devient autorisée.
8. **Immutabilité & Verrouillage** :
   - Dès que des notes sont enregistrées ou que l'évaluation est verrouillée (`verrouillee = true`), les champs structurels (barème, classe, matière) sont verrouillés contre toute altération.
   - `EditEvaluation` interdit formellement la sauvegarde sur une évaluation verrouillée.

## 2. Tests Exécutés
- Suite dédiée : `tests/Feature/Pedagogie/PC5EvaluationValidationTest.php` (8 tests, 26 assertions) :
  - `test_enseignant_affecte_planifie_evaluation_avec_statut_initial_soumis` : validation du statut initial `soumis`, barème, coefficient, année scolaire et période.
  - `test_enseignant_non_affecte_ne_peut_pas_proposer_evaluation` : refus de l'enseignant non affecté.
  - `test_bareme_ou_coefficient_invalide_rejete` : refus des valeurs négatives ou nulles.
  - `test_periode_invalide_pour_annee_scolaire_rejetee` : rejet d'une période n'appartenant pas à l'année scolaire active.
  - `test_utilisateur_non_autorise_ne_peut_pas_valider_evaluation` : exception d'autorisation si tentative de validation par un rôle non directeur/fondateur.
  - `test_fondateur_ou_directeur_valide_evaluation_avec_succes` : validation formelle, statut `valide`, persistance de `valide_par`.
  - `test_rejet_avec_motif_par_autorite_et_rejet_sans_motif_interdit` : rejet avec motif enregistré, échec si motif vide.
  - `test_saisie_note_impossible_si_evaluation_non_validee_et_autorisee_apres_validation` : refus de notation si `soumis`, autorisation immédiate dès passage à `valide`.
- Suite combinée : `EvaluationEtNoteWorkflowTest.php` + `PC5EvaluationValidationTest.php` (12 tests, 43 assertions) PASS.

## 3. Tableau des Erreurs Constatées & Corrigées

| ID | Priorité | Étape | Symptôme | Cause | Correction | Test | Statut |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **PC5-ERR-01** | HIGH | Service / Métier | Champs `annee_scolaire_id`, `periode_id`, `type_evaluation`, `coefficient` non enregistrés lors de la soumission Filament | `CreateEvaluation::handleRecordCreation()` et `EvaluationService::proposerSujet()` n'acceptaient ni ne sauvegardaient ces champs | Ajout des paramètres typés dans `EvaluationServiceInterface`, enrichissement de `proposerSujet()` et transmission depuis `CreateEvaluation` | `test_enseignant_affecte_planifie_evaluation_avec_statut_initial_soumis` | FIXED |
| **PC5-ERR-02** | HIGH | Service / Sécurité | N'importe quel enseignant ou utilisateur pouvait planifier des évaluations pour n'importe quelle classe | Absence de vérification d'affectation pédagogique active dans `proposerSujet()` | Ajout du contrôle d'affectation via `AffectationPedagogique` pour les acteurs non administratifs | `test_enseignant_non_affecte_ne_peut_pas_proposer_evaluation` | FIXED |
| **PC5-ERR-03** | HIGH | Service / Autorisation | Absence de contrôle de rôle dans `validerSujet()` | N'importe quel `userId` pouvait être passé pour forcer la validation d'une évaluation | Vérification de l'autorité décisionnelle (`Directeur`, `Fondateur`, `Admin`, `Coordinateur`) avec levée de `AuthorizationException` | `test_utilisateur_non_autorise_ne_peut_pas_valider_evaluation` | FIXED |
| **PC5-ERR-04** | MEDIUM | UI / Filament | Boutons "Valider le sujet" et "Rejeter le sujet" visibles par tous les profils dans le tableau Filament | Visibilité conditionnée uniquement par `$record->statut === 'soumis'` | Restriction de visibilité aux rôles de direction/fondation | `EvaluationResource::table` | FIXED |
| **PC5-ERR-05** | MEDIUM | UI / Intégrité | Barème et périmètre modifiables même après saisie de notes | Les champs restaient éditables dans le formulaire | Désactivation conditionnelle si `$record->verrouillee || $record->notes()->exists()`, et arrêt de sauvegarde dans `EditEvaluation::beforeSave()` | `EvaluationResource::form` & `EditEvaluation` | FIXED |
| **PC5-ERR-06** | LOW | Test existant | Erreur `Class App\Modules\Socle\Models\Cycle not found` dans `EvaluationEtNoteWorkflowTest` | Mauvais namespace d'import (`Socle` au lieu de `Scolarite`) | Correction des imports vers `App\Modules\Scolarite\Models\{Cycle, Niveau}` | `EvaluationEtNoteWorkflowTest` | FIXED |

## 4. Actions Réalisées
- Durcissement de `EvaluationService` :
  - Vérification des affectations d'enseignants.
  - Validation du barème et du coefficient.
  - Contrôle d'écriture de l'année scolaire et appartenance de la période.
  - Contrôle strict des rôles d'autorité pour la validation et le rejet.
- Complétion du formulaire Filament `EvaluationResource` et de la page `CreateEvaluation` pour enregistrer l'intégralité du contexte pédagogique.
- Verrouillage défensif des champs structurels dès la première note ou en cas de verrouillage global.
- Création de la suite complète de tests de conformité PC5.

## 5. Résultat des Tests
- `PC5EvaluationValidationTest.php` : 8 tests PASS, 26 assertions.
- Suite complète PC5 + Workflow d'évaluation : 12 tests PASS, 43 assertions.
- 0 régression.

## 6. Conclusion
Le processus critique PC5 (Enseignant -> Planification évaluation -> Soumission -> Validation Fondateur/Directeur -> Évaluation autorisée) est entièrement validé et conforme aux règles d'architecture et de gouvernance.
Le passage à PC6 (Évaluation autorisée -> Liste officielle des élèves -> Saisie notes -> Soumission -> Approbation Fondateur -> Gel) est autorisé.
