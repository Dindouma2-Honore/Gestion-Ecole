<?php

declare(strict_types=1);

namespace App\Modules\Socle\Http\Controllers;

use App\Modules\Socle\Contracts\DocumentServiceContract;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadDocumentController
{
    public function __invoke(Request $request, int $documentId, DocumentServiceContract $documents): StreamedResponse
    {
        return $documents->telecharger($request->user(), $documentId);
    }
}
