# PC6 — Saisie des Notes, Liste Officielle, Soumission, Approbation Fondateur & Gel

## 1. Périmètre & Processus Réel
Le processus PC6 constitue la chaîne d'évaluation finale et d'officialisation des notes :
1. **Évaluation Autorisée Uniquement** :
   - Seule une évaluation dont le sujet a été formellement validé (`statut === 'valide'`) peut recevoir des notes (`enregistrer`, `enregistrerLot`, `enregistrerAbsence`).
   - Toute tentative sur une évaluation en attente (`soumis`) ou rejetée (`rejete`) est refoulée immédiatement (`SujetExamenNonValideException`).
2. **Liste Officielle des Élèves (Scolarité) & Aucun Élève Fantôme** :
   - Les élèves admissibles sont rigoureusement validés via le contrat `EleveServiceInterface::getElevesParClasse($classeId)`.
   - Tout élève n'appartenant pas à la classe officielle de l'évaluation (élève fantôme ou d'une autre classe) est rejeté avec interdiction de persistance (`InvalidArgumentException`).
3. **Respect du Barème & Statuts de Présence** :
   - Pour les élèves présents, la note est strictement bornée : `0.0 <= valeur <= bareme` (`NoteInvalideException` en cas de dépassement).
   - Les absences (`absent_justifie`, `absent_injustifie`, `dispense`) sont supportées avec traçabilité complète.
4. **Idempotence & Prévention des Doublons** :
   - Grâce à la contrainte d'unicité `['evaluation_id', 'eleve_id']` et la logique `updateOrCreate` / `where()->first()`, la re-saisie d'un élève met à jour l'enregistrement existant sans création de doublon.
5. **Soumission avec Contrôle de Complétude ("Pas d'Oubli")** :
   - L'enseignant finalise la saisie via `NoteServiceInterface::soumettreNotes()`.
   - Le service vérifie que l'intégralité des élèves inscrits au registre officiel de la classe dispose d'une note ou d'un statut d'absence/dispense.
   - En cas d'omission d'un élève de la classe, la soumission est bloquée (`DomainException : Saisie incomplète`).
   - À la soumission, les notes passent au statut `soumis`, avec horodatage (`soumis_le`) et identifiant de l'auteur (`soumis_par`).
6. **Approbation Fondateur & Gel Définitif** :
   - Seul le Fondateur (ou Super Admin) possède l'autorité pour approuver les notes (`approuverNotes`). Tout autre rôle (ex: enseignant) est rejeté (`AuthorizationException`).
   - L'approbation fige les notes : `statut => 'valide'`, `valide_par => $fondateurId`, `valide_le => now()`, `verrouillee => true`.
   - L'évaluation est simultanément verrouillée (`Evaluation::verrouillee = true`).
7. **Inviolabilité du Gel & Déblocage Exceptionnel Tracé** :
   - Une fois l'évaluation et les notes gelées, toute modification directe via le service ou l'interface Filament est bloquée (`NoteModificationVerrouilleeException`).
   - La seule porte de révision est le déblocage exceptionnel individuel par le Fondateur (`debloquer($noteId, $motif)`), qui autorise une unique correction tracée dans le journal d'audit avant reverrouillage automatique.

## 2. Tests Exécutés
- Suite dédiée : `tests/Feature/Pedagogie/PC6NotesApprobationGelTest.php` (8 tests, 33 assertions) :
  - `test_saisie_autorisee_uniquement_sur_evaluation_validee` : rejet de la saisie sur évaluation non validée (`SujetExamenNonValideException`).
  - `test_eleve_fantome_ou_hors_classe_est_strictement_rejete` : rejet d'un élève inscrit dans une autre classe.
  - `test_bareme_strictement_respecte` : rejet des notes négatives et supérieures au barème.
  - `test_idempotence_et_pas_de_doublon_de_saisie` : mise à jour sur place sans doublon.
  - `test_soumission_exige_completude_et_bloque_les_oublis` : blocage si élève oublié, validation si tous les élèves officiels sont évalués.
  - `test_approbation_par_fondateur_et_rejet_pour_non_autorise` : refus pour l'enseignant, approbation réussie par le Fondateur avec horodatage et audit.
  - `test_gel_interdit_toute_modification_directe_et_autorise_deblocage_par_fondateur` : verrouillage complet interdisant toute modification directe.
  - `test_deblocage_exceptionnel_par_fondateur_avec_motif_autorise_correction_unique` : autorisation de correction exceptionnelle tracée par le Fondateur, consommation du déblocage, et reverrouillage immédiat.
- Suite combinée globale PC4 + PC5 + PC6 (32 tests, 123 assertions) PASS.

## 3. Tableau des Erreurs Constatées & Corrigées

| ID | Priorité | Étape | Symptôme | Cause | Correction | Test | Statut |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **PC6-ERR-01** | HIGH | Service / Périmètre | N'importe quel `eleve_id` (y compris d'une autre classe ou fantôme) pouvait recevoir une note | `NoteService::enregistrer()` et `enregistrerLot()` ne validaient pas l'appartenance de l'élève à `$evaluation->classe_id` | Injection de `EleveServiceInterface` et contrôle strict avec levée de `InvalidArgumentException` | `test_eleve_fantome_ou_hors_classe_est_strictement_rejete` | FIXED |
| **PC6-ERR-02** | HIGH | Service / Complétude | Risque d'oubli d'élèves lors de la transmission des notes | Absence de méthode de soumission globale et de contrôle de complétude | Implémentation de `NoteServiceInterface::soumettreNotes()` comparant les inscrits officiels et les élèves notés | `test_soumission_exige_completude_et_bloque_les_oublis` | FIXED |
| **PC6-ERR-03** | HIGH | Service / Autorisation | Absence de validation institutionnelle et de gel des notes par le Fondateur | Pas de méthode formelle d'approbation et de verrouillage simultané des notes et de l'évaluation | Implémentation de `approuverNotes()` réservée au rôle `Fondateur` avec transition à `valide`, horodatage et passage de `verrouillee = true` | `test_approbation_par_fondateur_et_rejet_pour_non_autorise` | FIXED |
| **PC6-ERR-04** | MEDIUM | Service / Déblocage | Exception "Cette note est encore modifiable et ne nécessite pas de déblocage" lors du déblocage d'une note gelée par le Fondateur | `debloquer()` vérifiait uniquement le délai de 7 jours sans prendre en compte le statut `verrouillee` de la note ou de l'évaluation | Mise à jour de la condition de déblocage prenant en compte le gel institutionnel | `test_deblocage_exceptionnel_par_fondateur_avec_motif_autorise_correction_unique` | FIXED |
| **PC6-ERR-05** | MEDIUM | Service / Exceptionnel | Le déblocage exceptionnel d'une note était bloqué par le verrou d'évaluation global | `enregistrer()` rejetait inconditionnellement si `$evaluation->verrouillee` sans vérifier `$note->debloque_le` | Autorisation conditionnelle de modification si un déblocage exceptionnel actif non consommé existe | `test_deblocage_exceptionnel_par_fondateur_avec_motif_autorise_correction_unique` | FIXED |
| **PC6-ERR-06** | MEDIUM | UI / Filament | Pas d'actions pour soumettre ou approuver/geler les notes dans `EvaluationResource` | Seules les actions de validation de sujet étaient présentes | Ajout des actions `soumettreNotes` et `approuverNotes` avec fenêtres modales de confirmation et notifications | `EvaluationResource::table` | FIXED |

## 4. Actions Réalisées
- Complétion du contrat `NoteServiceInterface` avec `soumettreNotes()` et `approuverNotes()`.
- Implémentation dans `NoteService` des contrôles :
  - Liste officielle des élèves via `EleveServiceInterface`.
  - Rejet systématique des élèves fantômes.
  - Détection des omissions avant soumission.
  - Restriction de l'approbation au rôle Fondateur.
  - Verrouillage transactionnel et gel de l'évaluation.
  - Gestion rigoureuse du cycle de déblocage exceptionnel tracé.
- Intégration des actions Filament `soumettreNotes` et `approuverNotes` dans `EvaluationResource`.
- Création de la suite de tests `PC6NotesApprobationGelTest.php`.

## 5. Résultat des Tests
- `PC6NotesApprobationGelTest.php` : 8 tests PASS, 33 assertions.
- Suite complète PC4 + PC5 + PC6 : 32 tests PASS, 123 assertions.
- 0 régression.

## 6. Conclusion
Le processus critique PC6 (Évaluation autorisée -> Liste officielle -> Saisie notes -> Soumission -> Approbation Fondateur -> Gel) est entièrement opérationnel, étanche et audité.
Le Batch 02 (PC4 + PC5 + PC6) est prêt pour la synthèse finale et la vérification qualité.
