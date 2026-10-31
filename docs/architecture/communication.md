# Communication

## Objectif

Communication centralise les messages parents/personnel, annonces, rendez-vous, notifications, portails et échanges WhatsApp.

## Code

- **Modèles** : `CommunicationMessage`, `MessageParent`, `MessageDestinataire`, `CommunicationTemplate`, `CommunicationTemplateEvent`, `NotificationEnvoyee`, `FileAttenteNotification`, `Annonce`, `RendezVous`, réponses et historiques.
- **Services** : annonces, communication parent, notification, rendez-vous, portails, courrier/notification, résolution et validation des templates WhatsApp, client Cloud API et dispatcher.
- **Jobs** : `SendWhatsAppMessage` et `SyncWhatsAppTemplatesJob`.
- **Filament** : messages, modèles, annonces, rendez-vous et notifications.

## Communications

Scolarité fournit les parents et destinataires d’inscription. RH fournit les comptes du personnel. Pédagogie émet la publication d’un bulletin. Le Socle gère les templates et permissions communes. Les jobs isolent l’appel API WhatsApp et conservent les statuts de livraison.

## Sécurité

Les tokens et secrets API restent dans l’environnement, jamais dans les modèles, migrations ou fichiers de documentation. Les templates Meta sont synchronisés puis validés avant envoi.
