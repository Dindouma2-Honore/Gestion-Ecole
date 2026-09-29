<?php

declare(strict_types=1);

namespace App\Modules\Socle\Services;

use App\Models\User;
use App\Modules\Socle\Contracts\DocumentServiceContract;
use App\Modules\Socle\Exceptions\TailleFichierDepasseeException;
use App\Modules\Socle\Exceptions\TypeFichierNonAutoriseException;
use App\Modules\Socle\Models\Document;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class DocumentService implements DocumentServiceContract
{
    private const TYPES_AUTORISES = ['pdf', 'jpg', 'jpeg', 'png', 'docx', 'xlsx'];

    private const TAILLE_MAX_KO = 5120; // 5 Mo

    private const NIVEAUX_CONFIDENTIALITE = ['public', 'interne', 'restreint'];

    public function attacher(
        Model $documentable,
        UploadedFile $fichier,
        string $categorie,
        string $niveauConfidentialite = 'interne',
        ?DateTimeInterface $dateExpiration = null
    ): object {
        $this->validerFichier($fichier);
        $this->validerContexte($documentable, $categorie, $niveauConfidentialite);
        $auteurId = $this->auteurConnecteId();
        $path = Storage::disk('documents')->putFile('', $fichier);

        try {
            return Document::create([
                'nom' => $fichier->getClientOriginalName(),
                'categorie' => $categorie,
                'fichier_path' => $path,
                'mime_type' => $fichier->getMimeType() ?? 'application/octet-stream',
                'taille' => $fichier->getSize(),
                'documentable_type' => $documentable->getMorphClass(),
                'documentable_id' => $documentable->getKey(),
                'date_expiration' => $dateExpiration,
                'niveau_confidentialite' => $niveauConfidentialite,
                'created_by' => $auteurId,
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('documents')->delete($path);

            throw $exception;
        }
    }

    public function nouvelleVersion(int $documentId, UploadedFile $fichier): object
    {
        $this->validerFichier($fichier);
        $auteurId = $this->auteurConnecteId();
        $path = Storage::disk('documents')->putFile('', $fichier);

        try {
            return DB::transaction(function () use ($documentId, $path, $auteurId): object {
                $document = Document::query()->lockForUpdate()->findOrFail($documentId);
                $dernierNumero = (int) ($document->versions()->max('version_numero') ?? 0);

                return $document->versions()->create([
                    'fichier_path' => $path,
                    'version_numero' => $dernierNumero + 1,
                    'created_by' => $auteurId,
                ]);
            });
        } catch (\Throwable $exception) {
            Storage::disk('documents')->delete($path);

            throw $exception;
        }
    }

    public function attacherContenu(
        Model $documentable,
        string $nom,
        string $contenu,
        string $mimeType,
        string $categorie,
        string $niveauConfidentialite = 'interne',
    ): object {
        $this->validerContexte($documentable, $categorie, $niveauConfidentialite);
        if ($contenu === '') {
            throw ValidationException::withMessages(['contenu' => 'Le document généré ne peut pas être vide.']);
        }

        $auteurId = $this->auteurConnecteId();
        $extension = $mimeType === 'application/pdf' ? 'pdf' : 'bin';
        $path = 'generes/'.now()->format('Y/m').'/'.str()->uuid().'.'.$extension;
        Storage::disk('documents')->put($path, $contenu);

        try {
            return Document::create([
                'nom' => trim($nom),
                'categorie' => $categorie,
                'fichier_path' => $path,
                'mime_type' => $mimeType,
                'taille' => strlen($contenu),
                'documentable_type' => $documentable->getMorphClass(),
                'documentable_id' => $documentable->getKey(),
                'niveau_confidentialite' => $niveauConfidentialite,
                'created_by' => $auteurId,
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('documents')->delete($path);

            throw $exception;
        }
    }

    public function peutAcceder(User $user, object $document): bool
    {
        return match ($document->niveau_confidentialite) {
            'public' => true,
            'interne' => (bool) $user->exists,
            'restreint' => $user->hasAnyRole(['Fondateur', 'Directeur', 'Admin', 'Administrateur']),
            default => false,
        };
    }

    public function getUrlTelechargement(?int $documentId): ?string
    {
        if ($documentId === null || ! Document::query()->whereKey($documentId)->exists()) {
            return null;
        }

        return URL::temporarySignedRoute(
            'documents.telecharger',
            now()->addMinutes(30),
            ['documentId' => $documentId],
        );
    }

    public function telecharger(User $user, int $documentId): StreamedResponse
    {
        $document = Document::query()->findOrFail($documentId);
        if (! $this->peutAcceder($user, $document)) {
            throw new AccessDeniedHttpException("Vous n'êtes pas autorisé à télécharger ce document.");
        }

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('documents');

        return $disk->download(
            $document->fichier_path,
            $document->nom,
            ['Content-Type' => $document->mime_type],
        );
    }

    public function getDocumentsExpirantBientot(int $joursAvant): Collection
    {
        if ($joursAvant < 0) {
            throw ValidationException::withMessages([
                'joursAvant' => 'Le délai d’alerte ne peut pas être négatif.',
            ]);
        }

        return Document::whereNotNull('date_expiration')
            ->whereDate('date_expiration', '<=', now()->addDays($joursAvant))
            ->whereDate('date_expiration', '>=', now())
            ->get();
    }

    private function validerFichier(UploadedFile $fichier): void
    {
        $extension = strtolower($fichier->getClientOriginalExtension());

        if (! in_array($extension, self::TYPES_AUTORISES, true)) {
            throw new TypeFichierNonAutoriseException($extension, self::TYPES_AUTORISES);
        }

        if ($fichier->getSize() > (self::TAILLE_MAX_KO * 1024)) {
            throw new TailleFichierDepasseeException(self::TAILLE_MAX_KO);
        }
    }

    private function validerContexte(Model $documentable, string $categorie, string $niveauConfidentialite): void
    {
        if (! $documentable->exists || $documentable->getKey() === null) {
            throw ValidationException::withMessages([
                'documentable' => 'Le document doit être rattaché à un enregistrement existant.',
            ]);
        }

        if (trim($categorie) === '' || mb_strlen($categorie) > 50) {
            throw ValidationException::withMessages([
                'categorie' => 'La catégorie est obligatoire et limitée à 50 caractères.',
            ]);
        }

        if (! in_array($niveauConfidentialite, self::NIVEAUX_CONFIDENTIALITE, true)) {
            throw ValidationException::withMessages([
                'niveauConfidentialite' => 'Le niveau de confidentialité est invalide.',
            ]);
        }
    }

    private function auteurConnecteId(): int
    {
        $id = Auth::id();

        if ($id === null) {
            throw new AccessDeniedHttpException('Une authentification est requise pour gérer un document.');
        }

        return (int) $id;
    }
}
