# PC4 — Séances, Appel, Présences & Frontière Assiduité

## 1. Périmètre & Processus Réel
Le processus PC4 couvre l'exécution de l'appel lors d'une séance de cours et l'observation des présences :
1. **Séance & Enseignant Habilité** : L'appel ne peut être réalisé que par l'enseignant affecté à la séance ou par l'autorité administrative (`Fondateur`, `Directeur`, `Admin`). Tout tiers non affecté est refusé (`DomainException`).
2. **Liste Officielle des Élèves (Scolarité)** : Les élèves ne sont jamais requêtés directement par SQL brut ; ils sont obtenus via le contrat canonique `EleveServiceInterface::getElevesParClasse($classeId)`.
3. **Ergonomie Métier "Présents par Défaut"** :
   - Tous les élèves inscrits sont réputés présents par défaut.
   - L'enseignant ne saisit que les anomalies (`absent`, `retard` avec heure d'arrivée).
   - Les élèves non mentionnés dans les anomalies sont automatiquement enregistrés comme `present` sans risque de suppression d'aucun élève.
4. **Validation de l'Appel & Transition de Séance** :
   - L'appel persiste les statuts dans la table `presences`.
   - La séance transite automatiquement vers `dispensee` (en passant par `commencee` si nécessaire).
   - Les anomalies d'appel non fait (`AnomalieAppel`) associées à la séance sont automatiquement marquées `resolue = true`.
5. **Idempotence & Verrouillage** :
   - Une seconde sauvegarde d'appel avec flag de remplacement (`remplacer = true` ou modal Filament) utilise `updateOrCreate` : aucun enregistrement en double, aucune multiplication d'absences.
   - Une tentative d'appel sur une séance verrouillée (`estAppelVerrouille`) est strictement rejetée.
6. **Frontière Assiduité (Ownership P0.2 / P0.3)** :
   - Pédagogie gère l'**observation locale de présence** lors de la séance.
   - Assiduité gère le **suivi institutionnel** (justifications, anomalies journalières, historique).
   - La dette de lecture SQL directe par `AttendanceEngine` est documentée et conservée pour un lot dédié (DEFERRED) sans régression.

## 2. Tests Exécutés
- Suite dédiée de test critique : `tests/Feature/Pedagogie/PC4AppelPresenceAssiduiteTest.php` (6 tests, 26 assertions) :
  - `test_pc4_1_liste_officielle_et_presents_par_defaut` : Intégration du contrat `EleveServiceInterface`, saisie d'anomalies uniquement, persistance des absents, retards et présents implicites.
  - `test_pc4_2_autorisation_appel_enseignant_affecte_vs_intrus` : Rejet de l'intrus non affecté, succès pour l'enseignant titulaire.
  - `test_pc4_3_idempotence_et_mise_a_jour_sans_doublon` : Premier appel, rejet de doublon brut (`AppelDejaEffectueException`), mise à jour idempotente sans doublon.
  - `test_pc4_4_resolution_automatique_anomalie_appel` : Résolution automatique de l'anomalie d'appel manquant après soumission.
  - `test_pc4_5_appel_verrouille_refuse_toute_modification` : Rejet si l'appel est verrouillé par la vie scolaire ou direction.
  - `test_pc4_6_filament_action_faire_appel_dans_seance_resource` : Test Livewire de l'action de table `faireAppel` sur `SeanceResource`.
- Suite globale de non-régression PC4 (12 tests, 47 assertions) :
  - `tests/Feature/Pedagogie/PC4AppelPresenceAssiduiteTest.php`
  - `tests/Feature/Pedagogie/PresenceTest.php`
  - `tests/Feature/Pedagogie/SeanceEtRemplacementTest.php`

## 3. Tableau des Erreurs Constatées & Corrigées

| ID | Priorité | Étape | Symptôme | Cause | Correction | Test | Statut |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **PC4-ERR-01** | CRITICAL | UI / Filament | Action d'appel absente de l'interface `SeanceResource` | Seules les actions incident, annulation, report et progression existaient | Ajout de l'action `faireAppel` avec formulaire d'anomalies (absents / retards) | `test_pc4_6` | FIXED |
| **PC4-ERR-02** | HIGH | Service / Autorisation | Absence de contrôle d'affectation dans `faireAppel()` | Tout utilisateur connecté pouvait enregistrer l'appel d'une séance quelconque | Ajout de `verifierAutorisationAppel()` via `EnseignantServiceInterface` et rôles direction | `test_pc4_2` | FIXED |
| **PC4-ERR-03** | HIGH | Ergonomie / Modèle | Les élèves non cochés n'avaient pas de présence créée | `faireAppel()` ne bouclait que sur les clés passées en argument | Récupération de la liste officielle via `EleveServiceInterface` et statut `present` par défaut | `test_pc4_1` | FIXED |
| **PC4-ERR-04** | MEDIUM | Service / Idempotence | Exception de transition `dispensee` vers `dispensee` lors de la réévaluation de l'appel | `$this->seanceService->marquerDispensee()` était invoqué sans tester si la séance était déjà dispensée | Ajout de la condition `if ($seance->fresh()->statut !== 'dispensee')` | `test_pc4_3` | FIXED |
| **PC4-ERR-05** | MEDIUM | UI / Filament | Erreur 500 Livewire : Route `filament.admin.resources.progressions.create` not defined | `SeanceResource` appelait `ProgressionResource::getUrl('create')` alors que la resource ne définit que la page `'index'` | Correction du lien vers `ProgressionResource::getUrl('index', ['seance' => $record->id])` | `test_pc4_6` | FIXED |
| **PC4-ERR-06** | LOW | Test existant | `Class App\Modules\Socle\Models\Cycle not found` dans `SeanceEtRemplacementTest` | Mauvais namespace d'import (`Socle` au lieu de `Scolarite`) | Correction des imports `Cycle` et `Niveau` vers `App\Modules\Scolarite\Models` | `SeanceEtRemplacementTest` | FIXED |

## 4. Actions Réalisées
- Implémentation du principe "présents par défaut" dans `PresenceService` grâce à l'injection de `EleveServiceInterface`.
- Sécurisation du service contre les appels par des enseignants non affectés.
- Idempotence complète garantie avec `updateOrCreate`.
- Ajout de l'Action Filament `faireAppel` sur `SeanceResource`.
- Résolution du bug de route sur `ProgressionResource`.

## 5. Résultat des Tests
- `PC4AppelPresenceAssiduiteTest.php` : 6 tests PASS, 26 assertions.
- Suite complète PC4 : 12 tests PASS, 47 assertions.
- 0 régression.

## 6. Dette & Points de Vigilance
- **Dette de lecture SQL Assiduité (`AttendanceEngine`)** : `AttendanceEngine::calculerEleve` effectue une lecture SQL directe de la table `presences`. Conformément à P0.2 et aux consignes, cette dette est documentée (DEFERRED) et sera résolue lors de la séparation physique complète du module Assiduité sans impacter le fonctionnement de Pédagogie.

## 7. Conclusion
Le processus critique PC4 (Séance -> Liste officielle -> Appel -> Absents/Retards -> Validation -> Frontière Assiduité) est validé avec succès. Le passage à PC5 est autorisé.
