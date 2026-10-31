# WhatsApp : phase TEST META

Implémentation exclusivement test, désactivée par défaut et interdite en environnement production. Aucun secret n’est fourni. Les tests automatisés utilisent Http::fake et SQLite en mémoire.

## Mise en service

Utiliser PHP 8.3+ (conteneur Sail du projet). Appliquer les migrations avec `php artisan migrate` après revue sur la base cible. Configurer hors Git les variables WHATSAPP de `.env.example` : token Meta, phone ID du numéro de test, WABA ID, version Graph active dans votre application Meta, expéditeur, app secret et verify token. `WHATSAPP_ENABLED=true`, `WHATSAPP_MODE=test`. La liste `WHATSAPP_TEST_RECIPIENTS` contient uniquement les numéros ajoutés et vérifiés dans la console Meta, au format international avec indicatif. Aucun numéro métier n’est automatiquement autorisé.

Attribuer séparément les permissions `whatsapp.view`, `whatsapp.connect`, `whatsapp.send`, `whatsapp.simulate` aux opérateurs autorisés. La page Communication → WhatsApp Test Meta vérifie les permissions côté serveur. La CLI est réservée aux opérateurs ayant accès au serveur.

```sh
php artisan whatsapp:test --connection
php artisan whatsapp:test +2250700000000 --simulate
php artisan whatsapp:test +2250700000000
php artisan queue:work database --queue=notifications --tries=3 --timeout=60
php artisan whatsapp:test +2250700000000 --pdf
```

Le PDF est créé dans le worker, contient exclusivement du texte fictif et est envoyé comme média privé. Le destinataire doit avoir ouvert la fenêtre de service WhatsApp (répondre au message de test) avant un envoi document libre. Aucun bulletin réel ni URL publique n’est utilisé.

## Garde-fous et exploitation

Les envois réels sont limités au template Meta `hello_world` en anglais et au PDF fictif. Les 19 codes ERP proposés sont initialisés LOCAL_ONLY avec des contenus inactifs à rédiger dans le Socle ; aucune approbation Meta n’est supposée. L’audit ne fournissait pas leurs noms : la liste initiale est dans `Socle/Contracts/WhatsAppTestTemplateCatalog.php`.

La destination prévue, la destination effective, le mode test, le phone ID et le statut sont conservés dans `communication_messages`. Le payload est chiffré avec APP_KEY. Ne pas supprimer cette clé. Les réponses enregistrées ne contiennent que l’identifiant Meta. Les statuts accepted/sent/delivered/read sont distincts. Un blocage ou une simulation n’est jamais un succès d’envoi.

L’override est utilisé uniquement pour une simulation explicite en local/testing. Il ne redirige jamais un envoi réel. Le worker recontrôle activation, mode, expéditeur et allowlist. La prise en charge atomique évite deux appels concurrents. Un résultat réseau ambigu devient unknown, sans retry automatique : vérifier côté Meta avant toute nouvelle tentative. Un worker interrompu en sending nécessite également une vérification manuelle. Les retries du job couvrent les erreurs avant la prise en charge, jamais un POST potentiellement accepté. Le timeout worker doit rester inférieur au retry_after de la connexion.

Les transitions d’inscription validee/active produisent une simulation après commit, dédupliquée par inscription. La publication d’un bulletin produit une trace bloquée sans destinataire pendant cette phase. L’ancien chemin WhatsApp échoue explicitement ; il ne simule plus de succès. Aucun MessageParent n’est créé par les outils de test.

## Webhook et recette

Exposer GET/POST `/webhooks/whatsapp` en HTTPS via staging ou tunnel de développement. Renseigner l’URL et le verify token dans Meta, souscrire aux statuts messages. POST exige HMAC SHA-256 du corps brut avec APP_SECRET, puis vérifie WABA et phone ID. Les doublons et régressions de statut sont ignorés. Les statuts signés arrivés avant la réponse HTTP sont conservés puis rapprochés dès l’enregistrement de l’identifiant fournisseur.

Vérifier connexion, hello_world, réponse du destinataire, PDF fictif, signatures invalides, progression delivered/read et changement de mode avant traitement. Aucun appel réel n’est effectué par l’installation ou les migrations. Les credentials et l’URL HTTPS restent nécessaires pour la recette Meta réelle.

Références : [exemples officiels Meta](https://github.com/fbsamples/whatsapp-api-examples), [API officielle Meta](https://www.postman.com/meta/whatsapp-business-platform/documentation/wlk6lh4/whatsapp-cloud-api).

## Validation locale

33 tests ciblés passent (95 assertions) : Communication, inscription et calcul des bulletins. Deptrac conserve 7 violations préexistantes en Assiduité/Pédagogie ; aucune ne concerne les nouveaux contrats ou services WhatsApp. Lors de cette validation initiale, les migrations avaient été exécutées uniquement sur SQLite en mémoire.


## Préparation locale du 13 septembre 2026

Les trois migrations WhatsApp ont été appliquées avec succès sur la base MySQL locale `ambassadors_local` via Sail : tables, quatre permissions et catalogue local. Les routes de la page Filament et du webhook GET/POST sont enregistrées. Les tests ciblés Communication ont été relancés : **19 tests, 47 assertions**, tous réussis avec appels HTTP simulés.

Le `.env` local contient les identifiants du numéro de test et du compte WABA de la capture, l’expéditeur et un verify token généré. `WHATSAPP_ENABLED=false` est conservé pendant la préparation. Aucun message réel n’a été envoyé.

Restent à renseigner localement : `WHATSAPP_TOKEN`, `WHATSAPP_APP_SECRET`, `WHATSAPP_GRAPH_VERSION` et `WHATSAPP_TEST_RECIPIENTS`. Le token de la capture est tronqué. Il faut également définir l’URL HTTPS publique se terminant par `/webhooks/whatsapp`, configurer sa vérification et sa souscription dans Meta, puis attribuer les permissions aux opérateurs choisis.

Après configuration, vider le cache avec `./vendor/bin/sail artisan config:clear`, vérifier la connexion, activer le mode test et démarrer le worker `notifications` pour la recette décrite plus haut. Le worker d’envoi n’a pas été démarré pendant cette préparation.

## Gestion des templates personnalisés

Le document ci-dessus conserve l’historique de la préparation initiale. Le cycle de vie complet des templates personnalisés est désormais décrit dans [Gestion des templates Meta](modules/communication-whatsapp-templates.md) : création, validation, soumission, synchronisation, activation et tests. Le mode test et le contrôle des destinataires restent obligatoires.
