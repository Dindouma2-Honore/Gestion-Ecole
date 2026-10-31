<?php

declare(strict_types=1);

namespace App\Modules\Technique\Filament\Pages;

use App\Modules\Technique\Services\BackupService;
use App\Modules\Technique\Services\TechnicalAccess;
use Filament\Notifications\Notification;
use Throwable;

class Backups extends TechnicalPage
{
    protected static ?string $title = 'Sauvegardes & restauration';

    protected static ?string $navigationLabel = 'Sauvegardes & restauration';

    protected static ?string $slug = 'technique/sauvegardes';

    protected static ?int $navigationSort = 3;

    public function createBackup(): void
    {
        abort_unless(TechnicalAccess::allows(auth()->user(), 'technique.backup.create'), 403);

        try {
            $backup = app(BackupService::class)->create('Sauvegarde manuelle');
            Notification::make()->title('Sauvegarde créée et vérifiée')->body($backup['file'])->success()->send();
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()->title('Échec de la sauvegarde')->body($exception->getMessage())->danger()->persistent()->send();
        }
    }

    public function restoreBackup(string $filename): void
    {
        abort_unless(TechnicalAccess::allows(auth()->user(), 'technique.backup.restore'), 403);

        try {
            $safetyBackup = app(BackupService::class)->restore($filename);
            Notification::make()
                ->title('Restauration terminée')
                ->body('Point de retour créé avant restauration : '.$safetyBackup['file'])
                ->success()->persistent()->send();
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()->title('Échec de la restauration')->body($exception->getMessage())->danger()->persistent()->send();
        }
    }

    public function backups(): array
    {
        abort_unless(static::canAccess(), 403);

        return app(BackupService::class)->list();
    }

    public function canCreateBackup(): bool
    {
        return TechnicalAccess::allows(auth()->user(), 'technique.backup.create');
    }

    public function canRestoreBackup(): bool
    {
        return TechnicalAccess::allows(auth()->user(), 'technique.backup.restore');
    }

    public function notice(): string
    {
        return 'Sauvegardes de la base de données sur le disque privé local, sans chiffrement ni copie distante. Les '.config('technique.retention_count', 30).' sauvegardes les plus récentes sont conservées. Chaque restauration vérifie l’empreinte SHA-256 et crée d’abord un point de retour. Les documents stockés séparément ne sont pas inclus.';
    }
}
