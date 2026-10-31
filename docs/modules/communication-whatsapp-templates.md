# Templates WhatsApp — gestion du cycle de vie Meta

## Fonctionnement livré

L’entrée **Communication → Templates** permet de créer un brouillon, définir ses variables et exemples, prévisualiser, valider localement, soumettre à Meta, synchroniser, activer puis envoyer un test. Une fiche contient les informations Meta, les dernières tentatives de test et l’historique des opérations.

Les statuts locaux (`DRAFT`, `READY`, `ACTIVE`, `DISABLED`) sont distincts des statuts Meta (`NOT_SUBMITTED`, `PENDING`, `APPROVED`, `REJECTED`, `PAUSED`, `DISABLED`, `UNKNOWN`). Le seeder ne soumet rien à Meta et n’active rien. Une réponse de soumission laisse le template en attente ; une réconciliation avec Meta doit confirmer l’approbation avant activation.

Les versions soumises sont immuables dans l’ERP. Pour corriger un rejet ou changer un modèle approuvé : **Dupliquer → modifier → valider → soumettre → synchroniser → activer**, puis désactiver l’ancienne version. Aucune suppression chez Meta n’est proposée ; la permission correspondante est réservée, sans attribution automatique.

## Sources vérifiées

L’intégration utilise la version Graph déclarée dans `WHATSAPP_GRAPH_VERSION` et le **WABA**, et non le numéro visible, pour gérer les templates :

- `POST /{WABA-ID}/message_templates` : création.
- `GET /{WABA-ID}/message_templates` : liste paginée et recherche par nom.
- `POST /{PHONE-ID}/messages` : envoi du template approuvé.
- `POST /app/uploads`, puis `POST /{Upload-ID}` : exemple PDF via téléversement reprenable.

Sources primaires : [collection officielle Meta — création](https://www.postman.com/meta/whatsapp-business-platform/request/uzphwqw/create-template-w-text-header-text-body-text-footer-and-2-quick-reply-buttons), [SDK Business officiel Meta](https://github.com/facebook/facebook-python-business-sdk/blob/main/facebook_business/adobjects/whatsappbusinessaccount.py), [webhook de statut officiel Meta](https://www.postman.com/meta/whatsapp-business-platform/request/enu4z5g/message-template-approved), [collection officielle Meta — téléversement](https://www.postman.com/meta/whatsapp-business-platform/documentation/wlk6lh4/whatsapp-cloud-api?entity=request-13382743-7919105f-6d65-43e8-95c8-0c6e9dcf1de0).

Le formulaire expose un sous-ensemble configurable : catégories `UTILITY` et `MARKETING`, langues `fr`, `en`, `en_US`. Les catégories d’authentification nécessitent un formulaire OTP spécifique et ne sont pas proposées. Les contraintes côté Meta et les capacités du WABA restent déterminantes ; l’ERP ne garantit jamais une approbation.

## Installation additive

Les deux migrations ci-dessous ont été appliquées à la base locale du projet pendant cette intervention. Elles ne doivent être exécutées ailleurs qu’avec le déploiement habituel :

```bash
php artisan migrate --path=app/Modules/Communication/database/migrations/2026_09_13_170000_create_communication_templates.php --path=app/Modules/Communication/database/migrations/2026_09_13_170100_add_template_permissions.php
php artisan db:seed --class=CommunicationTemplateSeeder
```

Le seeder est idempotent et préserve les modifications des modèles existants. Il prépare 33 brouillons : 14 parents, 5 enseignants, 4 personnel, 5 administration, 5 techniques. Les anciens `templates_notifications` et `whatsapp_template_metadata` restent conservés.

Tables créées : `communication_templates`, `communication_template_events`. La table `communication_messages` reçoit la référence du template, l’empreinte du contenu et l’utilisateur initiateur. Aucun secret Meta n’est stocké dans ces tables. Le handle d’exemple média est chiffré et caché dans la sérialisation.

## Configuration et exploitation

Conserver le token et le secret applicatif Meta uniquement dans la configuration sécurisée :

```dotenv
WHATSAPP_ENABLED=true
WHATSAPP_MODE=test
WHATSAPP_GRAPH_VERSION=v25.0
WHATSAPP_TEST_RECIPIENTS=+237670097386
WHATSAPP_TEMPLATE_SYNC_ENABLED=true
```

Les identifiants `WHATSAPP_PHONE_ID` et `WHATSAPP_WABA_ID` doivent correspondre au compte attendu. Le token nécessite les droits de gestion des templates et d’envoi. Le flag de synchronisation automatique est désactivé par défaut ; le traitement est déclaré toutes les 30 minutes dans le scheduler Laravel.

Les nouveaux tests et synchronisations utilisent la queue **whatsapp-templates** pour pouvoir être traités sans vider les autres queues métier :

```bash
php artisan queue:work --queue=whatsapp-templates --timeout=60 --tries=2
```

Le scheduler Laravel doit être déclenché par l’infrastructure existante. Ne pas lancer de scheduler supplémentaire concurrent. Une consultation Filament n’effectue aucune requête Meta. « Synchroniser tous » met un job en queue ; « Actualiser le statut » consulte explicitement Meta pour le modèle sélectionné.

Un état d’approbation vieux de plus de 60 minutes bloque un nouvel envoi jusqu’à réconciliation. Le worker recontrôle l’état local, l’approbation, l’identité WABA/nom/langue, l’empreinte, le destinataire autorisé et les droits de l’initiateur.

## Commandes administratives

Le paramètre `--actor` désigne explicitement le profil habilité au nom duquel la commande est exécutée et auditée. Ne pas employer un compte arbitraire. Pendant l’installation locale, le profil Administrateur Général existant a été utilisé.

```bash
php artisan whatsapp:templates preview --template=parent_inscription_confirmee --actor=ID
php artisan whatsapp:templates ready --template=parent_inscription_confirmee --actor=ID
php artisan whatsapp:templates submit --template=parent_inscription_confirmee --actor=ID --confirm
php artisan whatsapp:templates sync --template=parent_inscription_confirmee --actor=ID
php artisan whatsapp:templates activate --template=parent_inscription_confirmee --actor=ID
php artisan whatsapp:templates test --template=parent_inscription_confirmee --actor=ID --recipient=+237670097386 --confirm
php artisan whatsapp:templates sync --actor=ID
```

`--confirm` est obligatoire pour une soumission, un test ou le téléversement d’un exemple PDF. Une soumission incertaine ne peut pas être automatiquement relancée : synchroniser d’abord. S’il est nécessaire de recommencer avec un contenu corrigé, créer une nouvelle version.

## Documents et autres médias

Pour `parent_bulletin_document`, utiliser **Préparer un exemple PDF** : un bulletin fictif est téléversé auprès de Meta et son handle est enregistré chiffré. Valider puis soumettre uniquement après prévisualisation. Le test d’envoi produit un autre PDF fictif, uploadé comme média WhatsApp, puis envoyé dans le header DOCUMENT.

Les headers TEXT, IMAGE et VIDEO sont également représentés. IMAGE et VIDEO nécessitent un handle d’exemple Meta pour la soumission et un identifiant média de test pour l’envoi. Les boutons disponibles sont : réponses rapides, URL HTTPS fixes et numéro de téléphone international. Les boutons URL dynamiques, carrousels et formats OTP ne sont pas éditables dans ce formulaire.

## Webhooks et preuve de réception

Configurer chez Meta l’URL publique HTTPS correspondant à `/webhooks/whatsapp`, le token de vérification (`WHATSAPP_VERIFY_TOKEN`) et le secret applicatif (`WHATSAPP_APP_SECRET`). Le secret applicatif est distinct du token d’accès et ne peut pas être déduit de celui-ci.

Le contrôleur vérifie la signature HMAC et le WABA. Il traite les statuts des messages pour le Phone ID attendu, et les événements `message_template_status_update` / `message_template_quality_update` au niveau du WABA. Les suspensions bloquent immédiatement l’usage ; une approbation ne réactive jamais un template automatiquement. Une réconciliation API récupère le contenu courant. Une réponse API antérieure à un webhook récent ne peut pas annuler son effet.

`accepted` prouve seulement que Meta a retourné un identifiant de message. `sent`, `delivered`, `read` exigent des événements reçus via webhook. La lecture ne peut être garantie lorsque les accusés correspondants ne sont pas fournis par Meta. Aucun de ces états n’est simulé en exploitation réelle.

## Permissions et audit

Permissions ajoutées : `view_whatsapp_templates`, `create_whatsapp_template`, `update_whatsapp_template`, `preview_whatsapp_template`, `submit_whatsapp_template`, `sync_whatsapp_template`, `sync_all_whatsapp_templates`, `activate_whatsapp_template`, `disable_whatsapp_template`, `test_whatsapp_template`, `delete_whatsapp_template_provider`.

La migration attribue les permissions opérationnelles aux rôles existants Fondateur et Super Admin. La suppression provider n’est accordée à personne. Les services, la ressource et la policy contrôlent les accès ; le worker vérifie encore les droits avant envoi. Les actions journalisent date, initiateur, ancien/nouvel état et résultat fournisseur réduit, sans token ni handle média.

## Livrables techniques

- Modèles : `CommunicationTemplate`, `CommunicationTemplateEvent` ; extension additive de `CommunicationMessage`.
- Services : `WhatsAppTemplateService`, `WhatsAppTemplateValidator`, `MetaTemplateApiClient`, `WhatsAppTemplateResolver`.
- Exception : `WhatsAppTemplateNotApprovedException`.
- Jobs : `SyncWhatsAppTemplatesJob` ; extension de `SendWhatsAppMessage`.
- UI : `CommunicationTemplateResource`, pages de liste/création/modification/détail, vues Blade de détail et prévisualisation, widget `WhatsAppTemplateAlerts`.
- Sécurité : `CommunicationTemplatePolicy`, `WhatsAppTemplatePermissions`, `WhatsAppTemplateAudit`.
- Catalogue : `Support/initial-whatsapp-templates.json`, `CommunicationTemplateSeeder`.
- Commande : `whatsapp:templates`.
- Points d’intégration : provider Communication, dashboard Communication, webhook, dispatcher, client Cloud API, `config/whatsapp.php`, `routes/console.php`, `.env.example`.
- Tests : `tests/Feature/Communication/WhatsAppTemplateLifecycleTest.php`, non-régression dans `WhatsAppTest.php`.

## Validation et état réel

Le catalogue Meta a été lu avec succès : cinq templates de démonstration ont été importés, dont `hello_world` approuvé. Les 33 brouillons ont été créés dans la base locale. Le seul modèle personnalisé soumis est `parent_inscription_confirmee` ; le dernier statut confirmé était `PENDING`. Les 32 autres restent non soumis.

Les pages de liste, création et détail ont passé un contrôle de rendu Livewire sur la base locale. Les 49 tests automatisés passent avec 116 assertions sous PHP 8.3.33. Ils utilisent SQLite en mémoire et des réponses Meta simulées, sans envoi réel. Le formatage Pint des 32 fichiers PHP concernés a été appliqué.

À la reprise du 14 septembre, l’API Meta a refusé le token avec le code 190. Un nouveau token fourni par l’utilisateur a été enregistré et la synchronisation a repris avec succès : le template personnalisé restait `PENDING`. Son activation et son test réel attendent donc l’approbation de Meta. Aucun template personnalisé n’a été annoncé approuvé ni envoyé en l’absence de confirmation Meta.

Les événements scolaires existants restent volontairement en simulation pendant cette phase. Le reçu d’inscription réel et les bulletins réels ne sont pas envoyés automatiquement. Leur raccordement vient après validation du parcours template et de la réception effective.

### Vérification réelle du 14 septembre

Après renouvellement du token, le modèle `parent_inscription_confirmee` (identifiant Meta `2021320848566752`) reste `PENDING` au dernier contrôle. Le modèle de démonstration `hello_world` a été synchronisé, activé localement, puis envoyé via **WhatsAppDispatcher → queue whatsapp-templates → SendWhatsAppMessage → Meta**. Le message local #1 a atteint `accepted`, avec un identifiant fournisseur enregistré ; il vise uniquement le numéro de test autorisé.

`WHATSAPP_APP_SECRET` est maintenant configuré dans le fichier local `.env`. Aucune preuve webhook de livraison ou lecture n’est encore annoncée : la souscription Meta reste à terminer.

L’utilisateur a confirmé avoir reçu ce second « Hello World ». Cette confirmation humaine est distincte d’un accusé webhook ; le statut enregistré reste `accepted`.

Le flag local `WHATSAPP_TEMPLATE_SYNC_ENABLED` est activé. Un worker dédié `whatsapp-templates` est démarré dans le conteneur Laravel pour la session locale ; il faudra le relancer après arrêt du conteneur. Le déclenchement périodique dépend toujours du scheduler Laravel de l’installation. Aucun scheduler général n’a été lancé pendant cette intervention, pour ne pas exécuter les autres tâches métier.

Contrôle d’exploitation : le worker dédié est présent et `schedule:list` confirme la fréquence `*/30 * * * *` du job de synchronisation. Le scheduler général doit être déclenché par l’exploitation.


### Accès HTTPS temporaire du webhook

Le fichier `compose.whatsapp-webhook.yaml` démarre une passerelle Node et un tunnel Cloudflare sur le réseau Sail existant :

```bash
docker compose -p ambassadors-whatsapp-webhook -f compose.whatsapp-webhook.yaml up -d
docker compose -p ambassadors-whatsapp-webhook -f compose.whatsapp-webhook.yaml logs whatsapp-webhook-tunnel
```

Adresse créée le 14 septembre : `https://thru-tiffany-east-jumping.trycloudflare.com/webhooks/whatsapp`.
Cette adresse est temporaire ; une recréation du tunnel peut imposer une nouvelle souscription Meta. Les conteneurs du tunnel ne montent aucun secret ni fichier applicatif, hormis le script de passerelle.

La passerelle ne transmet que GET/POST sur `/webhooks/whatsapp`, limite les requêtes à 1 Mio, conserve les octets signés et masque les erreurs internes. Les tests locaux `node --test tools/whatsapp/webhook-proxy.test.mjs` passent : isolation des routes, retrait des cookies et des en-têtes de routage, conservation du corps et de la signature, masquage des erreurs et taille maximale.

La validation automatique a refusé le contrôle HTTPS avec jeton de vérification et les appels de configuration groupés : elle exige un accord explicite pour le transit de ce jeton par Cloudflare. Aucun abonnement Meta n’a été modifié pendant cette étape ; aucun test signé public ni nouvel envoi WhatsApp n’a été exécuté. Le prochain accord doit couvrir ce transit et la connexion du webhook à Meta. Le secret applicatif reste utilisé localement pour vérifier les signatures ; il ne doit pas être envoyé au tunnel.


### Connexion Meta validée après accord explicite

L’utilisateur a autorisé le transit du jeton de vérification par Cloudflare. Les contrôles HTTPS passent : routes privées refusées (404), challenge retourné exactement, POST sans signature refusé (403), événement vide correctement signé accepté (200). Aucun faux événement de livraison n’a été injecté.

Un saut de ligne avant `<?php` dans `bootstrap/providers.php` a été supprimé : il préfixait la réponse de challenge.

Meta a confirmé la création de l’abonnement de l’application `4385333388278116` et du WABA. Le callback ci-dessus est actif pour `messages`, `message_template_status_update` et `message_template_quality_update` (version de champs retournée par Meta : v26.0). Les abonnements préexistants d’autres applications n’ont pas été supprimés.

Après synchronisation des six templates distants, le message local #2 (`hello_world`) a été envoyé par la queue Laravel au numéro de test autorisé. Son statut réel est **delivered**, reçu via le webhook signé de Meta. Il s’agit d’une preuve automatique de livraison, distincte de la confirmation humaine du message #1.

Le template `parent_inscription_confirmee` reste `READY / PENDING` au contrôle du 14 septembre à 00:21 UTC. Son activation et son test restent conditionnés à l’approbation Meta. Le tunnel doit rester actif pour recevoir les prochains événements ; il ne constitue pas un hébergement permanent.

Après connexion : 49 tests / 116 assertions passent de nouveau. Un défaut d’isolation des tests a été corrigé : le cache statique des colonnes Eloquent conservait l’ancien schéma des messages lorsque les deux suites étaient exécutées ensemble. Il est vidé après les migrations du test de lifecycle ; aucun comportement d’envoi en production n’a été assoupli.
