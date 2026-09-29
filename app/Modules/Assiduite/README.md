# Module Assiduité — Développeur C

Zone : Temps & assiduité (biométrie/pointages). C'est la seule zone qui
dépend des deux autres — voir `docs/ambassadors_plan_projet.docx`,
section 6.

## Modules du cahier des charges couverts ici (8)

- [ ] Module Pointage du personnel
- [ ] Module Emplois du temps
- [ ] Module Séances de cours
- [ ] Module Présences des élèves
- [ ] Module Détection automatique des absences
- [ ] Module Biométrie
- [ ] Module Pointages *(complément — collecte brute, tous profils)*
- [ ] Module Moteur d'assiduité *(complément — calcule le statut à partir
      des pointages, congés et emploi du temps)*

## Ordre de développement recommandé (voir plan de projet, sprint 3-5)

1. Emplois du temps (dépend de `RH\Contracts\EnseignantServiceInterface`
   pour connaître la charge horaire des enseignants).
2. Séances de cours (dérive de l'emploi du temps — module interne, pas de
   dépendance externe supplémentaire).
3. Biométrie & Pointages (autonome, peut avancer en parallèle des deux
   points précédents).
4. Moteur d'assiduité en dernier — c'est le seul point bloquant du
   planning global : il attend `Scolarite\Contracts\EleveServiceInterface`
   (Dev A) et `RH\Contracts\EnseignantServiceInterface` (Dev B).

## Contract exposé (squelette déjà créé)

| Interface | Rôle |
|---|---|
| `PresenceServiceInterface` | getStatutJour(), enregistrerPointage() |
