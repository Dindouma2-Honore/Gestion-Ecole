# Architecture du module Technique

## Objectif

Fournir aux profils habilités une vue d’administration et de supervision technique : état du worker, mesures, bases de données, intégrations, sécurité, tâches planifiées, journaux et sauvegardes.

## Organisation du code

- `Models/TechnicalMeasurement` stocke les mesures exposées par le module.
- `Services/MonitoringService` collecte les informations; `TechnicalAccess` porte les contrôles d’accès techniques.
- `Contracts/MonitoringServiceContract` est la surface de service du module.
- `Filament/Pages/` contient le tableau de bord technique et les pages dédiées aux bases, intégrations, tâches planifiées, sauvegardes, sécurité et journaux.
- `Jobs/WorkerHeartbeat` est un job Laravel unique et mis en queue qui signale le fonctionnement d’un worker. Aucun dispatch de ce job n’a été trouvé dans le module; le scheduler ou le déploiement doit le déclencher pour que la mesure soit alimentée.
- `Console/MonitorTechnique` fournit la commande de supervision.
- `Services/BackupService` crée des copies cohérentes SQLite/MySQL dans `storage/app/private/technical-backups`, conserve une empreinte SHA-256 et n’autorise la restauration que depuis ces copies vers la même base. Une restauration prend d’abord un point de retour automatique.
- `Console/BackupDatabase` expose `artisan technique:backup-database`; le scheduler le lance chaque jour à 02:00. Les utilisateurs doivent configurer le scheduler Laravel du déploiement.
- `Providers/TechniqueServiceProvider.php` lie le contrat, charge migrations/vues et enregistre la commande.

## Communication

Technique consomme le contrat d’habilitation du Socle afin de limiter l’accès aux opérations d’administration. Le job heartbeat et la commande sont des points d’observation; ils ne remplacent pas le scheduler/worker Laravel configuré au niveau du déploiement. Le code de sauvegarde ou d’intégration affiché par les pages doit être lu avec les implémentations présentes et les permissions actives.

Les sauvegardes couvrent la base principale uniquement; elles ne sont ni chiffrées ni répliquées hors du serveur. Les binaires `mysqldump`/`mysql` ou `sqlite3` doivent être disponibles dans l’environnement d’exécution. Accorder `technique.backup.restore` uniquement aux personnes habilitées à remplacer les données actives.
