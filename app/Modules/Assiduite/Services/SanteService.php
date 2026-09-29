<?php

declare(strict_types=1);

namespace App\Modules\Assiduite\Services;

use App\Modules\Assiduite\Contracts\SanteServiceContract;
use App\Modules\Assiduite\Exceptions\DossierSanteInexistantException;
use App\Modules\Assiduite\Models\DossierSante;
use App\Modules\Assiduite\Models\VisiteInfirmerie;
use App\Modules\Socle\Contracts\AuditServiceContract;
use App\Modules\Socle\Contracts\NotificationServiceContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SanteService implements SanteServiceContract
{
    public function __construct(
        private ?AuditServiceContract $audit = null,
        private ?NotificationServiceContract $notification = null
    ) {}

    public function creerOuMettreAJourDossier(int $eleveId, array $donneesMedicales): object
    {
        return DossierSante::updateOrCreate(
            ['eleve_id' => $eleveId],
            $donneesMedicales
        );
    }

    public function consulterDossier(int $eleveId): object
    {
        $dossier = DossierSante::where('eleve_id', $eleveId)->first();
        if (!$dossier) {
            throw new DossierSanteInexistantException($eleveId);
        }

        if ($this->audit) {
            $this->audit->enregistrerConsultation($dossier, "Consultation dossier santé eleve #{$eleveId}");
        }

        return $dossier;
    }

    public function enregistrerVisite(int $eleveId, string $motif, string $gravite, ?string $soins = null): object
    {
        $visite = VisiteInfirmerie::create([
            'eleve_id' => $eleveId,
            'date_heure' => now(),
            'motif' => $motif,
            'soins_prodigues' => $soins,
            'gravite' => $gravite,
            'evacuation_necessaire' => $gravite === 'grave',
            'traite_par' => Auth::id() ?? 1,
        ]);

        if (in_array($gravite, ['moderee', 'grave'])) {
            $eleveData = DB::table('eleves')->where('id', $eleveId)->first();
            if ($eleveData && $this->notification) {
                $this->notification->envoyer(
                    canal: 'whatsapp',
                    code: 'visite_infirmerie_' . $gravite,
                    eleveId: $eleveId,
                    donnees: ['eleve' => $eleveData->nom ?? '', 'motif' => $motif]
                );
                $visite->update(['parent_notifie' => true]);
            }
        }

        return $visite;
    }

    public function getInfosUrgence(int $eleveId): object
    {
        $dossier = DossierSante::where('eleve_id', $eleveId)->first();

        if (!$dossier) {
            return (object) ['groupe_sanguin' => null, 'allergies' => null, 'contact_urgence' => null];
        }

        return (object) [
            'groupe_sanguin' => $dossier->groupe_sanguin,
            'allergies' => $dossier->allergies,
            'contact_urgence' => $dossier->contact_urgence_nom . ' — ' . $dossier->contact_urgence_telephone,
        ];
    }
}
