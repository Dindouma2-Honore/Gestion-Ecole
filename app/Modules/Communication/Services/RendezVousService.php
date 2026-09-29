<?php

declare(strict_types=1);

namespace App\Modules\Communication\Services;

use App\Models\User;
use App\Modules\Communication\Contracts\NotificationServiceContract;
use App\Modules\Communication\Contracts\RendezVousServiceContract;
use App\Modules\Communication\Models\RendezVous;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class RendezVousService implements RendezVousServiceContract
{
    public function __construct(
        private NotificationServiceContract $notification
    ) {}

    public function demander(int $parentId, int $responsableId, string $motif, DateTimeInterface $dateHeureSouhaitee): object
    {
        $rdv = RendezVous::create([
            'parent_id' => $parentId,
            'responsable_id' => $responsableId,
            'motif' => $motif,
            'date_heure_demandee' => $dateHeureSouhaitee,
            'statut' => 'demande',
        ]);

        $responsable = User::find($responsableId);
        $this->notification->envoyer(
            canal: 'in_app',
            code: 'demande_rendez_vous',
            destinataire: $responsable,
            donnees: ['motif' => $motif]
        );

        return $rdv;
    }

    public function confirmer(int $rendezVousId, DateTimeInterface $dateHeureConfirmee): void
    {
        $rdv = RendezVous::findOrFail($rendezVousId);
        $rdv->update(['date_heure_confirmee' => $dateHeureConfirmee]);
        $rdv->changerStatut('confirme', Auth::user());

        $this->notification->envoyer(
            canal: 'whatsapp',
            code: 'rendez_vous_confirme',
            destinataire: $rdv->parent,
            donnees: ['date_heure' => $dateHeureConfirmee->format('Y-m-d H:i')]
        );
    }

    public function annuler(int $rendezVousId, string $motif): void
    {
        $rdv = RendezVous::findOrFail($rendezVousId);
        $rdv->changerStatut('annule', Auth::user(), $motif);
    }

    public function enregistrerCompteRendu(int $rendezVousId, string $compteRendu): void
    {
        $rdv = RendezVous::findOrFail($rendezVousId);
        $rdv->update(['compte_rendu' => $compteRendu]);
        $rdv->changerStatut('effectue', Auth::user());
    }

    public function getRendezVousAVenir(int $responsableId): Collection
    {
        return RendezVous::where('responsable_id', $responsableId)
            ->where('statut', 'confirme')
            ->where('date_heure_confirmee', '>=', now())
            ->orderBy('date_heure_confirmee')
            ->get();
    }
}
