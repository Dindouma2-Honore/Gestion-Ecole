# Module RH — Développeur B (1/2)

Zone : RH & finances. Aucune dépendance bloquante — peut démarrer dès que
le Socle est fusionné, en parallèle des zones A et C.

## Modules du cahier des charges couverts ici (8 sur 18)

- [ ] Module Personnel administratif
- [ ] Module Enseignants
- [ ] Module Contrats
- [ ] Module Congés et permissions
- [ ] Module Discipline du personnel
- [ ] Module Formation du personnel
- [ ] Module Salaires et paie
- [ ] Module Primes

Le reste des 18 modules de la zone B (frais scolaires, paiements, caisse,
recouvrement, budget, dépenses, fournisseurs, achats, stocks, comptabilité)
est dans `app/Modules/Finances/`.

## Contracts exposés (squelette déjà créé)

| Interface | Rôle |
|---|---|
| `EnseignantServiceInterface` | existe(), getChargeHoraire() |
| `ContratServiceInterface` | estActif(), getSolde() |

`getChargeHoraire()` est l'interface attendue par le module Assiduité
(Dev C) pour terminer le Moteur d'assiduité — livrer cette méthode en
priorité si possible, c'est ce qui débloque C en fin de sprint.
