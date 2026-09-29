<?php

declare(strict_types=1);

namespace App\Modules\Communication\Console\Commands;

use App\Modules\Communication\Contracts\NotificationServiceContract;
use Illuminate\Console\Command;

class TraiterFileAttenteNotificationsCommand extends Command
{
    protected $signature = 'notifications:traiter-file';

    protected $description = "Traite les notifications en attente dans la file d'envoi multi-canal";

    public function handle(NotificationServiceContract $notificationService): int
    {
        $this->info("Traitement de la file d'attente des notifications...");

        $notificationService->traiterFileAttente();

        $this->info("File d'attente traitée avec succès.");

        return Command::SUCCESS;
    }
}
