# Développement parallèle Linux / Mac

## Branches et règle de travail

- **develop** : base commune d’intégration. Démarrée depuis l’état de travail actuel, avec les modifications locales sauvegardées.
- **work/linux** : travail courant sur Linux.
- **work/mac** : travail courant sur le Mac.
- **feat/…** ou **fix/…** : branche temporaire recommandée pour un sujet important, créée depuis `origin/develop`.
- **main**, **production** et le remote **plesk** : ne pas pousser pendant le développement courant. Le déploiement Plesk reste une opération distincte et arrêtée tant qu’il n’est pas demandé.

Une machine ne voit les changements de l’autre qu’après commit/push, puis récupération. Une Pull Request vers develop permet de partager une étape terminée. Ne pas travailler simultanément sur une même branche depuis les deux machines. Convenir du module ou de la tâche avant de commencer ; éviter de modifier le même fichier des deux côtés.

## Première installation sur le Mac depuis le paquet de transfert

Le paquet créé avant cette préparation conserve un état Git antérieur et des modifications locales. Elles ont été sauvegardées dans la base commune ; ne pas les réappliquer automatiquement.

Après extraction, dans `~/Projects/ambassadors-app` :

```bash
git status --short
# Seulement si des changements apparaissent : garder une copie locale sans la supprimer.
git stash push -u -m "Sauvegarde du paquet Linux avant branche Mac"
git remote set-url origin git@github-ambassadors:Joelo001/ambassadors-systeme.git
git fetch origin
git switch --track origin/work/mac
```

Si `work/mac` existe déjà localement : `git switch work/mac`, puis `git pull --ff-only origin work/mac`. Si Git signale une divergence, s’arrêter et examiner les commits ; ne pas forcer. Le stash reste une sauvegarde consultable avec `git stash list` / `git stash show`; ne pas faire `stash pop` aveuglément puisque ces modifications sont déjà intégrées.

Le dossier `.transfer` et `.env` sont ignorés et ne sont pas déplacés par `git stash -u`. Ne pas utiliser `git stash -a`, qui inclurait les secrets et dépendances ignorés.

Suivre ensuite le [guide Mac](../migration-mac.md), avec les scripts récupérés depuis GitHub, pour démarrer les conteneurs et importer la base une seule fois. L’alias SSH `github-ambassadors` est défini dans ce guide.

## Début de chaque session

```bash
bash tools/dev/sync.sh status
bash tools/dev/sync.sh sync
```

Le script refuse la synchronisation si des fichiers suivis ou nouveaux ne sont pas enregistrés. Il récupère la branche de la machine et develop, puis les fusionne sans réécrire l’historique. Il ne modifie pas automatiquement la base.

Si les dépendances ou migrations ont changé, utiliser les commandes correspondantes :

```bash
./vendor/bin/sail composer install
./vendor/bin/sail npm ci
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm run build
```

Ne jamais remplacer `migrate` par `migrate:fresh` sur vos données. Ne pas modifier une ancienne migration déjà appliquée : créer une nouvelle migration. Les migrations doivent fonctionner sur les deux environnements.

## Fin d’une étape

```bash
git diff
git status --short
git add chemin/du/fichier chemin/autre/fichier
git commit -m "Décrire précisément le changement"
bash tools/dev/sync.sh publish
```

Puis ouvrir une Pull Request **de work/linux ou work/mac vers develop**, depuis GitHub, ou avec GitHub CLI si installé :

```bash
gh pr create --base develop --head work/mac
# Sur Linux, remplacer work/mac par work/linux.
```

Attendre les contrôles CI et revoir le diff avant fusion. Préférer **Create a merge commit** pour les branches permanentes work/linux et work/mac : l’historique commun reste reconnaissable. Ne pas supprimer ces deux branches après fusion. Refaire `sync` sur les deux machines après intégration.

Pour un travail isolé : `git fetch origin`, puis `git switch -c feat/nom-du-sujet origin/develop`. Même procédure de PR vers develop ; cette branche temporaire peut être supprimée après fusion.

## En cas de conflit

Lire `git status`, ouvrir les fichiers signalés et choisir le contenu correct en conservant les changements nécessaires des deux côtés. Tester, `git add` les fichiers résolus puis `git commit` pour terminer la fusion. Si vous ne savez pas résoudre : conserver le travail et demander de l’aide. `git merge --abort` annule la fusion en cours ; le script exigeait un arbre propre avant de démarrer.

Ne pas employer `git push --force`, `git reset --hard` ou `git clean -fd` pour « se mettre à jour ».

## Ce qui est partagé et ce qui reste local

| Élément | Partage |
| --- | --- |
| Code, tests, migrations, documentation, composer.lock, package-lock.json | GitHub |
| Base et documents locaux | Copie initiale puis évolutions distinctes ; transfert explicite si nécessaire |
| `.env`, APP_KEY, mots de passe, jetons et clés SSH | Hors Git, transfert chiffré |
| `vendor`, `node_modules`, images Docker | Réinstallation locale |
| Données Plesk et configuration production | Restent sur le serveur |

Les nouveaux fichiers téléversés ne passent pas par Git. Ne pas importer un export Linux sur une base Mac déjà enrichie : préparer une fusion de données adaptée. Les versions PHP/MySQL et les dépendances verrouillées doivent rester alignées ; les ports locaux peuvent différer.

## WhatsApp et tâches automatiques

Au départ, Linux est le poste responsable des tests Meta ; le Mac conserve `WHATSAPP_ENABLED=false` et `WHATSAPP_TEMPLATE_SYNC_ENABLED=false`. Ne pas lancer les workers sur les deux copies de la même file restaurée. Une seule URL webhook doit être choisie pour le compte Meta. Le changement de poste responsable se fait explicitement avec arrêt des workers précédents, examen des jobs en attente et vérification du webhook.

## Contrôles GitHub

La CI existante vérifie compilation frontend, migrations MySQL, tests Laravel/Pest et frontières entre modules. Elle est déclenchée sur les Pull Requests vers develop/main/production et sur les pushes develop/main/production/work/**. Un résultat rouge doit être examiné ; le fait que le code soit poussé ne prouve pas que la version est validée.

La protection de develop doit imposer une PR et interdire le push forcé/la suppression. Si le forfait du dépôt privé empêche cette protection, appliquer cette règle manuellement et consulter le compte rendu de préparation. Ne pas contourner un échec CI pour publier sur Plesk.

## Installation du garde-fou local

GitHub a refusé la protection serveur de develop (HTTP 403) : ce dépôt privé nécessite une offre compatible, telle que GitHub Pro. Le dépôt reste privé. Aucune protection serveur ni obligation CI n’a donc été activée. La CI est configurée pour se lancer, mais n’empêche pas techniquement une fusion.

Après avoir récupéré le dernier develop, installer le hook sur chaque machine :

```bash
git config --local core.hooksPath .githooks
```

Si vous utilisez déjà un autre répertoire de hooks, intégrer ce contrôle dans votre configuration au lieu de l’écraser. Le hook refuse les pushes directs vers develop/main/production et vers Plesk ; il autorise vos branches de travail. Il est installé sur le poste Linux. Un hook local peut être contourné : il complète la discipline de travail et ne remplace pas une protection GitHub.

État initial : les trois branches ont été créées depuis le même commit incluant le travail local du 25 septembre. Les ajouts de coordination suivants sont intégrés par PR. Aucun changement de main/production ni déploiement Plesk n’a été effectué pour cette mise en place.
