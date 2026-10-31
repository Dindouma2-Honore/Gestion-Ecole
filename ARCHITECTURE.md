# Architecture du projet Ambassadors

## Rôle du projet

Ambassadors est une application web de gestion d’établissement scolaire. Le dépôt contient une application Laravel organisée en modules métier, une interface d’administration Filament, les migrations des données, les vues et les ressources front compilées avec Vite. La pile déclarée dans le projet est PHP 8.3+, Laravel 13, Filament 5 et MySQL 8.4.

## Démarrage de l’application

- `bootstrap/providers.php` enregistre les fournisseurs Laravel de l’application et des dix modules.
- `app/Providers/Filament/AdminPanelProvider.php` configure le panneau d’administration et découvre les Resources, Pages et Widgets des modules.
- `app/Providers/Filament/JoelPanelProvider.php` configure le panneau Filament distinct destiné à l’espace Joel.
- Le fournisseur de chaque module lie ses interfaces aux services concrets, charge généralement ses migrations et vues, et peut enregistrer policies, événements ou commandes.

Une Resource Filament présente et valide les formulaires; elle appelle normalement un service pour les règles métier. Les Pages et Widgets assemblent les vues de synthèse. Les vues Blade se trouvent dans `resources/views` du module et sont généralement exposées sous un espace de noms tel que `pedagogie::` ou `socle::`.

## Organisation interne d’un module

```text
app/Modules/<Module>/
├── Contracts/          interfaces publiques et événements d’intégration
├── Models/             modèles Eloquent possédés par le module
├── Services/           règles et opérations métier
├── Filament/           Resources, Pages, Widgets et support UI
├── Http/               contrôleurs, middleware et requêtes HTTP
├── Jobs/               travaux différés Laravel, quand le module en a
├── database/migrations/ schéma et changements de données du module
└── resources/views/    vues Blade et documents du module
```

Les dossiers varient selon les besoins : `Policies`, `Listeners`, `Events`, `Observers`, `Console`, `Mail`, `Notifications`, `Exceptions`, `Enums` et `Support` sont présents dans certains modules seulement.

## Communication entre modules

La règle d’architecture documentée dans `deptrac.yaml` est qu’un module consomme les interfaces `Contracts` d’un autre module, sans importer ses Models ou Services internes. Le fournisseur du module propriétaire lie l’interface à l’implémentation dans le conteneur Laravel; le module appelant reçoit l’interface par injection de dépendances. Les événements métier sont un autre point de communication : par exemple, Communication écoute des événements d’inscription, de publication de bulletin et de candidature.

`deptrac.yaml` contient aussi des exceptions explicites (`skip_violations`) pour certains imports internes historiques. La règle cible est donc claire, mais toute nouvelle intégration doit vérifier la configuration et les violations existantes plutôt que supposer que chaque frontière est déjà parfaite.

## Propriétaires des données et flux principaux

| Domaine | Module propriétaire | Échanges principaux |
|---|---|---|
| Comptes, habilitations, audit, années/périodes, documents et workflows | Socle | Services transverses consommés par les autres modules |
| Élèves, parents, classes, inscriptions et frais scolaires de base | Scolarité | Classes/élèves consommés par Pédagogie; inscriptions et facturation échangent avec Finances |
| Personnel et enseignants | RH | Référentiel des enseignants consommé par Pédagogie; données de paie et de personnel |
| Matières, offres, affectations, programmes, emploi du temps, séances, notes et bulletins | Pédagogie | Consomme classes, enseignants, années, audit et infrastructure par contrats |
| Paiements, caisse, budgets, factures et recouvrement | Finances | Consomme notamment des contrats de Scolarité et du Socle |
| Messages, notifications, annonces, portails et WhatsApp | Communication | Réagit à des événements métier et expose ses contrats de notification |
| Bibliothèque, cantine, transport, salles, équipements et événements | Logistique | Services d’infrastructure et contrats transverses |
| Incidents, santé scolaire, visiteurs, sorties et calendrier institutionnel | VieScolaire | Consomme parents/tuteurs et fonctions documentaires/audit |
| Pointages, présence quotidienne et moteur d’assiduité | Assiduité | S’appuie sur Scolarité, Socle et calendrier de VieScolaire |
| Supervision technique | Technique | Présente l’état de l’application et des opérations d’arrière-plan |

Les noms de fonctionnalités proches dans VieScolaire et Assiduité doivent être interprétés à partir de leurs contrats, tables et fournisseurs respectifs : ne déplacez pas une responsabilité de module par simple similarité de nom.

## Files d’attente et traitements différés

Laravel exécute les classes `Jobs` quand elles implémentent les contrats de queue et qu’elles sont dispatchées par un service, un listener ou une commande. Communication envoie des messages WhatsApp après commit et synchronise des modèles de message; Pédagogie traite et analyse des documents de programme; Technique expose un heartbeat de worker. Les autres modules n’ont pas de dossier `Jobs` à la date de cette documentation. Consulter `config/queue.php`, les jobs concernés et les appels `dispatch()` avant de conclure qu’un traitement est asynchrone.

## Documentation des modules

- [Assiduité](app/Modules/Assiduite/ARCHITECTURE.md)
- [Communication](app/Modules/Communication/ARCHITECTURE.md)
- [Finances](app/Modules/Finances/ARCHITECTURE.md)
- [Logistique](app/Modules/Logistique/ARCHITECTURE.md)
- [Pédagogie](app/Modules/Pedagogie/ARCHITECTURE.md)
- [Ressources humaines](app/Modules/RH/ARCHITECTURE.md)
- [Scolarité](app/Modules/Scolarite/ARCHITECTURE.md)
- [Socle](app/Modules/Socle/ARCHITECTURE.md)
- [Technique](app/Modules/Technique/ARCHITECTURE.md)
- [Vie scolaire](app/Modules/VieScolaire/ARCHITECTURE.md)

## Repères de développement

- Lire `docs/development/guide-developpeur.md` pour démarrer le projet et son workflow.
- Respecter `deptrac.yaml` et placer les migrations dans le module propriétaire.
- Le thème Filament est configuré au niveau des fournisseurs de panneaux; ne créez pas un panneau par module.
- Les tests, Pint, Deptrac et la compilation Vite sont décrits dans le README racine et le guide développeur.

Cette documentation décrit la structure observée dans le dépôt. Les fichiers de code, contrats liés dans les fournisseurs et migrations restent la référence en cas de divergence.
