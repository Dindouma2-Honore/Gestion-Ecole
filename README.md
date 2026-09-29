# Ambassadors Educational Complex

Application de gestion scolaire modulaire construite avec PHP 8.3+, Laravel
13, Filament 5, MySQL 8.4, Vite et Laravel Sail.

## Documentation essentielle

- [Guide complet du développeur](docs/development/guide-developpeur.md)
- [Socle visuel Filament](docs/ui/filament-design-system.md)
- [Règles de frontières modulaires](deptrac.yaml)

## Démarrage rapide

Après l’installation initiale décrite dans le guide :

```bash
./vendor/bin/sail up -d
./vendor/bin/sail npm install
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail npm run dev
```

Parcours de l’application :

```text
/             Bienvenue
/admin/login  Connexion
/accueil      Accueil authentifié
/admin        Dashboard Filament
```

## Validation obligatoire

```bash
./vendor/bin/sail vendor/bin/pint --dirty
./vendor/bin/sail artisan test
./vendor/bin/sail composer deptrac
./vendor/bin/sail npm run build
```

Aucune Pull Request ne doit être fusionnée si les tests ou Deptrac échouent.
Un module peut utiliser les `Contracts` d’un autre module, jamais ses classes
internes.
