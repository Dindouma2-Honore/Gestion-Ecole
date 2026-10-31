<?php

declare(strict_types=1);

namespace App\Modules\Technique\Console;

use App\Modules\Technique\Services\BackupService;
use Illuminate\Console\Command;
use Throwable;

class BackupDatabase extends Command
{
    protected $signature = 'technique:backup-database';

    protected $description = 'Créer et vérifier une sauvegarde de la base principale';

    public function handle(BackupService $backups): int
    {
        try {
            $backup = $backups->create('Sauvegarde planifiée ou commande artisan');
            $this->info('Sauvegarde créée : '.$backup['file'].' ('.$backup['size_bytes'].' octets).');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Échec de la sauvegarde : '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
