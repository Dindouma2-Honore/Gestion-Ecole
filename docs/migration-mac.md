# Transférer Ambassadors ERP de Linux vers le MacBook Pro 2019

Ce guide accompagne l’archive chiffrée préparée le 25 septembre 2026. Aucun déploiement Plesk ni envoi WhatsApp n’est effectué par les scripts de transfert.

## 1. Ce qui est transféré

- Le projet courant, y compris les modifications RH/CSS non publiées, les fichiers cachés et l’historique `.git`.
- La base MySQL locale, exportée logiquement : on ne copie pas un volume Docker brut.
- Les documents sous `storage/app`, les assets déjà présents, `.env` et surtout la même `APP_KEY` pour pouvoir déchiffrer les données.
- La clé SSH privée et publique existante, sous le nom distinct `id_ed25519_ambassadors`, ainsi que les empreintes de serveurs connues.
- Les scripts de démarrage/import Mac et le script Plesk existant `.plesk/deploy.sh`.

`vendor`, `node_modules` et les images Docker seront reconstruits. Les worktrees secondaires, sessions, logs et caches ne sont pas transférés. Il ne s’agit pas d’une copie des autres projets de cette machine ni d’une sauvegarde de la base Plesk. Les connexions navigateur GitHub/Meta/Plesk devront être réouvertes ; les accès par clé SSH sont transférés.

## 2. Copier le paquet

Sur Linux, les fichiers se trouvent dans le dossier caché `.transfer` du projet (Ctrl+H dans le gestionnaire de fichiers).

Copier `ambassadors-mac-DATE.tar` et son fichier `.sha256` sur une clé USB ou un support de transfert. Garder le fichier `.password.txt` séparé ; il contient la phrase secrète de déchiffrement. Ne pas publier ces fichiers sur GitHub. L’archive contient la clé SSH privée, le jeton Meta, les mots de passe et les données scolaires.

Copier ce guide sur le Mac avant de commencer. Sur Linux, un nouvel export peut être créé avec `bash tools/migration/export-mac.sh` lorsque le conteneur `ambassadors-app-mysql-1` est démarré.

## 3. Préparer le Mac

Le MacBook Pro 2019 utilise une puce Intel. Installer Docker Desktop **pour Intel** et l’ouvrir. Vérifier d’abord la version de macOS dans  → À propos de ce Mac : Docker prend en charge la version actuelle et les deux précédentes. Si votre macOS est plus ancien, vérifier sa compatibilité avant installation ; ne pas choisir une version au hasard.

Documentation : https://docs.docker.com/desktop/setup/install/mac-install/

Dans Terminal :

```bash
xcode-select --install
```

Cela installe notamment Git. Installer Homebrew depuis https://brew.sh si absent, puis OpenSSL :

```bash
brew install openssl@3
```

Aucune installation locale de PHP, MySQL ou Node n’est nécessaire : Sail les fournit dans Docker. Une connexion Internet est nécessaire au premier démarrage pour télécharger les images et les dépendances.

## 4. Extraire et déchiffrer

Le paquet `.tar` contient trois parties chiffrées et le guide. Placer les fichiers dans `~/Downloads/Transfert-Ambassadors`, puis remplacer DATE par le nom exact reçu :

```bash
cd ~/Downloads/Transfert-Ambassadors
shasum -a 256 -c ambassadors-mac-DATE.sha256
mkdir -p "$HOME/Transfert-Ambassadors"
chmod 700 "$HOME/Transfert-Ambassadors"
tar -xf ambassadors-mac-DATE.tar -C "$HOME/Transfert-Ambassadors"
cd "$HOME/Transfert-Ambassadors"
shasum -a 256 -c SHA256SUMS
umask 077
for part in project.tar.gz database.sql ssh.tar.gz; do
  "$(brew --prefix openssl@3)/bin/openssl" enc -d -aes-256-cbc -pbkdf2 -iter 200000 -md sha256 \
    -in "$part.enc" -out "$part" || break
done
```

Entrer le même contenu du fichier `.password.txt` à chacune des trois demandes. Le Terminal ne l’affiche pas. S’arrêter en cas d’erreur de checksum/déchiffrement. Le paquet est vérifié avant départ ; le mot de passe doit voyager séparément.

Le dossier `~/Projects/ambassadors-app` doit être neuf/vide : ne pas extraire sur un autre projet existant.

```bash
mkdir -p "$HOME/Projects/ambassadors-app"
tar -xzf project.tar.gz -C "$HOME/Projects/ambassadors-app"
tar -xzf ssh.tar.gz -C ssh
cd "$HOME/Projects/ambassadors-app"
chmod 600 .env
git status --short
git log -1 --oneline
```

L’état Git doit retrouver vos modifications locales. Ne pas générer une nouvelle `APP_KEY`.

## 5. Démarrer l’environnement local et importer les données

Dans le dossier du projet :

```bash
bash tools/migration/start-mac.sh
bash tools/migration/import-database-mac.sh "$HOME/Transfert-Ambassadors/database.sql"
```

Le premier script installe les dépendances verrouillées, démarre Sail et compile les assets. Il adapte `.env` pour `http://localhost:8080` et MySQL interne `mysql:3306` (port Mac 3307). Le deuxième refuse d’importer si la base contient déjà des tables ; il importe la sauvegarde, applique les migrations manquantes et recrée le lien des documents. Ne pas exécuter de seeder de démonstration sur les données restaurées.

Ouvrir **http://localhost:8080/admin/login** et utiliser votre compte habituel. Vérifier : connexion, fiches élèves, pièces jointes, habilitations et catalogue WhatsApp.

Les emails locaux sont dirigés vers les logs ; WhatsApp et sa synchronisation périodique sont désactivés au premier démarrage. Aucun worker/scheduler n’est démarré automatiquement. Les jobs historiques sont présents dans la base restaurée : les contrôler avant toute reprise de worker.

Commandes habituelles :

```bash
./vendor/bin/sail up -d
./vendor/bin/sail stop
./vendor/bin/sail artisan migrate:status
./vendor/bin/sail artisan test
./vendor/bin/sail npm run build
```

Ne pas utiliser `sail down -v` : cette option supprime les volumes, donc la base locale.

## 6. Rétablir SSH, Git et GitHub

Copier la clé sous son nom dédié pour préserver les clés déjà présentes sur le Mac. Si le fichier cible existe déjà, comparer les clés avant de continuer.

```bash
mkdir -p "$HOME/.ssh"
chmod 700 "$HOME/.ssh"
cp -n "$HOME/Transfert-Ambassadors/ssh/id_ed25519" "$HOME/.ssh/id_ed25519_ambassadors"
cp -n "$HOME/Transfert-Ambassadors/ssh/id_ed25519.pub" "$HOME/.ssh/id_ed25519_ambassadors.pub"
chmod 600 "$HOME/.ssh/id_ed25519_ambassadors"
chmod 644 "$HOME/.ssh/id_ed25519_ambassadors.pub"
cat "$HOME/Transfert-Ambassadors/ssh/known_hosts" >> "$HOME/.ssh/known_hosts"
```

Ajouter une seule fois le contenu de `ssh/config.fragment` à `~/.ssh/config` (ne pas remplacer une configuration existante). Puis :

```bash
chmod 600 "$HOME/.ssh/config" "$HOME/.ssh/known_hosts"
ssh-add "$HOME/.ssh/id_ed25519_ambassadors"
ssh -T github-ambassadors
ssh plesk-ambassadors 'pwd'
```

GitHub indique normalement une authentification réussie mais aucun shell, avec un code retour 1. Si le serveur présente une empreinte différente de celle connue, vérifier avant acceptation.

Dans le projet :

```bash
git remote set-url origin git@github-ambassadors:Joelo001/ambassadors-systeme.git
git fetch origin
git status --short
git branch -vv
```

Vérifier `git config user.name` et `git config user.email`, puis les définir si nécessaire avec votre nom/adresse GitHub. Pour publier, créer ou utiliser une branche de travail, examiner `git diff`, ajouter seulement les fichiers voulus, committer puis pousser cette branche. Ne jamais ajouter `.env`, la sauvegarde SQL ou la clé privée.

Le transfert n’exige pas de changer la clé sur GitHub/Plesk : il s’agit de la même identité SSH autorisée. Une nouvelle clé dédiée au Mac pourra être ajoutée ultérieurement si souhaité.

## 7. Repères de déploiement Plesk

Accès vérifié lors de la préparation précédente :

- SSH : `scholl-ambassadors@crm.ambassadors-aec.com` (alias `plesk-ambassadors`).
- Projet ERP : `/var/www/vhosts/scholl-ambassadors.com/ambassadors-systemem.ambassadors-aec.com`.
- Site : `https://ambassadors-systemem.ambassadors-aec.com`.
- PHP : `/opt/plesk/php/8.3/bin/php`.
- Composer : `/usr/lib/plesk-9.0/composer.phar`.
- Node : `/opt/plesk/node/22/bin`.

Le compte héberge plusieurs applications. **Le dossier `httpdocs` et le dossier `crm.ambassadors-aec.com` ne sont pas cet ERP.** La publication avait été arrêtée à votre demande ; le transfert ne la reprend pas.

Procédure de déploiement à suivre séparément :

1. Choisir un commit Git précis, valider tests et compilation, vérifier la branche distante et les migrations attendues.
2. Se connecter par SSH et sauvegarder le code actuel, les fichiers et la base de production dans un emplacement privé, hors du site. Préparer le retour arrière et une fenêtre de maintenance si nécessaire.
3. Transférer une archive issue de `git archive COMMIT` vers un répertoire de préparation privé. Ne pas transférer le `.env` local, le SQL local, les clés SSH, `node_modules` ou les caches locaux. Préserver le `.env`, l’APP_KEY et les documents du serveur.
4. Après vérification du contenu et de la sauvegarde, mettre à jour le code de l’ERP avec la version préparée. Le dossier serveur n’était pas un checkout Git lors du contrôle : ne pas supposer qu’un `git pull` y fonctionne.
5. Depuis le dossier exact de l’ERP, exécuter `bash .plesk/deploy.sh`. Ce script installe Composer sans dépendances de développement, compile les assets, exécute les migrations et reconstruit les caches. Lire les migrations avant cette étape : le titre « non destructives » du script n’est pas une garantie automatique.
6. Vérifier le site, la connexion, les documents et les écrans modifiés ; redémarrer uniquement les workers concernés si présents. En cas d’échec, utiliser la sauvegarde et le plan de retour arrière ; ne pas lancer un rollback de données aveugle.

La clé SSH est suffisante pour l’accès serveur autorisé, mais ne remplace pas le mot de passe/MFA de l’interface web Plesk. Aucune connexion web n’est exportée.

## 8. WhatsApp après transfert

Les templates approuvés/en attente restent chez Meta ; ils ne sont pas attachés à l’ordinateur. `.env` contient les identifiants de connexion, mais le jeton peut avoir expiré ou été révoqué. Réactiver les envois seulement après vérification du compte, du numéro de test et des jobs en attente.

Le jeton utilisateur système vérifié le 14 septembre avait une expiration annoncée au 13 novembre 2026 à 09:16 UTC ; ne pas le considérer sans expiration. Un nouveau tunnel Cloudflare aura une nouvelle URL, à enregistrer chez Meta avant d’attendre des accusés webhook sur le Mac. Ne pas utiliser l’ancienne adresse temporaire comme si elle était permanente.

Après validation du Mac, conserver une sauvegarde chiffrée et retirer les copies de transfert déchiffrées lorsqu’elles ne sont plus nécessaires. Conserver l’ordinateur Linux jusqu’à validation complète du transfert.
