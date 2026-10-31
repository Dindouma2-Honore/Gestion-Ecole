<?php

declare(strict_types=1);

namespace App\Modules\Technique\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

final class BackupService
{
    private const DISK = 'local';

    private const DIRECTORY = 'technical-backups';

    public function list(): array
    {
        $disk = Storage::disk(self::DISK);
        $backups = [];

        foreach ($disk->files(self::DIRECTORY) as $path) {
            if (! str_ends_with($path, '.backup')) {
                continue;
            }

            $metadataPath = $path.'.json';
            if (! $disk->exists($metadataPath)) {
                continue;
            }

            try {
                $metadata = json_decode($disk->get($metadataPath), true, flags: JSON_THROW_ON_ERROR);
                if (! is_array($metadata) || ! $this->isVerified($path, $metadata)) {
                    continue;
                }

                $backups[] = [
                    'file' => basename($path),
                    'created_at' => $metadata['created_at'],
                    'size' => $metadata['size_bytes'],
                    'driver' => $metadata['driver'],
                    'checksum' => $metadata['sha256'],
                ];
            } catch (Throwable) {
                continue;
            }
        }

        usort($backups, fn (array $a, array $b): int => strcmp($b['created_at'], $a['created_at']));

        return $backups;
    }

    public function create(?string $reason = null): array
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        if (! in_array($driver, ['mysql', 'sqlite'], true)) {
            throw new RuntimeException("Les sauvegardes ne prennent pas encore en charge le pilote {$driver}.");
        }

        $disk = Storage::disk(self::DISK);
        $disk->makeDirectory(self::DIRECTORY);
        $stamp = Carbon::now()->format('Ymd-His');
        $token = bin2hex(random_bytes(4));
        $filename = "db-{$stamp}-{$token}.backup";
        $absolutePath = $disk->path(self::DIRECTORY.'/'.$filename);
        $partialPath = $absolutePath.'.partial';

        try {
            if ($driver === 'mysql') {
                $this->backupMysql($connection->getConfig(), $partialPath);
            } else {
                $this->backupSqlite($connection->getConfig('database'), $partialPath);
            }

            if (! is_file($partialPath) || filesize($partialPath) === 0) {
                throw new RuntimeException('Le fichier de sauvegarde est vide.');
            }

            if (! rename($partialPath, $absolutePath)) {
                throw new RuntimeException('Impossible de finaliser le fichier de sauvegarde.');
            }

            $metadata = [
                'format' => 1,
                'driver' => $driver,
                'database' => $this->databaseIdentity($connection->getConfig()),
                'created_at' => now()->toIso8601String(),
                'created_by' => auth()->id(),
                'reason' => $reason,
                'size_bytes' => filesize($absolutePath),
                'sha256' => hash_file('sha256', $absolutePath),
            ];

            $disk->put(self::DIRECTORY.'/'.$filename.'.json', json_encode($metadata, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            $this->pruneOldBackups($filename);

            return ['file' => $filename, ...$metadata];
        } catch (Throwable $exception) {
            @unlink($partialPath);
            @unlink($absolutePath);
            throw $exception;
        }
    }

    public function restore(string $filename): array
    {
        if (basename($filename) !== $filename || ! preg_match('/^db-[0-9]{8}-[0-9]{6}-[a-f0-9]{8}\.backup$/', $filename)) {
            throw new RuntimeException('Nom de sauvegarde invalide.');
        }

        $disk = Storage::disk(self::DISK);
        $path = self::DIRECTORY.'/'.$filename;
        $metadataPath = $path.'.json';
        if (! $disk->exists($path) || ! $disk->exists($metadataPath)) {
            throw new RuntimeException('Cette sauvegarde est introuvable.');
        }

        $metadata = json_decode($disk->get($metadataPath), true, flags: JSON_THROW_ON_ERROR);
        $connection = DB::connection();
        if (! $this->isVerified($path, $metadata, verifyHash: true)
            || $metadata['driver'] !== $connection->getDriverName()
            || $metadata['database'] !== $this->databaseIdentity($connection->getConfig())) {
            throw new RuntimeException('La sauvegarde est invalide ou ne correspond pas à cette base.');
        }

        // Always take a fresh recovery point before replacing live data.
        $safetyBackup = $this->create('Point de retour automatique avant restauration');
        $backupPath = $disk->path($path);

        try {
            if ($connection->getDriverName() === 'mysql') {
                $this->restoreMysql($connection->getConfig(), $backupPath);
            } else {
                $this->restoreSqlite($connection->getConfig('database'), $backupPath);
            }
        } catch (Throwable $exception) {
            throw new RuntimeException('La restauration a échoué. Le point de retour est conservé sous '.$safetyBackup['file'].'. '.$exception->getMessage(), previous: $exception);
        }

        return $safetyBackup;
    }

    private function isVerified(string $path, array $metadata, bool $verifyHash = false): bool
    {
        $disk = Storage::disk(self::DISK);

        $valid = isset($metadata['sha256'], $metadata['size_bytes'], $metadata['driver'], $metadata['database'], $metadata['created_at'])
            && in_array($metadata['driver'], ['mysql', 'sqlite'], true)
            && $disk->size($path) === (int) $metadata['size_bytes'];

        return $valid && (! $verifyHash || hash_equals((string) $metadata['sha256'], (string) hash_file('sha256', $disk->path($path))));
    }

    private function pruneOldBackups(string $keepFilename): void
    {
        $retention = max(1, (int) config('technique.retention_count', 30));
        $backups = $this->list();
        if (count($backups) <= $retention) {
            return;
        }

        $disk = Storage::disk(self::DISK);
        foreach (array_slice($backups, $retention) as $backup) {
            if ($backup['file'] === $keepFilename) {
                continue;
            }

            $disk->delete(self::DIRECTORY.'/'.$backup['file']);
            $disk->delete(self::DIRECTORY.'/'.$backup['file'].'.json');
        }
    }

    private function backupMysql(array $config, string $output): void
    {
        $defaults = $this->mysqlDefaultsFile($config);
        try {
            $process = new Process([
                config('technique.mysqldump_binary', 'mysqldump'),
                '--defaults-extra-file='.$defaults,
                '--single-transaction', '--quick', '--routines', '--triggers', '--hex-blob',
                '--databases', $config['database'], '--result-file='.$output,
            ]);
            $process->setTimeout((float) config('technique.process_timeout', 3600));
            $process->mustRun();
        } finally {
            @unlink($defaults);
        }
    }

    private function restoreMysql(array $config, string $input): void
    {
        $defaults = $this->mysqlDefaultsFile($config);
        try {
            $stream = fopen($input, 'rb');
            if ($stream === false) {
                throw new RuntimeException('Impossible de lire la sauvegarde.');
            }

            try {
                $process = new Process([
                    config('technique.mysql_binary', 'mysql'),
                    '--defaults-extra-file='.$defaults,
                    '--database='.$config['database'],
                ]);
                $process->setInput($stream);
                $process->setTimeout((float) config('technique.process_timeout', 3600));
                $process->mustRun();
            } finally {
                fclose($stream);
            }
        } finally {
            @unlink($defaults);
        }
    }

    private function mysqlDefaultsFile(array $config): string
    {
        $path = tempnam(sys_get_temp_dir(), 'gestion-ecole-db-');
        if ($path === false) {
            throw new RuntimeException('Impossible de créer la configuration temporaire du client MySQL.');
        }

        $lines = ['[client]', 'user='.$this->mysqlOption((string) ($config['username'] ?? '')), 'password='.$this->mysqlOption((string) ($config['password'] ?? ''))];
        if (filled($config['host'] ?? null)) {
            $lines[] = 'host='.$this->mysqlOption((string) $config['host']);
        }
        if (filled($config['port'] ?? null)) {
            $lines[] = 'port='.(int) $config['port'];
        }
        if (filled($config['unix_socket'] ?? null)) {
            $lines[] = 'socket='.$this->mysqlOption((string) $config['unix_socket']);
        }

        file_put_contents($path, implode("\n", $lines)."\n");
        chmod($path, 0600);

        return $path;
    }

    private function mysqlOption(string $value): string
    {
        return '"'.str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '\\n', '\\r'], $value).'"';
    }

    private function backupSqlite(string $database, string $output): void
    {
        if (! is_file($database)) {
            throw new RuntimeException('Le fichier SQLite configuré est introuvable.');
        }

        $process = new Process([config('technique.sqlite_binary', 'sqlite3'), $database, '.backup '.escapeshellarg($output)]);
        $process->setTimeout((float) config('technique.process_timeout', 3600));
        $process->mustRun();
    }

    private function restoreSqlite(string $database, string $input): void
    {
        $process = new Process([config('technique.sqlite_binary', 'sqlite3'), $database, '.restore '.escapeshellarg($input)]);
        $process->setTimeout((float) config('technique.process_timeout', 3600));
        $process->mustRun();
        DB::purge();
    }

    private function databaseIdentity(array $config): string
    {
        return match ($config['driver'] ?? null) {
            'mysql' => implode('@', [$config['database'], $config['host'] ?? '', $config['port'] ?? '']),
            'sqlite' => realpath($config['database']) ?: $config['database'],
            default => (string) ($config['database'] ?? ''),
        };
    }
}
