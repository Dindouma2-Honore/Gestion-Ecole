# AMBASSADORS ERP
# PROMPT GÉNÉRAL — SOCLE
# ARCHITECTURE CIBLE DE RÉFÉRENCE

Tu travailles sur Ambassadors ERP.

STACK :
- Laravel 12
- Filament
- monolithe modulaire
- architecture MVC
- Deptrac
- tests automatisés
- migrations Laravel
- base de données existante à préserver

IMPORTANT :
Ambassadors ERP est conçu pour UN SEUL ÉTABLISSEMENT.

Il ne s'agit PAS d'un SaaS multi-écoles.

Ne crée donc pas :
- tenant_id ;
- school_id généralisé ;
- campus_id généralisé ;
- TenantResolver ;
- CurrentTenant ;
- sélecteur d'établissement ;
- architecture multi-tenant.

L'identité de l'établissement est fixe.

Le contexte global variable principal est l'année scolaire sélectionnée.

============================================================
1. MISSION DU SOCLE
============================================================

Le Socle fournit les capacités transversales nécessaires aux autres
modules de l'ERP.

Il est propriétaire de :

1. identité utilisateur ;
2. authentification ;
3. comptes utilisateurs ;
4. groupes d'utilisateurs ;
5. habilitations ;
6. contexte d'année scolaire ;
7. périodes scolaires ;
8. calendrier institutionnel ;
9. paramètres généraux de l'établissement ;
10. numérotation transverse ;
11. GED / stockage documentaire privé ;
12. audit ;
13. contrats transversaux ;
14. DTO transversaux ;
15. événements transversaux.

Le Socle doit rester PETIT, STABLE et TRANSVERSAL.

Il ne doit pas devenir un module fourre-tout.

============================================================
2. CE QUI N'APPARTIENT PLUS AU SOCLE
============================================================

Le Socle NE DOIT PAS être propriétaire de :

- Cycle ;
- Niveau ;
- Filière ;
- Section scolaire ;
- Classe ;

car ils appartiennent à SCOLARITÉ.

Le Socle NE DOIT PAS être propriétaire de :

- Employé ;
- Enseignant ;
- Poste ;
- Contrat RH ;
- Congé RH ;
- Paie ;

car ils appartiennent à RH.

Le Socle NE DOIT PAS être propriétaire de :

- Fonction administrative métier ;

celle-ci relève de l'espace ADMINISTRATION.

Le Socle NE DOIT PAS être propriétaire de :

- matières ;
- programmes ;
- affectations pédagogiques ;
- séances ;
- évaluations ;
- notes ;
- bulletins pédagogiques ;

car ils appartiennent à PÉDAGOGIE.

Le Socle NE DOIT PAS être propriétaire de :

- caisse ;
- mouvement de caisse ;
- paiement ;
- comptabilité ;

car ils appartiennent à FINANCES.

Les anciens éléments génériques comme :

- workflows ;
- tâches ;
- réunions ;
- courriers ;

ne doivent pas rester automatiquement dans le Socle.

Auditer leur utilisation réelle et les déplacer vers leur propriétaire
fonctionnel lorsqu'ils sont encore nécessaires.

============================================================
3. RÈGLE FONDAMENTALE DE PROPRIÉTÉ
============================================================

Une donnée = un propriétaire.

Pour chaque objet métier, déterminer :

1. quel module le possède ;
2. quel module peut le modifier ;
3. quels modules peuvent le consulter ;
4. par quel Contract il est consulté ;
5. quels Events sont émis lorsqu'il change.

Un module externe ne doit jamais importer directement un Model interne
du Socle si un Contract constitue la frontière appropriée.

Inversement, le Socle ne doit pas importer les Models métier des autres
modules.

============================================================
4. UTILISATEURS
============================================================

User appartient au Socle.

Le compte utilisateur représente l'identité applicative permettant
d'accéder à l'ERP.

Le Socle gère notamment :

- création ;
- activation ;
- suspension ;
- authentification ;
- sécurité du compte ;
- rattachement aux groupes ;
- habilitations individuelles.

Un User n'est pas un Employe.

Un Employe appartient à RH.

La relation éventuelle est :

Employe.user_id nullable

Un employé peut donc exister sans compte utilisateur.

============================================================
5. SUPPRESSION DU MODÈLE PERMISSION
============================================================

La cible ne doit plus utiliser Permission comme concept fonctionnel
principal.

Le système d'accès est basé sur :

HABILITATION.

Ne pas maintenir deux moteurs concurrents :

Permission
+
Habilitation.

Si Spatie Permission ou un ancien système Role/Permission existe :

- l'auditer ;
- préserver temporairement la compatibilité ;
- migrer progressivement ;
- ne pas supprimer brutalement les données ;
- déprécier l'ancien mécanisme lorsque tous les consommateurs sont migrés.

============================================================
6. HABILITATIONS
============================================================

Une habilitation indique ce qu'un sujet peut faire sur une fonctionnalité.

Les quatre actions standards sont :

READ
WRITE
EXECUTE
PRINT

ou leurs équivalents normalisés :

LECTURE
ECRITURE
EXECUTION
IMPRESSION.

Choisir une seule convention interne.

Exemple :

scolarite.students

READ
WRITE
EXECUTE
PRINT

============================================================
7. SOURCES D'HABILITATION
============================================================

Une habilitation peut être attribuée à :

1. USER
2. GROUP
3. RH_POSITION

USER :
habilitation individuelle.

GROUP :
habilitation provenant d'un groupe d'utilisateurs.

RH_POSITION :
habilitation provenant d'un poste RH.

Exemple :

POSTE = Comptable

peut recevoir :

finance.cash
READ
WRITE
EXECUTE
PRINT

Le Socle reste propriétaire de l'habilitation.

RH reste propriétaire du Poste.

============================================================
8. GROUPES D'UTILISATEURS
============================================================

Groupe appartient au Socle.

Un groupe permet de regrouper transversalement des utilisateurs.

Exemples :

Direction
Secrétariat
Comptabilité
Surveillance

Un groupe n'est PAS un Poste RH.

Un utilisateur peut appartenir à plusieurs groupes.

Les habilitations d'un groupe sont héritées par ses membres.

============================================================
9. POSTE RH ET SOCLE
============================================================

Poste appartient exclusivement à RH.

Le Socle ne doit pas créer une copie locale de Poste.

Pour déterminer les postes actifs d'un utilisateur :

SOCLE
   ↓
RH Contract
   ↓
PositionRefDTO[]

Jamais :

Socle
→ RH\Models\Poste

Le Contract RH doit être minimal et readonly.

============================================================
10. CALCUL DES HABILITATIONS EFFECTIVES
============================================================

Version cible initiale :

EffectiveGrants(user)
=
IndividualGrants
UNION
GroupGrants
UNION
PositionGrants

Il n'existe pas de DENY prioritaire dans cette première version.

Default deny :

si aucune habilitation n'autorise l'action,
l'accès est refusé.

Un utilisateur désactivé ou suspendu ne reçoit aucun accès opérationnel.

============================================================
11. HABILITATION ≠ RÈGLE MÉTIER
============================================================

Une habilitation ne suffit pas toujours à autoriser une opération.

Autorisation finale :

HABILITATION
+
RÈGLE MÉTIER
+
CONTEXTE ACADÉMIQUE

Exemple :

un utilisateur possède :

scolarite.enrollments / WRITE

mais l'année sélectionnée est clôturée.

Résultat :

écriture interdite.

Autre exemple :

rh.payroll / EXECUTE

autorise l'action technique,
mais le bulletin doit également être dans un statut métier permettant
l'opération.

============================================================
12. CATALOGUE DE FONCTIONNALITÉS
============================================================

Le Socle possède le catalogue des fonctionnalités sécurisables.

Concept :

Feature / Fonctionnalite

Exemples :

socle.users
socle.groups
socle.habilitations
socle.academic_years
socle.periods
socle.calendar
socle.documents
socle.audit

rh.employees
rh.positions
rh.contracts
rh.payroll

scolarite.students
scolarite.enrollments

etc.

Chaque module peut déclarer ses fonctionnalités.

Le Socle centralise leur utilisation pour les habilitations.

Éviter les chaînes dispersées et non contrôlées dans le code.

============================================================
13. ADMINISTRATION
============================================================

Administration est l'espace de gouvernance de l'ERP.

Il ne faut pas nécessairement en faire un nouveau domaine métier
indépendant avec duplication du Socle.

L'interface Administration assemble les services appropriés.

Navigation cible :

ADMINISTRATION

1. Tableau de bord
2. Utilisateurs
3. Groupes & habilitations
4. Paramètres
5. Audit

La notion de "Rôles & permissions" doit progressivement disparaître
de l'interface cible.

============================================================
14. FONCTION ADMINISTRATIVE
============================================================

Ne pas confondre :

POSTE RH
et
FONCTION ADMINISTRATIVE.

POSTE :
propriété RH.

FONCTION :
responsabilité administrative.

Exemple :

Employé :
Jean

Poste RH :
Enseignant

Fonction administrative :
Responsable des examens

Affectation pédagogique :
Principal de 6e A

Ces concepts sont différents.

Ne pas utiliser automatiquement Fonction comme source d'habilitation
dans la première version.

Les sources d'habilitation restent :

USER
GROUP
RH_POSITION.

============================================================
15. ANNÉE SCOLAIRE
============================================================

Le Socle reste propriétaire de l'année scolaire car elle constitue
un contexte transversal à l'ensemble de l'ERP.

Concept :

AcademicYear / AnneeScolaire.

États :

PREPARATION
ACTIVE
CLOTURE_EN_COURS
CLOTUREE
ARCHIVEE.

Transitions contrôlées.

Ne pas modifier librement le status avec un simple CRUD.

Utiliser des Actions/Services métier.

============================================================
16. UNE SEULE ANNÉE ACTIVE
============================================================

Il ne doit exister qu'une seule année :

ACTIVE

à la fois.

Ne pas conserver deux sources de vérité concurrentes comme :

status = ACTIVE
+
is_current = true.

La source de vérité cible est :

status.

Si is_current existe actuellement :

migrer progressivement son usage avant suppression.

============================================================
17. ANNÉE ACTIVE ≠ ANNÉE SÉLECTIONNÉE
============================================================

Distinction obligatoire.

ANNÉE ACTIVE :
année opérationnelle officielle de l'établissement.

ANNÉE SÉLECTIONNÉE :
année actuellement consultée par l'utilisateur.

Exemple :

année active :
2026-2027

année sélectionnée :
2025-2026

L'utilisateur peut consulter les archives sans changer l'année active.

Changer l'année sélectionnée ne doit jamais modifier l'année active.

============================================================
18. CONTEXTE ACADÉMIQUE
============================================================

Créer/conserver un service transversal :

AcademicContextContract

capable d'exposer notamment :

activeYear()
selectedYear()
isWritable(...)
canPerform(...)

selon la conception retenue.

Les autres modules ne doivent pas reconstruire eux-mêmes cette logique.

============================================================
19. ÉCRITURE SELON LE STATUT
============================================================

Règle générale :

PREPARATION
→ écritures de préparation contrôlées.

ACTIVE
→ opérations normales.

CLOTURE_EN_COURS
→ opérations fortement contrôlées.

CLOTUREE
→ lecture seule sauf opérations administratives exceptionnelles explicites.

ARCHIVEE
→ lecture seule.

Mais le Socle ne décide pas seul de toutes les règles métier.

Chaque module peut ajouter ses propres contraintes.

============================================================
20. PÉRIODES SCOLAIRES
============================================================

Le Socle gère les périodes temporelles transversales.

Types racines autorisés :

SEMESTER
TRIMESTER.

Une année utilise UN système racine.

Pas :

semestres + trimestres racines simultanément.

Des SEQUENCES peuvent éventuellement exister sous une période racine.

Exemple :

Année
└── Trimestre 1
    ├── Séquence 1
    └── Séquence 2

Interdire :

Sequence
└── Sequence.

============================================================
21. CALENDRIER INSTITUTIONNEL
============================================================

Le Socle gère le calendrier transversal.

Il doit pouvoir représenter :

- semaine standard ;
- jours ouvrables ;
- jours non ouvrables ;
- jours fériés ;
- vacances ;
- fermetures exceptionnelles ;
- événements institutionnels nécessaires au calendrier.

Modèle recommandé :

CALENDRIER STANDARD
+
EXCEPTIONS PAR DATE.

Éviter de générer inutilement une ligne pour chaque jour de l'année.

============================================================
22. EXAM_DAY
============================================================

Si EXAM_DAY existe dans le calendrier du Socle :

il représente uniquement une information institutionnelle.

Le Socle ne gère pas :

- examen ;
- matière ;
- salle d'examen ;
- candidats ;
- surveillants ;
- notes.

Ces informations appartiennent à Pédagogie.

============================================================
23. PARAMÈTRES DE L'ÉTABLISSEMENT
============================================================

Il existe UN établissement.

Le Socle conserve son profil/configuration générale :

- nom ;
- logo ;
- contacts ;
- adresse générale ;
- devise ;
- formats ;
- paramètres institutionnels réellement transversaux.

Ne pas créer une table multi-écoles inutile.

Exposer ces informations par :

SchoolProfileContract
ou
SchoolSettingsContract

selon l'existant.

============================================================
24. DEVISE
============================================================

Le Socle peut fournir :

currency = XAF
currency_decimals = 0
formatting.

Mais le Socle ne calcule pas :

- frais ;
- salaires ;
- caisse ;
- paiements.

Les modules métier utilisent la configuration monétaire commune.

Ne jamais utiliser float/double pour les montants financiers.

============================================================
25. NUMÉROTATION
============================================================

Le Socle fournit un générateur de séquences transversal.

Exemples de codes sémantiques :

STUDENT
EMPLOYEE
INVOICE
RECEIPT
PAYROLL
DOCUMENT

Éviter que les modules codent eux-mêmes :

"FAC-"
"REC-"
"EMP-"

partout dans le code.

Le générateur doit être atomique.

Utiliser transaction + verrouillage approprié pour éviter deux numéros
identiques en concurrence.

============================================================
26. SEQUENCE CONTRACT
============================================================

Exposer :

SequenceGeneratorContract

Concept :

next(
    sequenceCode,
    context
)

Les modules utilisent le service.

Ils ne modifient pas directement la table des compteurs.

============================================================
27. GED
============================================================

Le Socle possède la couche documentaire générique.

Il est propriétaire de :

Document
DocumentVersion
stockage
hash
version
metadata technique
sécurité de stockage.

Les fichiers sont PRIVÉS par défaut.

Ne pas rendre les documents métier publiquement accessibles par URL
directe.

============================================================
28. MÉTIER ET GED
============================================================

Le module métier reste propriétaire du SENS du document.

Exemple :

Scolarité :

DocumentEleve
→ acte de naissance

RH :

DocumentEmploye
→ contrat signé

Pédagogie :

ProgrammeImport
→ programme annuel.

Mais le fichier physique appartient à GED.

Donc :

Module métier
      ↓
DocumentVaultContract
      ↓
SOCLE / GED.

============================================================
29. DOCUMENT VERSIONING
============================================================

Chaque remplacement important d'un document doit pouvoir créer une
nouvelle version.

Ne pas écraser silencieusement le fichier précédent lorsqu'un historique
est nécessaire.

Conserver notamment :

document_id
version
storage_path
hash
size
mime_type
created_by
created_at

selon les structures existantes.

============================================================
30. AUDIT
============================================================

Le Socle fournit l'audit transversal.

L'audit doit être append-only du point de vue applicatif.

Il doit pouvoir enregistrer :

actor
action
module
entity_type
entity_id
before
after
metadata
timestamp.

Ne pas permettre un CRUD normal sur les logs d'audit.

============================================================
31. DONNÉES SENSIBLES DANS L'AUDIT
============================================================

Ne jamais enregistrer en clair dans l'audit :

password
password_confirmation
token
OTP
secret
API key
credentials
données techniques sensibles.

Créer une stratégie centralisée de redaction/sanitization.

============================================================
32. AUDITS EXISTANTS
============================================================

Si plusieurs mécanismes existent :

audit_logs
activity_log
Spatie Activitylog
ou équivalent,

ne pas supprimer arbitrairement.

Identifier :

- lequel est réellement utilisé ;
- les consommateurs ;
- les données historiques ;
- les différences.

Puis converger progressivement vers une source de vérité.

============================================================
33. CONTRACTS PUBLICS DU SOCLE
============================================================

La cible peut exposer notamment :

AcademicContextContract
AcademicCalendarContract
SchoolProfileContract
SequenceGeneratorContract
DocumentVaultContract
AuditTrailContract
HabilitationContract

et uniquement si nécessaire :

UserDirectoryContract.

Ne crée pas un Contract inutilisé uniquement pour compléter une liste.

============================================================
34. DTO
============================================================

Les Contracts retournent :

- scalars ;
- enums ;
- DTO readonly ;
- collections de DTO.

Ils ne retournent pas les Models Eloquent internes du Socle.

Exemples :

AcademicYearDTO
PeriodDTO
CalendarDayDTO
SchoolProfileDTO
DocumentRefDTO
UserRefDTO
FeatureDTO.

============================================================
35. EVENTS
============================================================

Utiliser des Events lorsque les autres modules doivent être informés
qu'une situation a changé.

Exemples :

AcademicYearActivated
AcademicYearClosingStarted
AcademicYearClosed
AcademicYearArchived
CalendarChanged
SchoolSettingsChanged
UserSuspended.

Les Events résultant d'une transaction doivent être dispatchés après commit.

============================================================
36. CONTRACT OU EVENT ?
============================================================

Règle :

J'AI BESOIN D'UNE INFORMATION MAINTENANT
→ CONTRACT.

QUELQUE CHOSE VIENT DE CHANGER
→ EVENT.

Ne pas utiliser un Event pour simuler une lecture synchrone.

Ne pas créer de dépendances directes simplement pour éviter un Contract.

============================================================
37. STRUCTURE CIBLE DU MODULE
============================================================

Adapter à la structure réelle du dépôt, mais viser conceptuellement :

app/Modules/Socle/
├── Contracts/
├── DTOs/
├── Enums/
├── Models/
├── Services/
├── Actions/
├── Events/
├── Listeners/
├── Policies/
├── Exceptions/
├── Providers/
└── Filament/

Ne déplace pas brutalement des fichiers uniquement pour obtenir cette
arborescence.

Migrer progressivement.

============================================================
38. FILAMENT
============================================================

Filament est une couche d'interface et d'orchestration.

Les Resources/Pages ne doivent pas contenir les règles métier critiques.

Éviter :

Resource
→ logique métier complexe
→ modification directe multi-table.

Préférer :

Filament
→ Action/Service
→ transaction
→ audit
→ Event.

============================================================
39. INTERFACE ADMINISTRATION
============================================================

Navigation cible :

ADMINISTRATION

Tableau de bord

Utilisateurs

Groupes & habilitations

Paramètres
├── Établissement
├── Années scolaires
├── Périodes
├── Calendrier
└── Numérotation si administrable

Audit

Ne pas remettre :

Rôles & permissions

comme système cible.

============================================================
40. HABILITATIONS FILAMENT
============================================================

READ :
voir Resource/Page/données.

WRITE :
créer/modifier les données ordinaires.

EXECUTE :
actions métier sensibles.

PRINT :
imprimer/exporter les documents autorisés.

Les contrôles doivent être côté serveur.

Cacher un bouton Filament ne constitue pas une autorisation suffisante.

============================================================
41. AUDIT DES CHANGEMENTS D'ACCÈS
============================================================

Toute modification importante de :

- habilitation utilisateur ;
- habilitation groupe ;
- habilitation poste ;
- appartenance à un groupe ;

doit être auditée.

Le cache des habilitations doit être invalidé immédiatement après
modification.

============================================================
42. TRANSACTIONS
============================================================

Toute opération métier critique multi-table doit être transactionnelle.

Exemples :

activation année scolaire ;
changement important d'habilitations ;
génération de séquence critique ;
enregistrement documentaire + metadata ;
opération administrative sensible.

============================================================
43. MIGRATIONS
============================================================

Le projet existe déjà.

Toutes les refontes doivent préserver les données.

INTERDIT :

- réécrire arbitrairement les anciennes migrations exécutées ;
- DROP massif ;
- recréer la base pour simplifier ;
- perdre des IDs.

Méthode :

OLD
→ ADD NEW STRUCTURE
→ BACKFILL
→ MIGRATE APPLICATION
→ VERIFY
→ DEPRECATE
→ DROP LATER.

============================================================
44. DEPTRAC
============================================================

Deptrac représente les frontières réelles.

Ne pas simplement ajouter des exceptions pour faire passer Deptrac.

Lorsqu'une violation existe :

1. identifier la dépendance ;
2. déterminer le propriétaire ;
3. créer/réutiliser le Contract nécessaire ;
4. migrer le consommateur ;
5. supprimer la dépendance directe ;
6. seulement ensuite supprimer l'exception.

============================================================
45. DÉPENDANCES DU SOCLE
============================================================

Le Socle doit avoir le minimum possible de dépendances métier.

Il ne doit pas dépendre directement de :

Scolarité\Models
RH\Models
Pédagogie\Models
Finance\Models
Assiduité\Models
VieScolaire\Models
Logistique\Models
Communication\Models.

Exception conceptuelle :

pour calculer les habilitations provenant d'un poste RH,
le Socle peut consommer un CONTRACT public de RH.

Jamais RH\Models\Poste.

============================================================
46. TESTS OBLIGATOIRES
============================================================

Prévoir progressivement des tests pour :

UTILISATEURS
- activation ;
- suspension ;
- compte désactivé sans accès.

GROUPES
- ajout utilisateur ;
- retrait utilisateur ;
- héritage habilitations.

HABILITATIONS
- USER ;
- GROUP ;
- RH_POSITION ;
- union des grants ;
- default deny ;
- READ ;
- WRITE ;
- EXECUTE ;
- PRINT.

ANNÉE SCOLAIRE
- une seule ACTIVE ;
- transitions valides ;
- transitions interdites ;
- sélection différente de l'année active ;
- année clôturée readonly.

PÉRIODES
- semestre ou trimestre ;
- pas de mélange racine ;
- séquences enfants valides.

CALENDRIER
- semaine standard ;
- exceptions ;
- jour férié ;
- fermeture.

NUMÉROTATION
- unicité ;
- concurrence ;
- incrément atomique.

GED
- document privé ;
- versioning ;
- hash ;
- autorisation.

AUDIT
- append-only ;
- before/after ;
- redaction des secrets.

ARCHITECTURE
- absence de dépendances métier interdites ;
- Contracts respectés ;
- Deptrac conforme.

============================================================
47. PRINCIPES POUR LES AUTRES MODULES
============================================================

Les autres modules utilisent le Socle pour :

AUTHENTIFICATION
HABILITATIONS
CONTEXTE ACADÉMIQUE
CALENDRIER
PARAMÈTRES ÉTABLISSEMENT
NUMÉROTATION
GED
AUDIT.

Ils ne doivent pas reconstruire localement ces mécanismes.

Inversement :

le Socle ne doit pas absorber leurs règles métier.

============================================================
48. RÈGLE SUR LA CAISSE COMMUNE
============================================================

Le Socle ne possède aucune logique de caisse.

L'ERP possède une caisse commune appartenant à FINANCES.

Les modules métier peuvent initier et valider leurs opérations financières
depuis leur propre interface.

Exemples :

Scolarité
→ encaissement d'un frais élève.

RH
→ paiement d'un salaire.

Logistique
→ dépense autorisée.

Le module métier reste responsable du contexte de l'opération.

FINANCES reste propriétaire :

- caisse commune ;
- mouvements de caisse ;
- journal financier central ;
- références financières ;
- entrées/sorties ;
- contre-passations ;
- clôtures de caisse.

Le Socle intervient uniquement pour les capacités transversales nécessaires :

- User ;
- Habilitation ;
- Audit ;
- Numérotation si utilisée.

============================================================
49. PRINCIPE DE L'OPÉRATION FINANCIÈRE
============================================================

Un utilisateur ne doit pas être obligé de quitter son module métier pour
aller valider manuellement la même opération dans Finances.

Exemple :

Scolarité
→ paiement élève
→ validation dans Scolarité
→ mouvement de caisse Finance automatiquement créé.

RH
→ paiement salaire
→ validation dans RH
→ mouvement de caisse Finance automatiquement créé.

Le module métier conserve son historique métier.

Finance conserve l'historique consolidé de la caisse.

Il ne doit toutefois exister qu'UNE SEULE vérité concernant le mouvement
de caisse.

============================================================
50. CRITÈRES DE FIN DU SOCLE
============================================================

Le Socle est correctement refondu lorsque :

- il est indépendant des domaines métier ;
- User lui appartient ;
- Groupe lui appartient ;
- Habilitation est le moteur d'accès unique cible ;
- Permission est déprécié ;
- les accès USER/GROUP/RH_POSITION fonctionnent ;
- Poste reste propriété RH ;
- Fonction n'est pas confondue avec Poste ;
- Cycle/Niveau/Filière/Section/Classe ne sont plus propriété du Socle ;
- année scolaire reste transverse ;
- année active et année sélectionnée sont distinctes ;
- périodes sont cohérentes ;
- calendrier est centralisé ;
- établissement unique est respecté ;
- numérotation est atomique ;
- GED est privée et versionnée ;
- audit est centralisé et sécurisé ;
- les Contracts/DTO/Events constituent les frontières ;
- Filament ne contient pas les règles métier critiques ;
- les migrations préservent l'existant ;
- les dépendances interdites sont supprimées ;
- les tests passent ;
- Pint passe ;
- Deptrac passe.

============================================================
51. RÈGLE DE TRAVAIL POUR L'AGENT
============================================================

Avant toute modification :

1. inspecte l'existant ;
2. identifie les consommateurs ;
3. compare l'existant à cette architecture cible ;
4. réutilise ce qui est correct ;
5. modifie ce qui doit évoluer ;
6. ne duplique pas une capacité existante correcte ;
7. utilise des migrations additives ;
8. préserve les données ;
9. ajoute les tests ;
10. exécute les validations disponibles.

Ne fais jamais une réécriture massive du Socle.

Travaille par petits lots :

INSPECT
→ MODIFY
→ MIGRATE
→ TEST
→ REPORT.

Si une commande ne peut pas être exécutée à cause de Docker, PHP,
Composer ou de l'environnement :

indique explicitement :

NOT RUN

avec la raison.

Ne prétends jamais qu'un test ou Deptrac passe sans l'avoir réellement
exécuté.
