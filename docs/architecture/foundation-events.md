# Events publics Foundation — lot 14.1

Les Events publics sont définis dans `Socle\Contracts\Events`. Leurs noms expriment
un fait passé. Le payload est immutable (`final readonly`), composé uniquement de
scalaires ou DTO readonly, jamais de Models Eloquent. Les listeners portent les
effets secondaires. Les événements de ce lot implémentent `ShouldDispatchAfterCommit` :
pas de notification avant la validation de la transaction englobante, ni après rollback.

- `UserAccountCreated(userId)` : StaffAccountService a inséré un nouveau compte du personnel.
- `UserAccountReactivated(userId)` : le même service a réutilisé un compte existant reconnu
  comme placeholder ou désactivé selon les critères legacy ; l'ID est conservé. Il n'émet
  pas également UserAccountCreated. Ces événements ne couvrent pas tous les parcours de
  création de User de l'application.
- `UserFeatureAccessChanged` : une attribution utilisateur/rôle/groupe a changé pour le
  destinataire indiqué. Ce fait ne garantit pas un changement de ses droits effectifs
  (les groupes restent additifs). Les destinataires actifs sont sélectionnés par Socle.
  Code, libellé, catégorie, état accordé/retiré, date et nom du groupe éventuel constituent
  le snapshot minimal nécessaire pour conserver le message existant sans lire les Models
  internes Socle depuis Communication. Les mutations de membres n'émettent pas cet Event.

Communication enregistre explicitement les listeners : les deux Events de compte déclenchent
SendStaffAccountWhatsApp ; UserFeatureAccessChanged déclenche la notification in_app existante
`habilitation_modifiee`. Les destinataires supprimés ou devenus inactifs sont ignorés pour
cette dernière. Aucun événement sans consommateur n'est ajouté.

Après commit ne signifie pas livraison garantie : les listeners restent synchrones et une
exception ne peut plus annuler une écriture déjà validée. L'outbox et les reprises de livraison
ne font pas partie de ce lot. La présence d'un téléphone n'est pas une nouvelle précondition
à la création de compte ; un téléphone absent conserve le compte et bloque la notification
après commit, comportement couvert par StaffAccountTest.
