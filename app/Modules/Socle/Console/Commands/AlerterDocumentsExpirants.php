<?php

declare(strict_types=1);

namespace App\Modules\Socle\Console\Commands;

use App\Modules\Socle\Contracts\DocumentServiceContract;
use App\Modules\Socle\Contracts\ResponsableNotificationDocumentContract;
use App\Modules\Socle\Notifications\DocumentExpirationProche;
use Illuminate\Console\Command;

class AlerterDocumentsExpirants extends Command
{
    protected $signature = 'documents:alerter-expiration';

    protected $description = 'Alerte les responsables sur les documents dont la date d\'expiration approche';

    public function handle(DocumentServiceContract $documentService): void
    {
        $documents = $documentService->getDocumentsExpirantBientot(joursAvant: 30);

        $this->info("Trouvé {$documents->count()} document(s) expirant bientôt.");

        foreach ($documents as $document) {
            $proprietaire = $document->documentable;
            $destinataire = $proprietaire instanceof ResponsableNotificationDocumentContract
                ? $proprietaire->responsableNotification()
                : $document->creator;

            if ($destinataire === null) {
                $this->warn("Aucun destinataire pour le document {$document->id}.");

                continue;
            }

            $destinataire->notify(new DocumentExpirationProche($document));
            $this->line("- Alerte envoyée pour {$document->nom} ({$document->date_expiration?->format('Y-m-d')})");
        }
    }
}
