# Module Logistique

Ce module regroupe les fonctionnalités logistiques de l'établissement.

## Structure

- `Contracts/` : interfaces publiques utilisables par les autres modules.
- `Services/` : logique métier interne implémentant les Contracts.
- `Models/` : modèles Eloquent internes au module.
- `Http/Controllers/` : contrôleurs HTTP du module.
- `Filament/Pages/` : pages Filament propres au module.
- `Filament/Resources/` : Resources Filament propres au module.
- `Filament/Widgets/` : widgets de son tableau de bord.
- `database/migrations/` : migrations appartenant au module.
- `Providers/` : bindings des Contracts et chargement des migrations.
- `Exceptions/` : exceptions métier du module.

## Règle d'architecture

Un autre module ne doit jamais importer les `Models` ou `Services` de
Logistique. Toute intégration extérieure passe exclusivement par une
interface déclarée dans `Contracts/`.
