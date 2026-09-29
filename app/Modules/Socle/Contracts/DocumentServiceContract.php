<?php

declare(strict_types=1);

namespace App\Modules\Socle\Contracts;

use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

interface DocumentServiceContract
{
    /** Attache un document à n'importe quel modèle métier. */
    public function attacher(
        Model $documentable,
        UploadedFile $fichier,
        string $categorie,
        string $niveauConfidentialite = 'interne',
        ?DateTimeInterface $dateExpiration = null
    ): object;

    /**
     * Remplace un document existant en créant une NOUVELLE version.
     */
    public function nouvelleVersion(int $documentId, UploadedFile $fichier): object;

    /** Stocke durablement un document généré par l'application. */
    public function attacherContenu(
        Model $documentable,
        string $nom,
        string $contenu,
        string $mimeType,
        string $categorie,
        string $niveauConfidentialite = 'interne',
    ): object;

    /** Vérifie si l'utilisateur peut accéder à ce document selon son niveau de confidentialité */
    public function peutAcceder(User $user, object $document): bool;

    /** URL signée et temporaire vers un document privé. */
    public function getUrlTelechargement(?int $documentId): ?string;

    /** Télécharge un document après contrôle de confidentialité. */
    public function telecharger(User $user, int $documentId): StreamedResponse;

    /** Retourne les documents dont la date d'expiration approche */
    public function getDocumentsExpirantBientot(int $joursAvant): Collection;
}
