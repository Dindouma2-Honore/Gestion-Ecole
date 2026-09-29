<?php

declare(strict_types=1);

namespace App\Modules\VieScolaire\Services;

use App\Modules\Scolarite\Contracts\ParentTuteurServiceInterface;
use App\Modules\Socle\Contracts\AuditServiceContract;
use App\Modules\VieScolaire\Contracts\SanteServiceInterface;
use App\Modules\VieScolaire\Exceptions\DossierSanteInexistantException;
use App\Modules\VieScolaire\Models\DossierSante;
use App\Modules\VieScolaire\Models\VisiteInfirmerie;
use Illuminate\Support\Facades\Auth;

class SanteService implements SanteServiceInterface
{
    public function __construct(
        private readonly AuditServiceContract $audit,
        private readonly ParentTuteurServiceInterface $parent,
        // TODO: brancher un vrai NotificationServiceContract une fois identifié
        // (même point resté ouvert depuis D.29 Pédagogie — CourrierServiceContract
        // n'est pas le bon contrat, voir notes du module Pédagogie).
    ) {}

    public function creerOuMettreAJourDossier(int $eleveId, array $donneesMedicales): object
    {
        return DossierSante::updateOrCreate(['eleve_id' => $eleveId], $donneesMedicales);
    }

    public function consulterDossier(int $eleveId): object
    {
        $dossier = DossierSante::where('eleve_id', $eleveId)->first();

        if (! $dossier) {
            throw new DossierSanteInexistantException($eleveId);
        }

        // Principe posé en A.4 : une simple LECTURE doit être auditée
        // explicitement ici — appel obligatoire à chaque endroit du code
        // qui affiche ce dossier, jamais un accès direct au Model.
        $this->audit->enregistrerConsultation($dossier, "Consultation dossier santé eleve #{$eleveId}");

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
            'traite_par' => Auth::id(),
        ]);

        if (in_array($gravite, ['moderee', 'grave'], true)) {
            // TODO: notification réelle désactivée en attendant le vrai
            // NotificationServiceContract — parent_notifie reste false tant
            // que ce point n'est pas résolu, ne pas mentir dans la donnée.
            // $personnesAutorisees = $this->parent->getPersonnesAutoriseesRecuperer($eleveId);
            // $this->notification->envoyerImmediat(...);
            // $visite->update(['parent_notifie' => true]);
        }

        return $visite;
    }

    public function getInfosUrgence(int $eleveId): object
    {
        $dossier = DossierSante::where('eleve_id', $eleveId)->first();

        if (! $dossier) {
            return (object) ['groupe_sanguin' => null, 'allergies' => null, 'contact_urgence' => null];
        }

        return (object) [
            'groupe_sanguin' => $dossier->groupe_sanguin,
            'allergies' => $dossier->allergies,
            'contact_urgence' => $dossier->contact_urgence_nom.' — '.$dossier->contact_urgence_telephone,
        ];
        // Volontairement PAS audité comme consulterDossier() — à confirmer
        // avec Joel si même ce niveau d'info doit être tracé.
    }
}
