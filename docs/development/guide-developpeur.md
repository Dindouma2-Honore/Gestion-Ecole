# Guide développeur — Ambassadors

Ce document explique comment récupérer, démarrer et modifier Ambassadors sans
enfreindre les frontières entre modules.

## 1. Environnement requis

- Git.
- Docker Engine ou Docker Desktop avec Docker Compose v2.
- Au moins 4 Go de mémoire disponibles pour Docker.
- Ports `80`, `3306` et `5173` libres, ou ports personnalisés dans `.env`.
- Accès au dépôt GitHub `Joelo001/ambassadors-systeme`.

PHP, Composer, Node et MySQL sont exécutés dans Laravel Sail. Il n’est donc pas
nécessaire de les installer directement sur le poste. Le projet exige PHP 8.3
minimum et utilise Laravel 13.17, Filament 5.7, MySQL 8.4 et Vite 8.

## 2. Premier clonage

```bash
git clone git@github.com:Joelo001/ambassadors-systeme.git
cd ambassadors-systeme
git switch main
git pull --ff-only origin main
```

Si l’accès SSH GitHub n’est pas encore configuré :

```bash
ssh-keygen -t ed25519 -C "votre-email@example.com"
cat ~/.ssh/id_ed25519.pub
```

Ajouter la clé publique dans GitHub, rubrique **Settings > SSH and GPG keys**,
puis tester :

```bash
ssh -T git@github.com
```

Ne jamais partager ni envoyer la clé privée `~/.ssh/id_ed25519`.

## 3. Installation initiale

Après un clonage, `vendor/` n’existe pas encore. Installer d’abord les
dépendances Composer au moyen de l’image officielle Laravel :

```bash
docker run --rm \
  -u "$(id -u):$(id -g)" \
  -v "$PWD:/var/www/html" \
  -w /var/www/html \
  laravelsail/php83-composer:latest \
  composer install --ignore-platform-reqs
```

Créer ensuite le fichier local d’environnement :

```bash
cp .env.example .env
```

Configurer au minimum ces valeurs dans `.env` :

```dotenv
APP_NAME="Ambassadors Educational Complex"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=ambassadors
DB_USERNAME=sail
DB_PASSWORD=password

APP_PORT=80
FORWARD_DB_PORT=3306
```

Si les ports sont déjà occupés, utiliser par exemple :

```dotenv
APP_URL=http://localhost:8080
APP_PORT=8080
FORWARD_DB_PORT=3307
```

Le port `DB_PORT` reste toujours `3306`, car il correspond au port interne du
conteneur MySQL. Seul `FORWARD_DB_PORT` change sur le poste.

Ne jamais ajouter `.env` à Git et ne jamais y placer un secret destiné à être
partagé.

Démarrer et initialiser l’application :

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail npm install
./vendor/bin/sail npm run build
```

Vérifier les conteneurs :

```bash
./vendor/bin/sail ps
```

## 4. Utilisation quotidienne

Démarrer :

```bash
./vendor/bin/sail up -d
```

Développer avec recompilation automatique du CSS et du JavaScript :

```bash
./vendor/bin/sail npm run dev
```

Arrêter les conteneurs sans supprimer les données MySQL :

```bash
./vendor/bin/sail down
```

Ne jamais exécuter `sail down -v`, car `-v` supprime le volume et donc la base
locale. Ne jamais utiliser `migrate:fresh` sur une base partagée ou de
production.

Parcours local :

- `/` : bienvenue ;
- `/admin/login` : connexion ;
- `/accueil` : accueil après connexion ;
- `/admin` : tableau de bord Filament.

Le compte créé par le seeder local est `admin@ambassadors.com` avec le mot de
passe initial `password`. Il est strictement réservé au développement.

## 5. Récupérer les dernières modifications

Avant de commencer une nouvelle tâche :

```bash
git switch main
git pull --ff-only origin main
git switch -c devX/nom-court-de-la-tache
```

Exemples :

```text
devA/gestion-eleves
devB/gestion-personnel
devC/pointage-biometrique
```

Pour remettre à jour une branche existante sans réécrire son historique :

```bash
git switch devX/nom-court-de-la-tache
git fetch origin
git merge origin/main
```

Résoudre les conflits localement, tester, puis créer un commit normal. Ne
jamais faire de force-push.

Après un `git pull`, si les dépendances ont changé :

```bash
./vendor/bin/sail composer install
./vendor/bin/sail npm install
./vendor/bin/sail artisan migrate
```

## 6. Architecture modulaire obligatoire

Le code métier se trouve dans `app/Modules/<Module>/` :

```text
Contracts/             interfaces publiques du module
Services/              logique métier interne
Models/                modèles Eloquent internes
Http/                  contrôleurs et requêtes
Filament/              Resources, Pages et Widgets
database/migrations/   migrations propres au module
Providers/             bindings et chargement des migrations
Exceptions/            exceptions métier
```

Règle absolue : un module ne peut utiliser que les `Contracts` d’un autre
module. Il ne peut jamais importer ses `Models`, `Services`, `Http`,
`Filament`, `Providers` ou autres classes internes.

Interdit :

```php
use App\Modules\Scolarite\Models\Eleve;
```

depuis un fichier du module Finances.

Autorisé :

```php
use App\Modules\Scolarite\Contracts\EleveServiceInterface;
```

Le module propriétaire définit l’interface dans `Contracts`, son
implémentation dans `Services`, puis lie les deux dans son Provider. Le module
consommateur reçoit uniquement l’interface par injection de dépendances.

Ne jamais modifier `deptrac.yaml` pour faire passer une violation. Si un
Contract manque, le signaler et le concevoir avec le responsable du module.

## 7. Travailler dans son module

Un développeur modifie uniquement le module qui lui est attribué. Une
migration métier doit aller dans :

```text
app/Modules/<Module>/database/migrations/
```

Une Resource Filament doit aller dans :

```text
app/Modules/<Module>/Filament/Resources/
```

Un Widget ou une Page de dashboard du module doit aller dans :

```text
app/Modules/<Module>/Filament/Widgets/
app/Modules/<Module>/Filament/Pages/
```

Le développeur ne crée pas un nouveau PanelProvider et ne change pas l’ordre
global de navigation sans coordination avec le responsable du Socle.

## 8. Utiliser le socle visuel

Le thème commun est chargé automatiquement par
`app/Providers/Filament/AdminPanelProvider.php`. Une Resource Filament située
dans un module reçoit donc automatiquement :

- la barre latérale bleu nuit ;
- les boutons bleu royal ;
- les accents dorés ;
- le style partagé des tableaux, filtres, cartes et badges ;
- le comportement responsive.

Il ne faut jamais copier `resources/css/filament/admin/theme.css` dans un
module ni créer des couleurs locales concurrentes.

Chaque écran métier suit ce patron :

1. titre et sous-titre expliquant l’objectif ;
2. deux à quatre statistiques réellement utiles ;
3. filtres et recherche ;
4. tableau Filament ;
5. badges de statut partagés ;
6. actions métier explicites ;
7. état vide compréhensible.

Exemple de page avec sous-titre et statistiques :

```php
use App\Modules\MonModule\Filament\Widgets\MonOverview;
use Illuminate\Contracts\Support\Htmlable;

public function getSubheading(): string|Htmlable|null
{
    return 'Suivez et gérez les éléments du module.';
}

protected function getHeaderWidgets(): array
{
    return [MonOverview::class];
}
```

Exemple de widget :

```php
namespace App\Modules\MonModule\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MonOverview extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        return [
            Stat::make('Total', 42)
                ->description('Éléments enregistrés')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('primary'),
        ];
    }
}
```

Pour les statuts partagés, utiliser
`App\Filament\Support\AmbassadorsDesign` :

```php
use App\Filament\Support\AmbassadorsDesign;
use Filament\Tables\Columns\TextColumn;

TextColumn::make('statut')
    ->badge()
    ->formatStateUsing(
        fn (string $state): string =>
            AmbassadorsDesign::statutLabels()[$state] ?? ucfirst($state)
    )
    ->color(fn (string $state): string =>
        AmbassadorsDesign::statutColor($state)
    );
```

Une nouvelle couleur ou un nouveau groupe global doit être ajouté au socle
visuel central après validation, pas directement dans chaque module. Le guide
visuel détaillé se trouve dans `docs/ui/filament-design-system.md`.

## 9. Commandes de qualité obligatoires

Avant chaque commit :

```bash
./vendor/bin/sail vendor/bin/pint --dirty
./vendor/bin/sail artisan test
./vendor/bin/sail composer deptrac
./vendor/bin/sail npm run build
```

Résultat obligatoire : aucun test en échec et aucune violation Deptrac.

Pour exécuter un test ciblé pendant le développement :

```bash
./vendor/bin/sail artisan test --filter=NomDuTest
```

## 10. Commit, push et Pull Request

Inspecter les changements :

```bash
git status
git diff --check
git diff
```

Ne jamais ajouter `.env`, une clé API, un mot de passe, un export SQL, un
fichier de stockage utilisateur ou un token.

Créer un commit clair :

```bash
git add app/Modules/MonModule tests
git commit -m "Implement student enrollment workflow"
git push -u origin devX/nom-court-de-la-tache
```

Créer ensuite une Pull Request vers `main`. La fusion est interdite tant que
la CI GitHub n’est pas verte. La CI exécute les migrations, les tests et
Deptrac sous PHP 8.3.

La Pull Request doit préciser :

- le besoin couvert ;
- le module modifié ;
- les migrations ajoutées ;
- les tests ajoutés ou modifiés ;
- les captures des écrans Filament ;
- les éventuels Contracts utilisés entre modules.

Ne jamais pousser directement sur `main`, ne jamais faire de force-push et ne
jamais fusionner son propre travail si la revue ou la CI n’est pas terminée.

## 11. Problèmes courants

### `docker-compose: command not found`

Utiliser Docker Compose v2 :

```bash
docker compose version
```

Sail utilise normalement cette commande automatiquement.

### `address already in use` sur 80 ou 3306

Changer `APP_PORT` ou `FORWARD_DB_PORT` dans `.env`, puis :

```bash
./vendor/bin/sail down
./vendor/bin/sail up -d
```

### `Access denied for user sail`

Vérifier que les valeurs `DB_DATABASE`, `DB_USERNAME` et `DB_PASSWORD` sont
cohérentes. Une base déjà créée conserve ses anciens identifiants dans le
volume. Ne pas supprimer ce volume sans autorisation ; demander au responsable
du projet comment conserver ou réinitialiser les données locales.

### `Sail is not running`

```bash
docker info
./vendor/bin/sail up -d
./vendor/bin/sail ps
```

### Permissions sur Docker

Ne pas lancer quotidiennement Sail avec `sudo`. Faire configurer correctement
l’accès Docker pour son utilisateur par l’administrateur du poste.

## 12. Liste de contrôle avant livraison

- La modification reste dans le module attribué.
- Aucun import interne entre modules.
- Les migrations sont dans le module propriétaire.
- Les autorisations Filament sont contrôlées.
- Le socle visuel commun est réutilisé.
- Les écrans sont utilisables sur mobile.
- Les tests métier sont présents.
- Pint, tests, Deptrac et build passent.
- Aucun secret ni `.env` n’est suivi par Git.
- La branche est à jour avec `main`.
- La Pull Request décrit clairement le changement.
