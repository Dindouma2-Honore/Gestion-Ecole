<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Resources\DocumentResource\Pages;

use App\Models\User;
use App\Modules\Socle\Filament\Resources\DocumentResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class CreateDocument extends CreateRecord
{
    protected static string $resource = DocumentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::id() ?? 1;

        $filePath = $data['fichier_path'] ?? '';
        $fullPath = Storage::disk('public')->path($filePath);

        $data['mime_type'] = file_exists($fullPath) ? (mime_content_type($fullPath) ?: 'application/octet-stream') : 'application/octet-stream';
        $data['taille'] = file_exists($fullPath) ? filesize($fullPath) : 0;
        $data['documentable_type'] = User::class;
        $data['documentable_id'] = Auth::id() ?? 1;

        return $data;
    }
}
