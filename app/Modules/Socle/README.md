# Module Socle

Construit en premier, avant que Dev A / Dev B / Dev C ne démarrent (voir
`docs/ambassadors_plan_projet.docx`, section 7 — Ordonnancement des sprints).

## Modules couverts (4)

- [x] Squelette — Module Utilisateurs, rôles et permissions (`UtilisateurServiceInterface`)
- [x] Squelette — Module Années scolaires et périodes (`AnneeScolaireServiceInterface`)
- [x] Module Paramétrage général
- [x] Module Audit et traçabilité (`spatie/laravel-activitylog`)

## Contracts exposés aux autres modules

| Interface | Rôle |
|---|---|
| `UtilisateurServiceInterface` | existe(), roles(), possedePermission() |
| `AnneeScolaireServiceInterface` | anneeActiveId(), estActive(), periode() |
| `ParametrageServiceContract` | configuration, niveaux, numérotation et paramètres typés |
| `AuditServiceContract` | audit avec motif, consultations sensibles et historique |

Ces deux interfaces sont les seules portes d'entrée vers le Socle. Aucun
autre module n'a le droit d'importer `App\Modules\Socle\Models\*` ni
`App\Modules\Socle\Services\*` directement — `deptrac.yaml` fait échouer la
CI si c'est le cas.

## Audit des futurs modèles métier sensibles

Les modules propriétaires de `Note`, `Paiement`, `Salaire`, `Bulletin`,
`Absence` et `Prime` devront appliquer `LogsActivity` avec une liste
explicite de champs. Ils utilisent `AuditServiceContract` lorsqu'un motif
ou l'audit d'une consultation sensible est requis.
