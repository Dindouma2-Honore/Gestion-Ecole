# Module Finances — Développeur B (2/2)

Zone : RH & finances.

## Modules du cahier des charges couverts ici (10 sur 18)

- [x] Module Frais scolaires
- [x] Module Paiements scolaires
- [x] Module Caisse
- [x] Module Recouvrement
- [x] Module Budget
- [ ] Module Dépenses
- [ ] Module Fournisseurs
- [ ] Module Achats et approvisionnements
- [ ] Module Stocks
- [ ] Module Comptabilité et rapports financiers

## Dépendances

- Consomme `Scolarite\Contracts\InscriptionServiceInterface::getFraisRestants()`
  pour savoir combien un élève doit encore payer — jamais de requête
  directe sur la table `inscriptions` (voir plan de projet, "Cas réel").
- Consomme `RH\Contracts\ContratServiceInterface` (même zone, autorisé)
  pour les primes/salaires liés à un contrat.

## E40 — Paiements scolaires

Encaissement transactionnel, numérotation via le Contract du Socle, calcul du
reste à payer via E39, historique, annulation motivée et auditée, sans aucune
suppression physique.

La génération du reçu PDF (C24) et la notification du parent (module F) seront
branchées lorsque leurs Contracts publics seront disponibles. Aucun accès
direct à leurs Models ou Services n'est introduit.

## E43 — Budget

Budgets annuels uniques, catégories et lignes prévisionnelles, validation
auditée, Resource Filament et widget de consommation. Le port de lecture
`DepenseServiceContract` est défini pour E44 ; tant qu'E44 n'est pas
implémenté, la consommation échoue explicitement au lieu d'afficher un faux
montant nul.

## E41 — Caisse

Ouverture/clôture de session journalière, mouvements d'encaissement et de
décaissement immuables, écart audité à la clôture. `PaiementService`
(E.40) appelle automatiquement `enregistrerMouvement()` pour tout paiement
en espèces — jamais de double saisie côté Comptable. Un paiement en
espèces échoue explicitement si aucune session n'est ouverte.

## E42 — Recouvrement

Relances par palier (SMS/WhatsApp puis lettre), échéanciers négociés et
promesses de paiement. `traiterRelancesImpayes()` s'appuie sur deux
Contracts ajoutés à cette occasion, en attente de leur implémentation
réelle :

- `Scolarite\Contracts\EleveServiceInterface::getElevesActifsIds()` —
  énumération des élèves, absente jusqu'ici.
- `Socle\Contracts\NotificationServiceContract::envoyer()` — dispatch de
  notification, absent jusqu'ici.

Les modules qui les possèdent (Scolarité, Socle) n'ont pas encore
d'implémentation concrète ; les tests utilisent des doubles
(`Tests\Support\FakeEleveService`, `Tests\Support\FakeNotificationService`)
comme le fait déjà E.39/E.40.

## E48b — Balance par période

Journal opérationnel en lecture seule des entrées validées et des sorties
réellement payées, avec période journalière par défaut, export CSV et section
séparée des dépenses en attente de validation. E48b lit E40 dans son propre
module et attend de E44 l'implémentation de `BalanceDepenseProviderContract` ;
aucun accès direct aux composants internes d'un autre module n'est autorisé.

## Préinscription — facture et contrôle du versement

Finances implémente le port `Scolarite\Contracts\InscriptionFacturationPort` :
il fige les frais obligatoires et optionnels choisis dans une facture
provisoire, l'envoie au parent payeur, puis expose la file de contrôle
Filament. Seul le rôle `Comptable` peut confirmer le versement. Cette
confirmation enregistre le paiement, demande à Scolarité de valider
définitivement l'inscription et envoie l'e-mail de confirmation.
