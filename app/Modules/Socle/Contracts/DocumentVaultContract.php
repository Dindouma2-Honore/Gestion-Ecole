<?php

declare(strict_types=1);

namespace App\Modules\Socle\Contracts;

use App\Modules\Socle\Data\DocumentRefDTO;
use App\Modules\Socle\Data\DocumentUploadData;

interface DocumentVaultContract
{
    public function store(DocumentUploadData $upload, int $actorUserId): DocumentRefDTO;

    public function document(int $id): ?DocumentRefDTO;

    public function createVersion(int $id, DocumentUploadData $upload, int $actorUserId): DocumentRefDTO;

    public function versionsOf(int $id): array;

    public function temporaryDownloadUrl(int $userId, int $id): ?string;
}
