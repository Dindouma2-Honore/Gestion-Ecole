# Comptes du personnel

Dans Administration > Utilisateurs, la liste contient uniquement les comptes liés à un dossier `Employe`. Elle propose Consulter, Modifier et Supprimer. Supprimer le compte conserve le dossier RH. La suppression de son propre compte et du compte Fondateur est désactivée.

Créer un utilisateur sélectionne un personnel actif sans compte. Son identité et son téléphone proviennent du dossier RH. L’adresse de connexion doit être unique. Le rôle et, si nécessaire, le niveau sont choisis sur le formulaire. La création et le rattachement sont transactionnels ; deux créations pour le même personnel sont refusées.

## Invitation WhatsApp

Ce parcours utilise le template `staff_account_access` : nom, identifiant et lien personnel pour définir le mot de passe. Aucun mot de passe en clair n’est conservé. Le lien est généré au moment de l’envoi et expire selon le broker Laravel. Après sa consommation, le changement de mot de passe obligatoire est levé.

Le message est enregistré dans `communication_messages` avec la clé `staff-account:{id}`. La queue `notifications` le traite après validation de la transaction. Le worker vérifie de nouveau le compte, le destinataire et l’approbation récente du template ; les envois ambigus ne sont pas relancés automatiquement.

La notification de création distingue « en attente d’envoi » et « WhatsApp non envoyé ». Une acceptation par Meta ne signifie pas une livraison : consulter le statut du message/webhook. Un message bloqué n’est pas remis automatiquement en file après changement de configuration.

## Configuration nécessaire

- Template approuvé par Meta et actif localement. Synchroniser puis activer depuis Communication, ou avec `php artisan whatsapp:templates sync --template=staff_account_access --actor=ID`, puis `php artisan whatsapp:templates activate --template=staff_account_access --actor=ID`.
- Worker sur la queue `notifications`, synchronisation régulière des templates, configuration WhatsApp valide.
- En mode test, destinataire autorisé dans la liste existante. Le garde-fou actuel de l’intégration refuse l’environnement Laravel `production` ; son évolution reste distincte du travail local.
- `APP_URL` doit être accessible au destinataire pour que le lien signé fonctionne. L’adresse locale `http://localhost:8000` n’est pas utilisable depuis son téléphone.

Au 25 septembre 2026, le modèle a été soumis à Meta et reste `PENDING`. Aucun compte réel n’a été créé pour les vérifications ; les appels WhatsApp des tests sont simulés. Le parcours RH existant d’embauche n’est pas remplacé par ce formulaire.
