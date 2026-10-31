# PC2 — Programmes Pédagogiques : Import, Analyse IA & Validation Humaine

## 1. Périmètre & Processus Réel
Le processus PC2 couvre le cycle de vie du programme d'enseignement à partir de documents physiques :
1. **Affectation & Droits d'import** : Vérification stricte que l'enseignant est affecté à l'offre pédagogique ciblée (`classe_id`, `matiere_id`, `annee_scolaire_id`).
2. **Import de Document (PDF / Word DOCX)** :
   - Validation stricte des extensions et des signatures binaires / archives.
   - Rattachement sécurisé dans la GED privée (`DocumentServiceContract::attacher(..., 'programme_source', 'restreint')`).
   - Initialisation du programme en statut `brouillon` (`analyse_statut: analyse_en_attente`).
3. **Analyse IA Asynchrone (Job)** :
   - Prise en charge par le job de file d'attente (`AnalyseProgramme` / `ProcessProgrammeDocumentJob`).
   - Transition d'état : `analyse_en_cours` -> extraction -> proposition structurée (`a_valider`).
   - **Invariant Métier Vital** : L'analyse IA ne valide JAMAIS le programme de manière autonome (`statut` reste `brouillon`, `valide_le` reste `null`).
4. **Correction Humaine** :
   - L'enseignant ou le coordinateur examine la proposition générée (`ProgramStructureService::proposition()`).
   - Modification manuelle des chapitres, leçons, volumes horaires ou objectifs sans changer le statut vers validé.
5. **Validation Humaine Explicite & Publication** :
   - Acte explicite de validation (`ProgramStructureService::enregistrer($p, $elements, true)`).
   - Enregistrement du validateur (`valide_par`) et de la date (`valide_le`).
   - Publication via `ProgrammeService::publier()`. Toute tentative de publication d'un programme non validé est rejetée (422).
6. **Sécurisation de la route d'analyse** : Résolution de la vulnérabilité fail-open sur `AnalyserProgrammeController`.

## 2. Tests Exécutés
- Suite dédiée de test critique : `tests/Feature/Pedagogie/PC2ProgrammeAnalyseValidationTest.php` (5 tests, 36 assertions) :
  - `test_pc2_1_import_programme_affectation_et_ged_privee` : Contrôle d'affectation, upload document DOCX, intégration GED privée restreinte, dispatch du Job, rejet 403 pour tiers non affecté.
  - `test_pc2_2_analyse_ia_asynchrone_et_proposition_structuration_sans_auto_validation` : Exécution du parser/extracteur, création des chapitres/leçons, vérification de l'invariant `statut === brouillon` et `valide_le === null`.
  - `test_pc2_3_correction_humaine_de_la_structure` : Ajustement manuel des intitulés et volumes horaires, persistance sans validation anticipée.
  - `test_pc2_4_validation_humaine_explicite_et_publication` : Rejet de publication prématurée (422), validation humaine horodatée, publication réussie.
  - `test_pc2_5_securite_analyser_controller_non_fail_open` : 401 si non authentifié, 403 si utilisateur lambda non habilité, 200 pour le Fondateur/Direction.
- Suite complète de régression PC2 (40 tests, 136 assertions) :
  - `tests/Feature/Pedagogie/PC2ProgrammeAnalyseValidationTest.php`
  - `tests/Feature/Pedagogie/AiProgrammeAnalysisTest.php`
  - `tests/Feature/Pedagogie/ProgrammesProgressionWorkflowTest.php`
  - `tests/Unit/Pedagogie/ProgrammeDocumentParserTest.php`

## 3. Écarts Constatés
- **Vulnérabilité fail-open (`AnalyserProgrammeController`)** :
  Le contrôleur possédait un bloc conditionnel vide autorisant implicitement tout utilisateur connecté à déclencher l'analyse IA. De plus, il comparait `programme->enseignant_id` avec `user->id` sans passer par la résolution contractuelle de l'Enseignant RH.
- **Classes et imports manquants dans `ProgrammeService.php`** :
  Absence d'import pour `DB`, `Storage`, `StatutProgrammeEnum`, `AffectationPedagogique`, `AnalyzeProgrammeJob` et `ProcessProgrammeDocumentJob`.
- **Import manquant dans `ProgressionService.php`** :
  `use InvalidArgumentException;` manquait, provoquant une résolution erronée en `App\Modules\Pedagogie\Services\InvalidArgumentException`.

## 4. Actions Réalisées
- **Correction de `AnalyserProgrammeController`** : Résolution du propriétaire RH via `EnseignantServiceInterface::getEnseignantIdPourUtilisateur()`, contrôle des rôles de direction (`Fondateur`, `Directeur`, `Admin`, `Super Admin`) et de la permission `pedagogie.progression.superviser`. Renvoi strict de `403 Forbidden` pour tout utilisateur non habilité.
- **Correction des imports** : Ajout de tous les alias manquants dans `ProgrammeService.php` et `ProgressionService.php`.
- **Création du test E2E PC2** : `PC2ProgrammeAnalyseValidationTest.php` garantissant les 5 étapes du processus critique.

## 5. Résultat des Tests
- `PC2ProgrammeAnalyseValidationTest.php` : 5 tests PASS, 36 assertions.
- Suite complète de non-régression PC2 : 40 tests PASS, 136 assertions.
- 0 régression.

## 6. Dette & Points de Vigilance
- **Cohérence des jobs d'analyse** : Le système propose à la fois `AnalyseProgramme` (utilisé par le workflow de versioning documentaire) et `AnalyzeProgrammeJob` (utilisé par le endpoint API HTTP). Les deux jobs respectent scrupuleusement l'invariant de non-auto-validation (`a_valider`).
- **Gestion des gros fichiers** : La limite de 5 Mo sur les documents de programme et la vérification des signatures MIME garantissent l'intégrité de la GED privée.

## 7. Conclusion
Le processus critique PC2 (Affectation -> Import document -> GED privée -> Analyse IA -> Proposition -> Correction humaine -> Validation humaine explicite) est entièrement opérationnel, sécurisé et validé par 40 tests automatisés. Le passage à PC3 est autorisé.
