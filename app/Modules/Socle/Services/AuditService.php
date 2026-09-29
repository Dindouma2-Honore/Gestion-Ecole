<?php

declare(strict_types=1);

namespace App\Modules\Socle\Services;

use App\Modules\Socle\Contracts\AuditServiceContract;
use App\Modules\Socle\Exceptions\MotifObligatoireException;
use App\Modules\Socle\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class AuditService implements AuditServiceContract
{
    public function enregistrerAvecMotif(Model $sujet, string $description, string $motif): void
    {
        if (empty(trim($motif))) {
            throw new MotifObligatoireException($description);
        }

        auditLog()
            ->causedBy(Auth::user())
            ->performedOn($sujet)
            ->withProperties([
                'motif' => $motif,
                'ip_address' => request()?->ip(),
            ])
            ->log($description);
    }

    public function enregistrer(Model $sujet, string $description): void
    {
        auditLog()
            ->causedBy(Auth::user())
            ->performedOn($sujet)
            ->withProperties(['ip_address' => request()?->ip()])
            ->log($description);
    }

    public function enregistrerConsultation(Model $sujet, string $contexte): void
    {
        auditLog()
            ->useLog('consultation')
            ->causedBy(Auth::user())
            ->performedOn($sujet)
            ->withProperties(['ip_address' => request()?->ip()])
            ->log("Consultation : {$contexte}");
    }

    public function getHistorique(Model $sujet): Collection
    {
        return ActivityLog::forSubject($sujet)
            ->orderByDesc('created_at')
            ->get();
    }
}
