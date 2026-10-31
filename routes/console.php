<?php

use App\Modules\Communication\Jobs\SyncWhatsAppTemplatesJob;
use App\Modules\Technique\Contracts\MonitoringServiceContract;
use App\Modules\Technique\Jobs\WorkerHeartbeat;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('documents:alerter-expiration')->dailyAt('07:00');
Schedule::command('taches:relancer')->daily();
Schedule::command('recouvrement:relancer')->weekly();

Schedule::command('technique:monitor')->everyMinute()->withoutOverlapping(2);
Schedule::command('technique:backup-database')->dailyAt('02:00')->withoutOverlapping(120);
Schedule::call(function (): void {
    app(MonitoringServiceContract::class)->heartbeat('scheduler');
    if (! in_array(config('queue.default'), ['sync', 'null'], true)) {
        WorkerHeartbeat::dispatch()->onQueue(config('technique.queue'));
    }
})->name('technique:heartbeat')->everyMinute()->withoutOverlapping(2);

Schedule::job(new SyncWhatsAppTemplatesJob, config('whatsapp.templates.queue', 'whatsapp-templates'))
    ->everyThirtyMinutes()
    ->when(fn () => config('whatsapp.enabled') && config('whatsapp.templates.sync_enabled'))
    ->withoutOverlapping();
