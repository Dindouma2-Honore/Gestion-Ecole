# Programmes & progression — audit et conception

Cette spécification remplace la conception précédente de Programmes & progression seulement.

## A. Réutilisation
Programme, ProgrammeChapitre, Progression, Seance et EmploiDuTemps sont déjà présents. Progression représente déjà une déclaration liée à une séance : ne pas créer une seconde table ProgressionSeance. ProgrammeChapitre peut porter les unités, leçons, objectifs et activités via un type et un parent. Utiliser EnseignantServiceInterface::getEnseignantIdPourUtilisateur pour la chaîne users → employes → enseignants, et les affectations Pédagogie (principal, assistants spécialisés/généralistes, matière), jamais les anciennes affectations RH matière/niveau.

DocumentServiceContract stocke les originaux sur le disque privé documents avec nom sécurisé et métadonnées ; DocumentVersion existe mais ne versionne pas la structure pédagogique. AuditServiceContract, la queue database et les composants AmbassadorsDesign/RecordModal sont réutilisables. Le calendrier est dans Socle : année, périodes, jours ouvrables et jours fériés. Aucun modèle Devoir/Travail scolaire dédié trouvé ; seul progressions.devoirs_donnes existe.

## B. Fichiers à modifier
ProgrammeResource et ses pages, ProgressionResource et ses pages, ProgrammesProgression, ProgrammeService, ProgressionService, ProgrammeDocumentParser, PedagogieServiceProvider, modèles Programme/ProgrammeChapitre/Progression. Socle : contrat documentaire pour lecture interne du fichier par le worker et support DOC contrôlé ; contrat de calendrier si nécessaire. Navigation et catalogue d'habilitations pour le suivi.

## C–E. Modèle proposé, nouveaux fichiers et relations
- Programme conserve une ligne par version, source privée et contexte offre + année + classe + enseignant. Ajouter origine_programme_id, version, analyse_statut, analyse_erreur, importe_le, valide_le. Une nouvelle version crée une nouvelle ligne ; aucun remplacement des éléments d'une version validée.
- ProgrammeChapitre est réutilisé comme élément générique : parent_id, type (chapitre, unité, séquence, leçon, objectif, compétence, activité). Les colonnes titres, objectifs, ordre et volumes sont conservées. Les anciens JSON restent lisibles, conversion explicite ultérieure sans suppression.
- Progression garde seance_id et chapitre_id (élément), ajoute état et observation. Classe/année/offre sont vérifiées par la séance et le programme. Aucun pourcentage saisi.
- DevoirPedagogique, uniquement si devoir=oui : progression/séance, classe, enseignant, offre, année, type, consigne, description et échéance. Pas de nouveau module.
- Contrats extracteur/analyseur ; services d'accès aux offres, import/version, structure, calcul et supervision ; job AnalyseProgramme.

## F/I. Traitement documentaire
Vérification serveur extension + MIME + taille 5 Mo ; original privé conservé avant dispatch afterCommit sur une queue non synchrone. Extraction PDF/DOCX/DOC séparée de l'analyse, résultat normalisé indépendant de tout fournisseur. PDF sans texte = échec explicite/OCR requis, jamais validation fictive. Worker idempotent, erreurs techniques journalisées et message public neutre, reprise explicite. L'enseignant corrige puis valide ; seuls les programmes validés alimentent les progressions. La validation ne peut pas être contournée par un statut transmis depuis le formulaire.

## G. Calcul
Unité suivie = feuille de la structure validée, sans double comptage parent/enfant. Seules les déclarations TERMINEE sur des séances dispensées du contexte classe/année/programme sont comptées. Plusieurs séances pour une même feuille sont autorisées. Pondération par heures si toutes les feuilles ont un volume positif ; sinon poids égaux pour toutes (pas de mélange heures/unités). Aucun pourcentage arbitraire pour COMMENCEE/EN_COURS. L'année écoulée utilise les jours ouvrables, périodes actives et exclusions du calendrier Socle ; manque de configuration signalé, pas de faux zéro. Écart en points, seuil central configurable. Historique détail → séance explicable.

## H. Permissions
Vérifier l'habilitation du composant et l'identité RH côté serveur, puis le périmètre et les dates d'affectation. Enseignant : import/correction/validation de ses versions, progression de ses séances. Direction : supervision limitée à son périmètre ; correction supérieure auditée. Aucun ID enseignant fourni par le navigateur ne fait autorité. Les URL directes et téléchargements doivent respecter le même périmètre. Versions validées et déclarations historiques non supprimables.

## J. Tests
Accès principal/assistant spécialisé/matière ; autre offre refusée ; utilisateur sans RH refusé ; dates d'affectation ; PDF/DOCX/DOC et MIME falsifié ; job succès/échec/reprise ; validation humaine ; version immutable ; plusieurs séances/élément ; devoir oui/non ; isolation classes ; calcul poids égaux/pondéré ; calendrier et seuil ; habilitations ; audit ; modales et routes. Exécuter les tests pédagogiques puis Deptrac (ne pas masquer les violations préexistantes).

## K. Migrations et risques
Migrations additives uniquement ; aucun reset des données. Ne pas marquer automatiquement les anciennes structures comme validées par l'enseignant. Garder les anciens programmes et liens historiques. Risques constatés : analyse synchrone limitée à 16 chapitres ; validation Fondateur seulement ; confusion utilisateurs/enseignants dans certaines listes ; progression actuelle compte les chapitres commencés et ne filtre pas la classe ; état global du chapitre incorrect pour un programme partagé ; sélection ancienne non compatible principal/assistant ; DOC annoncé dans l'UI mais refusé par le stockage Socle.

## Ordre d'implémentation
1. Corriger le calcul existant et l'isolation entre classes, avec tests de non-régression.
2. Sécuriser accès utilisateur → RH → affectations → offres et tests.
3. Versions, structure et import asynchrone avec tests.
4. Saisie après séance, devoirs et historique.
5. Écrans Mes programmes / Progression des séances / Suivi des progressions, calendrier et alertes.
