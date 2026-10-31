<?php

declare(strict_types=1);

namespace App\Modules\Socle\Services;

use App\Models\User;
use App\Modules\Socle\Contracts\DocumentVaultContract;
use App\Modules\Socle\Data\DocumentRefDTO;
use App\Modules\Socle\Data\DocumentUploadData;
use App\Modules\Socle\Models\Document;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

final class DocumentVaultService implements DocumentVaultContract
{
    public function store(DocumentUploadData $u, int $actor): DocumentRefDTO
    {
        $path = 'vault/'.str()->uuid();
        Storage::disk('documents')->put($path, file_get_contents($u->temporaryPath));
        $d = Document::create(['nom' => $u->originalName, 'categorie' => $u->category, 'fichier_path' => $path, 'mime_type' => $u->mimeType, 'taille' => $u->size, 'documentable_type' => $u->ownerType, 'documentable_id' => $u->ownerId, 'niveau_confidentialite' => $u->confidentialityLevel ?: 'restreint', 'date_expiration' => $u->expiresAt, 'created_by' => $actor, 'sha256' => hash_file('sha256', $u->temporaryPath)]);

        return $this->ref($d);
    }

    public function document(int $id): ?DocumentRefDTO
    {
        $d = Document::find($id);

        return $d ? $this->ref($d) : null;
    }

    public function createVersion(int $id, DocumentUploadData $u, int $actor): DocumentRefDTO
    {
        $d = Document::findOrFail($id);
        $path = 'vault/'.str()->uuid();
        Storage::disk('documents')->put($path, file_get_contents($u->temporaryPath));
        $v = $d->versions()->create(['fichier_path' => $path, 'version_numero' => (int) $d->versions()->max('version_numero') + 1, 'created_by' => $actor, 'sha256' => hash_file('sha256', $u->temporaryPath)]);

        return $this->ref($d, $v->version_numero);
    }

    public function versionsOf(int $id): array
    {
        return Document::findOrFail($id)->versions->map(fn ($v) => $this->ref($v->document, $v->version_numero))->all();
    }

    public function temporaryDownloadUrl(int $userId, int $id): ?string
    {
        $d = Document::find($id);
        $u = User::find($userId);
        if (! $d || ! $u || ! app(DocumentService::class)->peutAcceder($u, $d)) {
            return null;
        }

return URL::temporarySignedRoute('documents.telecharger', now()->addMinutes(30), ['documentId' => $id]);
    }

    private function ref(Document $d, int $v = 0): DocumentRefDTO
    {
        return new DocumentRefDTO($d->id, $d->nom, $d->categorie, $d->mime_type, $d->taille, $d->niveau_confidentialite, $d->documentable_type, $d->documentable_id, $v, $d->created_at->toImmutable(), $d->date_expiration?->toImmutable(), $d->sha256);
    }
}
