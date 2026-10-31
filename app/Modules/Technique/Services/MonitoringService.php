<?php

declare(strict_types=1);

namespace App\Modules\Technique\Services;

use App\Modules\Technique\Contracts\MonitoringServiceContract;
use App\Modules\Technique\Models\TechnicalMeasurement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class MonitoringService implements MonitoringServiceContract
{
    public function collect(): void
    {
        $start = hrtime(true);
        try {
            DB::connection()->select('SELECT 1');
            $this->record('database.primary', 'AVAILABLE', ['latency_ms' => round((hrtime(true) - $start) / 1e6, 2)]);
        } catch (Throwable) {
            // Never persist a connection exception: it can contain credentials or SQL.
            $this->record('database.primary', 'FAILED');
        }
        foreach (config('technique.storage_disks', []) as $disk) {
            try {
                if (config("filesystems.disks.{$disk}.driver") !== 'local') {
                    $this->record("storage.{$disk}", 'UNKNOWN');

                    continue;
                }
                $path = Storage::disk($disk)->path('');
                $available = is_dir($path) && is_readable($path) && is_writable($path);
                $free = $available ? @disk_free_space($path) : false;
                $this->record("storage.{$disk}", $available ? 'AVAILABLE' : 'FAILED', $free === false ? [] : ['free_bytes' => (int) $free]);
            } catch (Throwable) {
                $this->record("storage.{$disk}", 'FAILED');
            }
        }
    }

    public function heartbeat(string $component): void
    {
        if (! in_array($component, ['scheduler', 'queue.worker'], true)) {
            throw new \InvalidArgumentException('Composant non autorisé.');
        }
        $this->record($component, 'AVAILABLE');
    }

    private function record(string $component, string $status, array $metrics = []): void
    {
        TechnicalMeasurement::query()->create([
            'component' => $component, 'status' => $status, 'metrics' => $metrics, 'measured_at' => now(),
        ]);
    }

    public function snapshot(): array
    {
        $components = ['database.primary', ...array_map(fn ($disk) => "storage.{$disk}", config('technique.storage_disks', [])), 'scheduler', 'queue.worker'];
        $result = [];
        foreach ($components as $component) {
            try {
                $measurement = TechnicalMeasurement::query()->where('component', $component)->latest('id')->first();
                $result[$component] = [
                    'status' => $measurement?->effectiveStatus() ?? 'UNKNOWN',
                    'measured_at' => $measurement?->measured_at?->toIso8601String(),
                    'metrics' => $measurement?->metrics ?? [],
                ];
            } catch (Throwable) {
                $result[$component] = ['status' => 'UNKNOWN', 'measured_at' => null, 'metrics' => []];
            }
        }

        return $result;
    }
}
