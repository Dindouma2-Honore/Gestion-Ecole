# Architecture du module Communication

## Objectif

Centraliser les communications de l’établissement : annonces, messages aux parents, notifications, courriers, rendez-vous, portails parent/élève, modèles WhatsApp et suivi des envois.

## Organisation du code

- `Models/` : annonces et accusés de lecture, messages, destinataires, modèles, file d’attente de notification, notifications envoyées, rendez-vous et réponses parent.
- `Services/` : services métier de notification, annonces, portails, courriers, rendez-vous et clients WhatsApp (Cloud API, modèles, médias, statuts et environnement).
- `Contracts/` : ports consommables par les autres modules : notification, communication parent, courriers-notifications, portails, rendez-vous et annonces.
- `Jobs/` : `SendWhatsAppMessage` envoie un message; `SyncWhatsAppTemplatesJob` synchronise un modèle distant. Les envois utilisent la file `notifications`; la synchronisation utilise `whatsapp.templates.queue` (valeur par défaut `whatsapp-templates`). Les messages sont dispatchés après commit dans `WhatsAppDispatcher`.
- `Events`/`Listeners` : le fournisseur écoute les validations d’inscription, publications de bulletins et décisions de candidature pour déclencher le parcours de communication prévu.
- `Http/` et `routes/` : notamment le webhook WhatsApp chargé par `CommunicationServiceProvider`.
- `Filament/` : tableau de bord, test WhatsApp, Resources de messages, annonces, modèles, notifications et rendez-vous; widgets de file et d’alertes de modèles.
- `Console/` : traitement de file, commandes de modèles WhatsApp et commande de test.
- `Providers/CommunicationServiceProvider.php` : bindings, policy des modèles de communication, listeners, vues, routes, migrations et commandes.

## Flux d’envoi

Une page, un événement ou un autre module appelle un contrat ou `WhatsAppDispatcher`. Le service persiste le message de communication, applique les règles d’environnement/idempotence, puis dispatch le job après commit sur la file appropriée. Le job contacte l’API; le webhook reçoit les retours et peut relancer une synchronisation de modèle. Les adaptateurs de domaine écoutent les événements métier afin de ne pas faire dépendre les producteurs de messages de l’implémentation WhatsApp.

Les interfaces de Scolarité, Pédagogie, RH, Finances et Socle sont importées dans certains flux. Le fournisseur lie les interfaces publiques de Communication; les autres modules doivent utiliser ces contrats et les événements publiés, pas ses modèles internes.
