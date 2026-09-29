# Module Scolarité — Développeur A (1/2)

Zone : Scolarité & pédagogie. Aucune dépendance bloquante — démarre dès
que le Socle est fusionné sur `develop`.

## Modules du cahier des charges couverts ici (7 sur 17)

- [x] Module Élèves
- [x] Module Parents et tuteurs
- [x] Module Classes et affectations
- [x] Module Inscriptions et réinscriptions (1er lot : inscription simple)
- [ ] Module Candidatures et admissions
- [ ] Module Cartes scolaires et badges
- [ ] Module Documents scolaires

Le reste des 17 modules de la zone A (matières, progression, évaluations,
notes, bulletins, examens, qualité pédagogique, discipline des élèves) est
dans `app/Modules/Pedagogie/` — voir son README.

## Ce qui est fait

- **Élèves** (`eleves` + `eleve_contacts_urgence`)
- **Parents et tuteurs** (`parents_tuteurs` + `eleve_parent`, relation N-N
  avec responsabilités : légal, paiement, récupération)
- **Classes** (`classes`) — capacité, effectif calculé depuis les
  inscriptions actives ou en attente de versement
- **Inscriptions** (`inscriptions` + `compteurs_matricules`) — génération
  sécurisée du matricule (verrou pessimiste sur le compteur), contrôle de
  la capacité de classe, interdiction de double inscription sur une même
  année scolaire

## Contracts exposés

| Interface | Rôle |
|---|---|
| `EleveServiceInterface` | existe(), getEleve(), creer() |
| `ParentTuteurServiceInterface` | existe(), getParent(), creer(), lierAEleve() |
| `ClasseServiceInterface` | existe(), getNomClasse(), getNiveauId(), getEffectif(), getPlacesRestantes() |
| `InscriptionServiceInterface` | inscrire(), estInscrit(), getFraisRestants() |

`getFraisRestants()` renvoie désormais le vrai reste à payer (délégué à
`PaiementServiceContract::getResteAPayer()`) — ce n'était qu'un stub à 0.0
en attendant un module Finances qui n'existera finalement jamais, voir
"Module 5 — circuit financier interne" plus bas.

## Dépendances

- Consomme `Socle\Contracts\AuditServiceContract` (journalisation des
  inscriptions).
- `niveau_id`, `annee_scolaire_id`, `professeur_principal_id` : référentiels
  d'autres modules (Socle, RH) — simples colonnes indexées, jamais de FK
  inter-module (voir `deptrac.yaml`).

## Interfaces Filament

- `EleveResource`, `ParentTuteurResource`, `ClasseResource` : CRUD standard.
- `InscriptionResource` : formulaire volontairement réduit à 3 champs
  (élève / classe / année) — toute la logique métier (matricule, capacité,
  double inscription) reste dans `InscriptionServiceInterface::inscrire()`.
  Pas de page d'édition : une inscription validée ne doit plus être
  librement modifiable (RG-13 du document de conception Module 2).

## Correction notable

Le fichier `Contracts/ClasseServiceInterface.php` livré précédemment était
syntaxiquement invalide (une seule ligne de méthode, sans `<?php`, ni
`namespace`, ni déclaration `interface` — erreur fatale). Corrigé.

## Tests

`tests/Feature/Modules/Scolarite/InscriptionTest.php` couvre la génération
de matricule, l'interdiction de double inscription et le contrôle de
capacité.

## Préinscription avec paiement

`demarrerPreinscription()` conserve l'inscription au statut `en_cours`,
vérifie le parent payeur et délègue la facture au port
`InscriptionFacturationPort`. Le matricule et le statut `validee` ne sont
attribués qu'après l'appel financier à `validerApresPaiement()`.

## Nouveau lot : frais de classe, statistiques et facturation

- **Classes** : nouveau champ `frais` (decimal), renseigné à la création
  (`ClasseResource`), exposé via `ClasseServiceInterface::getFrais()`.
- **Inscriptions** : le montant des frais s'affiche désormais dès qu'une
  classe est choisie (étape "Classe" du formulaire, `Select::make('classe_id')`
  passé en `->live()` + `Placeholder` dépendant).
- **Statistiques Inscriptions** : même pied de tableau que sur Élèves
  (total / filles / garçons), calculé sur la requête filtrée, plus un
  filtre "Sexe" (`whereHas('eleve', ...)`).
- **Facturation** (nouveau) : chaque inscription validée
  (`InscriptionService::inscrire()`) génère automatiquement sa facture via
  le nouveau `FactureServiceInterface::genererPourInscription()` :
  - Tables `factures` + `compteurs_factures` (même schéma de verrou
    pessimiste que `compteurs_matricules`), numérotation
    `FACT-AAAA-000001`.
  - Montant figé au moment de la génération (`Classe::frais`), parent
    destinataire figé (nom/téléphone) — même logique que l'"identite"
    figée dans `InscriptionFacturationPort::creerFactureProvisoire()`.
  - `marquerEnvoyee(int $factureId, string $canal = 'whatsapp')` et
    `marquerEchecEnvoi(int $factureId, string $raison)` sont les deux
    méthodes que le futur développeur WhatsApp doit appeler pour rendre
    compte de l'envoi — **l'intégration WhatsApp elle-même n'est pas
    faite ici**, on s'arrête à la génération de la facture.
  - Nouvelle `FactureResource` (lecture seule, `canCreate()` = false) pour
    suivre les factures et leur statut d'envoi.
  - Vue imprimable `facture-impression.blade.php` (format facture A4
    portrait), servie par `ImprimerFactureController`.

### Action manuelle requise : route d'impression

Route à ajouter dans `routes/web.php` du projet principal, juste après
`impressions.inscriptions` (même style, même middleware) :

```php
Route::get('/impressions/facture/{facture}', \App\Modules\Scolarite\Http\Controllers\ImprimerFactureController::class)
    ->middleware(['web', 'auth', \App\Http\Middleware\EnsureModuleAccess::class])
    ->name('impressions.facture');
```

`FactureResource` est découverte automatiquement par
`AdminPanelProvider::discoverResources()` (déjà pointé vers
`Modules/Scolarite/Filament/Resources`) — aucune autre modification à
faire côté panel.

### Dette technique connue : modèle Document de Socle

Socle prévoit un modèle de document générique (`DocumentServiceContract`),
encore en cours de développement par un collègue. `FactureService` reste
volontairement autonome (compteur local `CompteurFacture`, même schéma que
`CompteurMatricule`) tant que ce contrat n'est pas livré et son contenu
réel confirmé — pas question de deviner sa signature à l'avance (voir les
bugs passés avec `ParametrageServiceContract`/`ProgrammeServiceInterface`).
Un repère `TODO` est laissé en tête de `FactureService` pour la bascule
future.

### Contracts modifiés/ajoutés

| Interface | Ajout |
|---|---|
| `ClasseServiceInterface` | `getFrais(int $classeId): float` |
| `FactureServiceInterface` (nouveau) | `genererPourInscription()`, `getFacture()`, `marquerEnvoyee()`, `marquerEchecEnvoi()` |

## Nouveau lot : Module 5 — circuit financier interne (fin du module Finances externe)

**L'inscription ne va plus jusqu'au module Finances.** Toute la
facturation (frais, grille tarifaire, versements, factures) est désormais
portée par Scolarité elle-même, en implémentation de la spécification
"Module 5 — Inscriptions scolaires". Ce lot remplace le circuit précédent
(`demarrerPreinscription()` + `InscriptionFacturationPort` fourni par
Finances) plutôt que de l'étendre.

### Ce qui change

- **`InscriptionFacturationPort` est supprimé.** `InscriptionServiceInterface::inscrire()`
  est désormais le seul point d'entrée de l'inscription et orchestre, en
  une seule transaction : création de l'inscription (statut initial
  `en_attente_versement`) → résolution des frais dus via
  `FraisServiceContract` → génération + envoi automatique par e-mail de la
  facture provisoire via `FactureServiceInterface::genererProvisoire()`.
  Les méthodes `demarrerPreinscription()`, `preparerEtDemarrerPreinscription()`
  et `validerApresPaiement()` disparaissent ; `activerApresVersement()` les
  remplace (déclenchée par `PaiementServiceContract` une fois le reste à
  payer global tombé à 0 — voir plus bas).
- **`inscriptions.statut`** perd l'état `en_cours` et devient exactement
  `en_attente_versement` → `active` → `annulee`.
- **Le matricule est désormais généré à la création de l'élève**
  (`Eleve::booted()` → `EleveServiceInterface::genererMatricule()`), plus à
  l'inscription — identifiant permanent, indépendant du statut
  d'inscription. Numéroté via Socle (`ParametrageServiceContract`, type
  `matricule_eleve`, remis à zéro chaque année scolaire) — voir
  "Intégration Socle" plus bas.
- **`classes.frais` est supprimé.** Le montant de la scolarité n'est plus
  un unique montant par classe : il est résolu frais par frais
  (inscription / tranche 1 / tranche 2) via la nouvelle grille tarifaire.
- **L'ancienne table `factures`** (une facture unique par inscription) est
  remplacée par `factures_generees` : une facture **provisoire** à la
  création, une facture **définitive** une fois le versement confirmé.

### Nouveau modèle de frais (CRUD libre, aucune catégorie imposée)

- `categories_frais` / `Frais` : CRUD libre côté catégories — pas de règle
  "2 groupes fixes". Chaque `Frais` porte `utilise_grille_tarifaire`
  (true uniquement pour inscription/tranche 1/tranche 2) et
  `ordre_repartition` (1/2/3 pour la scolarité, 999 = frais divers,
  départagés par date de création).
- `grille_tarifaire` : tarif par frais × classe × année scolaire —
  s'applique uniquement aux frais de scolarité. Un frais qui l'exige sans
  tarif défini lève `TarifClasseNonDefiniException` — jamais un montant à
  0 par défaut.
- `frais_eleve` : frais effectivement rattachés à une inscription, montant
  figé au moment d'`InscriptionServiceInterface::inscrire()` (résolu via
  `FraisServiceContract::attacherFraisPourInscription()`).

### Versements et répartition en cascade

`PaiementServiceContract::enregistrerPaiement()` calcule le reste à payer
**global** de l'inscription avant toute écriture : un montant excédentaire
est **intégralement rejeté** (`MontantVersementExcedentaireException`,
avec le reste exact) — jamais d'acceptation partielle, jamais de crédit
créé. Le versement accepté est réparti en cascade sur les `frais_eleve`
dus, triés par `ordre_repartition` puis date de création
(`paiement_repartitions`). Si le reste à payer tombe à 0 après répartition,
`InscriptionServiceInterface::activerApresVersement()` est appelée
automatiquement : statut `active` + génération de la facture définitive.

`annulerPaiement()` ne supprime jamais la ligne — passe le paiement à
`statut = 'annule'` et crée une ligne `paiement_annulations` avec motif.
Correction volontaire par rapport au document de conception :
`FraisEleve::montantPaye()` exclut les répartitions dont le paiement est
annulé (sinon une annulation ne libérerait jamais le reste à payer).

Numérotation des reçus déléguée à Socle (`ParametrageServiceContract`, type
`recu_versement`) — voir "Intégration Socle" plus bas.

### Nouvelles interfaces Filament

`CategorieFraisResource`, `FraisResource`, `GrilleTarifaireResource`,
`PaiementResource` (registre en lecture seule + action "Annuler"). Sur
`InscriptionResource\Pages\ViewInscription` : actions "Encaisser un
versement" (appelle `PaiementServiceContract::enregistrerPaiement()`,
affiche le reste à payer exact en cas de rejet) et "Annuler l'inscription".
`FactureResource` pointe désormais sur `FactureGeneree` (colonne "Type" :
provisoire/définitive).

### Dossier élève et tableau d'honneur (nouveaux contracts)

- `DossierEleveServiceContract::getDossierComplet()` : agrégation pure
  lecture (élève, inscriptions, factures, reçus, tableau d'honneur) —
  aucune nouvelle donnée stockée. Les bulletins/certificats restent des
  placeholders volontaires : les modules Évaluations et Documents
  scolaires ne sont pas livrés dans ce module.
- `TableauHonneurServiceContract` : composition strictement manuelle par
  la Direction (`composer()`), jamais de génération automatique par seuil
  de moyenne. `retirer()` journalise le motif via le Socle puis supprime
  la ligne (pas de statut/soft-delete sur `tableau_honneur`).

### Contracts modifiés/ajoutés (Module 5)

| Interface | Changement |
|---|---|
| `InscriptionServiceInterface` | `inscrire()`/`reinscrire()` prennent `$fraisDiversIds`, `activerApresVersement()` remplace `validerApresPaiement()`, ajout `annulerInscription()` ; `demarrerPreinscription()`/`preparerEtDemarrerPreinscription()` supprimées |
| `FactureServiceInterface` | `genererPourInscription()` → `genererProvisoire()` + `genererDefinitive()` |
| `ClasseServiceInterface` | `getFrais()` supprimée (remplacée par `FraisServiceContract`) |
| `EleveServiceInterface` | ajout `genererMatricule()` |
| `FraisServiceContract` (nouveau) | catégories/frais en CRUD libre, grille tarifaire, `attacherFraisPourInscription()`, `previsualiserMontant()`, `getMontantDu()`, `getDetailFrais()` |
| `PaiementServiceContract` (nouveau) | `enregistrerPaiement()`, `annulerPaiement()`, `getTotalPaye()`, `getResteAPayer()`, `getResteParFrais()`, `getHistoriquePaiements()` |
| `DossierEleveServiceContract` (nouveau) | `getDossierComplet()` |
| `TableauHonneurServiceContract` (nouveau) | `composer()`, `getTableauDeLaPeriode()`, `retirer()` |
| `InscriptionFacturationPort` | supprimé |

## Intégration Socle (module réel reçu et vérifié)

Le module Socle a été livré et lu intégralement (contrats **et**
implémentations concrètes, pas seulement les interfaces) : les points
listés comme "dette technique" dans le lot précédent sont résolus ici,
sans deviner aucune signature — même discipline que le reste du module
("on ne branche jamais un Contract avant d'avoir vérifié sa vraie
signature").

### Numérotation (`ParametrageServiceContract`)

`compteur_matricules`, `compteurs_factures` et `compteurs_recus` sont
**supprimés**. Toute numérotation passe par
`ParametrageServiceContract::genererNumero(typeDocument, variables)`, adossé
à la table `formats_numerotation` de Socle (verrou pessimiste géré côté
Socle — plus besoin de le reproduire ici) :

| Type document | Pré-semé par | Format | Réinitialisation |
|---|---|---|---|
| `facture` (facture **définitive**) | Socle (`bulletin`/`carte_scolaire` à ses côtés — manifestement prévus pour ce module) | `FACT-{ANNEE_SCOLAIRE}-{SEQ:5}` | année scolaire |
| `facture_provisoire_scolarite` | Scolarité (migration `2026_09_01_000013`) | `FACT-PROV-{ANNEE_SCOLAIRE}-{SEQ:5}` | année scolaire |
| `recu_versement` | Scolarité (migration `2026_09_01_000013`) | `REC-{ANNEE_SCOLAIRE}-{SEQ:6}` | année scolaire |
| `matricule_eleve` | Scolarité (migration `2026_09_01_000013`) | `AMB-{ANNEE_SCOLAIRE}-{SEQ:6}` | année scolaire |

La facture définitive réutilise volontairement le type `facture` déjà
pré-semé par Socle plutôt que d'en recréer un — la provisoire, non fiscale,
garde son propre compteur pour ne jamais partager sa séquence avec la
définitive.

### Verrouillage d'année scolaire (`RattacheAAnneeScolaire`)

`Inscription`, `Classe`, `GrilleTarifaire`, `Paiement`, `FraisEleve` et
`TableauHonneur` implémentent `RattacheAAnneeScolaire` ;
`VerrouAnneeObserver` (Socle) est enregistré sur chacun dans
`ScolariteServiceProvider::boot()`. Toute écriture (`saving`/`deleting`) sur
une année scolaire `cloturee` ou `archivee` est bloquée
(`AnneeVerrouilleeException`), sauf le rôle `Fondateur` sur une année
`cloturee` (jamais `archivee`). `Paiement`/`FraisEleve` n'ont pas de colonne
`annee_scolaire_id` directe — résolue via leur inscription rattachée.

### Scoping par niveau (`ScopedByNiveau`)

`Classe` (`getNiveauScopeColumn()` → `niveau_id`) et `Inscription`
(→ `classe.niveau_id`, chemin relationnel) enregistrent `NiveauScope`
(Socle) en global scope dans `booted()` : un `Directeur`/`Enseignant` ne
voit que les classes/inscriptions de son niveau (fail-closed si son
`niveau_id` n'est pas configuré — aucune ligne visible plutôt qu'une fuite).
Comportement automatique et transparent pour tous les appels du module —
aucun changement requis dans les services.

### Transfert annuel (`TransfertElevesAnneeContract`)

`TransfertAnneeService` remplace le placeholder `TransfertElevesIndisponible`
que Socle lie par défaut (rebind dans `ScolariteServiceProvider::register()` ;
suppose que ce provider est enregistré après celui de Socle). Portée
volontairement minimale et honnête : aucune règle de progression de niveau
(CP1 → CP2...) n'est documentée nulle part dans ce codebase (pas de champ
"classe suivante" sur `Classe`) — elle n'est donc pas inventée. `transferer()`
réinscrit (`InscriptionServiceInterface::reinscrire()`, même circuit qu'une
réinscription manuelle : frais, facture provisoire...) chaque élève encore
**activement** inscrit (statut `active`, versement confirmé) dans la classe
de même nom côté année destination. Un échec individuel (classe destination
introuvable, capacité dépassée, parent payeur invalide, élève déjà
transféré) ne bloque jamais les autres élèves du lot — il est journalisé via
le Socle pour revue par la Direction plutôt que de faire échouer tout le
transfert.

**Bug corrigé (dépendance circulaire au conteneur) :** `InscriptionServiceInterface`
n'est **jamais injecté au constructeur** de `TransfertAnneeService` — résolu
paresseusement (`app(InscriptionServiceInterface::class)`) uniquement au
moment de l'appel dans `transfererUnEleve()`. Une première version l'injectait
au constructeur, ce qui fermait une boucle au moment de la résolution par le
conteneur : `AnneeScolaireService` (Socle) dépend de
`TransfertElevesAnneeContract` → `TransfertAnneeService` → (avant fix)
`InscriptionServiceInterface` → `InscriptionService` → `FactureServiceInterface`
→ `FactureService` → `AnneeScolaireServiceContract` → retour à
`AnneeScolaireService`. Cette boucle se déclenchait sur **chaque page
Filament** (la bannière "année active" que Socle enregistre en hook global
appelle `app(AnneeScolaireServiceContract::class)` à chaque rendu), ce qui
cassait l'accès à tout le panneau d'administration, pas seulement à
Scolarité. Même précaution déjà appliquée à
`InscriptionService::getFraisRestants()` pour `PaiementServiceContract`.

### Archivage des documents (`DocumentServiceContract`)

`factures_generees.document_id`, `paiements.document_recu_id` et
`tableau_honneur.document_id` sont désormais de vraies clés étrangères vers
`documents` (Socle). `FactureService`/`PaiementService` appellent
`DocumentServiceContract::attacherContenu()` à la génération de chaque
facture/reçu, avec le même gabarit HTML que l'impression à la volée
(`facture-impression.blade.php` / nouveau `recu-impression.blade.php`) —
stocké en `text/html` (`attacherContenu()` accepte n'importe quel
mimeType, aucune dépendance à une bibliothèque PDF non confirmée).
`tableau_honneur.document_id` reste nullable, sans génération automatique
dans ce lot (aucun contenu à archiver n'existe pour lui) : renseignable au
besoin via `DocumentServiceContract::attacher()` ailleurs.

**Conformité deptrac** (voir `Socle/README.md` : "Aucun autre module n'a
le droit d'importer `App\Modules\Socle\Models\*` ni `App\Modules\Socle\Services\*`
directement — `deptrac.yaml` fait échouer la CI si c'est le cas") :
`FactureGeneree`, `Paiement` et `TableauHonneur` stockent la colonne
`document_id`/`document_recu_id` (une simple FK, aucune contrainte deptrac
là-dessus) mais **n'ont pas de relation Eloquent `belongsTo` vers
`App\Modules\Socle\Models\Document`** — ça a été la première version de ce
lot, corrigée après relecture : ce serait un import direct de `Models\*`.
Chaque modèle expose à la place `getUrlDocument(): ?string`, qui passe
uniquement par `DocumentServiceContract::getUrlTelechargement()`. À
l'inverse, `VerrouAnneeObserver` (`Observers\*`) et `NiveauScope`
(`Scopes\*`) sont importés directement (`Model::observe()` /
`addGlobalScope()`) : ce n'est pas une exception à la règle mais le calque
exact du seul usage que Socle fait lui-même de ces classes
(`SocleServiceProvider::boot()` : `Periode::observe(VerrouAnneeObserver::class)`
sur son propre modèle) — seuls `Models\*` et `Services\*` sont concernés
par l'interdiction documentée.

### Sémantique corrigée d'`AuditServiceContract`

Le vrai contrat distingue `enregistrer(sujet, description)` (opération
routinière, sans motif) de `enregistrerAvecMotif(sujet, description, motif)`
— dont le **troisième paramètre est un vrai motif texte**, pas un code
d'événement. Le lot précédent utilisait `enregistrerAvecMotif()` partout
avec un code d'événement en 3ᵉ argument (ex: `'inscription_initiale'`) :
corrigé partout — `enregistrer()` pour les événements système routiniers
(création d'inscription, génération de facture, encaissement, envoi
e-mail, activation après versement, composition du tableau d'honneur),
`enregistrerAvecMotif()` réservé aux véritables annulations qui portaient
déjà un vrai motif en paramètre (`annulerPaiement()`, `annulerInscription()`,
`TableauHonneurServiceContract::retirer()`) — motif désormais transmis tel
quel au lieu d'être remplacé par un code.

