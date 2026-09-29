# A5 — Utiliser la gestion documentaire

Le Socle fournit `DocumentServiceContract`. Les autres modules ne doivent
jamais importer `Document`, `DocumentVersion` ou `DocumentService`, qui sont
des classes internes au Socle.

## Relation polymorphique

Un même service peut rattacher un fichier à tout modèle Eloquent déjà
enregistré. Par exemple, si le module Scolarité passe son modèle Élève au
Contract, le Socle mémorise le type morphologique du modèle et son identifiant.
Il n’est donc pas nécessaire de créer une table différente pour les documents
des élèves, des employés ou des fournisseurs.

```php
use App\Modules\Socle\Contracts\DocumentServiceContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

final class MonService
{
    public function __construct(private DocumentServiceContract $documents) {}

    public function joindre(Model $proprietaire, UploadedFile $fichier): object
    {
        return $this->documents->attacher(
            documentable: $proprietaire,
            fichier: $fichier,
            categorie: 'piece_identite',
            niveauConfidentialite: 'restreint',
            dateExpiration: now()->addYear(),
        );
    }
}
```

L’appel doit être effectué pour un utilisateur authentifié et pour un modèle
déjà sauvegardé. Les extensions admises sont PDF, JPG/JPEG, PNG, DOCX et XLSX,
avec une taille maximale de 5 Mo.

## Nouvelle version

```php
$version = $this->documents->nouvelleVersion($documentId, $fichier);
```

Le fichier d’origine n’est jamais écrasé. Les versions reçoivent un numéro
croissant protégé par transaction et contrainte unique.

## Confidentialité

- `public` : accessible à tous selon le Contract ;
- `interne` : accessible à un utilisateur enregistré ;
- `restreint` : réservé aux rôles Fondateur et Directeur.

Dans Filament, la liste masque automatiquement les documents restreints aux
autres rôles. La Resource est volontairement en lecture seule : les créations
et versions passent par le Contract afin de conserver validation, traçabilité
et sécurité du stockage.

## Alertes d’expiration

La commande `documents:alerter-expiration` est planifiée chaque jour à 07:00.
Le modèle propriétaire peut implémenter
`ResponsableNotificationDocumentContract` pour désigner le destinataire. À
défaut, le créateur du document reçoit l’alerte email.

En production, le cron Laravel doit exécuter `php artisan schedule:run` chaque
minute. Le disque est privé et se configure avec `DOCUMENTS_DRIVER`; aucun
fichier documentaire ne doit être placé directement dans `public/`.

Le pilote local est opérationnel immédiatement. Avant de choisir `s3`, ajouter
l’adaptateur `league/flysystem-aws-s3-v3` aux dépendances Composer et renseigner
les variables AWS uniquement sur le serveur, jamais dans Git.
