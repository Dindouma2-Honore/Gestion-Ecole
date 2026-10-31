<?php

namespace App\Modules\Communication\Console\Commands;

use App\Modules\Communication\Services\WhatsAppCloudApiClient;
use App\Modules\Communication\Services\WhatsAppDispatcher;
use Illuminate\Console\Command;

class WhatsAppTestCommand extends Command
{
    protected $signature = 'whatsapp:test {recipient?} {--connection} {--simulate} {--pdf}';

    protected $description = 'Test Meta contrôlé : connexion ou template hello_world';

    public function handle(): int
    {
        if ($this->option('connection')) {
            try {
                $ok = app(WhatsAppCloudApiClient::class)->connection();
            } catch (\RuntimeException $e) {
                $this->error('Configuration ou connexion Meta indisponible.');

                return self::FAILURE;
            }
            $this->info($ok ? 'Connexion validée' : 'Connexion refusée');

            return $ok ? self::SUCCESS : self::FAILURE;
        }
        $m = app(WhatsAppDispatcher::class)->dispatch((string) $this->argument('recipient'), ($this->option('pdf') ? ['type' => 'fictitious_pdf'] : ['type' => 'template', 'template' => ['name' => 'hello_world', 'language' => ['code' => 'en_US']]]), simulation: (bool) $this->option('simulate'));
        $this->info('#'.$m->id.' '.$m->status.' '.$m->reason);

        return in_array($m->status, ['queued', 'simulated']) ? self::SUCCESS : self::FAILURE;
    }
}
