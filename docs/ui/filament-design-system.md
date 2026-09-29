# Socle visuel Filament — Ambassadors

Ce document est la référence obligatoire des interfaces d’administration.
Chaque développeur conserve ses Resources et Pages dans son propre module,
mais utilise ce thème et ces conventions communes.

## Parcours commun

1. `/` : écran institutionnel de bienvenue.
2. `/admin/login` : authentification sécurisée.
3. `/accueil` : accueil institutionnel réservé aux utilisateurs connectés.
4. `/admin` : tableau de bord adapté aux droits de l’utilisateur.

Le thème est chargé par le `AdminPanelProvider` central. Un développeur ne
copie donc jamais `theme.css` dans son module : toute Resource Filament en
bénéficie automatiquement.

## Identité

- Bleu nuit `#07163f` : navigation et surfaces institutionnelles.
- Bleu royal `#1948bd` : action principale et sélection.
- Or `#d7aa35` : accent, attention et éléments de marque.
- Les couleurs de workflow restent sémantiques : vert succès, orange attente,
  rouge blocage, gris archive.
- Le logo officiel est `public/images/logo.png`. Toutes les interfaces doivent
  réutiliser ce fichier et ne pas embarquer une copie propre à un module.
- La photographie institutionnelle commune est `public/images/campus.jpg`.

## Structure d’une Resource

1. Titre Filament clair et sous-titre expliquant l’objectif métier.
2. Deux à quatre statistiques utiles, jamais décoratives.
3. Filtres avant multiplication des colonnes.
4. Tableau avec recherche, tri et badges de statut communs.
5. Actions métier nommées explicitement; aucune modification directe d’un
   statut soumis à un workflow.
6. État vide expliquant la première action attendue.

`AnneeScolaireResource` et son widget `AnneeScolaireOverview` constituent
l’implémentation de référence.

## Modèle de dashboard

- Une Page de dashboard reste dans le périmètre de son module.
- Ses indicateurs sont des widgets `StatsOverviewWidget` du module.
- Elle réutilise les composants Filament pour les tableaux, filtres, badges,
  pagination et actions afin de recevoir automatiquement le thème commun.
- Elle n’importe jamais un Model ou un Service d’un autre module. Pour une
  donnée externe, elle passe obligatoirement par le Contract public du module.
- `App\Filament\Pages\Dashboard` et `AdministrationOverview` forment le modèle
  global minimal que chaque développeur peut décliner dans son périmètre.

## Navigation

Utiliser exactement un groupe déclaré dans
`App\Filament\Support\AmbassadorsDesign::NAVIGATION_GROUPS` :

- Socle & Administration
- Scolarité
- Pédagogie
- Ressources humaines
- Finances
- Assiduité

Le `AdminPanelProvider` central est le seul fichier autorisé à ordonner ces
groupes. Un module ne crée pas son propre PanelProvider.

## Badges de statut

Utiliser les libellés et couleurs de `AmbassadorsDesign` :

```php
TextColumn::make('statut')
    ->badge()
    ->formatStateUsing(
        fn (string $state) => AmbassadorsDesign::statutLabels()[$state] ?? ucfirst($state)
    )
    ->color(fn (string $state) => AmbassadorsDesign::statutColor($state));
```

Ajouter un nouveau statut partagé dans cette classe centrale plutôt que de
réinventer une couleur dans chaque Resource.

## Responsive et accessibilité

- Les libellés ne reposent jamais uniquement sur une couleur.
- Les actions destructives exigent une confirmation.
- Les vues doivent rester utilisables à 360 px de largeur.
- Conserver les composants Filament pour le clavier, les erreurs et les
  lecteurs d’écran; le thème personnalise leur apparence, pas leur logique.
