<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Services;

use App\Modules\Logistique\Contracts\BibliothequeServiceInterface;
use App\Modules\Logistique\Exceptions\ExemplaireIndisponibleException;
use App\Modules\Logistique\Models\Emprunt;
use App\Modules\Logistique\Models\ExemplaireLivre;
use App\Modules\Logistique\Models\ReservationLivre;
use App\Modules\Socle\Contracts\ParametrageServiceContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class BibliothequeService implements BibliothequeServiceInterface
{
    public function __construct(
        private readonly ParametrageServiceContract $parametrage,
    ) {}

    public function emprunter(int $exemplaireId, Model $emprunteur, int $dureeJours = 14): object
    {
        $exemplaire = ExemplaireLivre::findOrFail($exemplaireId);

        if (! $exemplaire->disponible) {
            throw new ExemplaireIndisponibleException($exemplaireId);
        }

        $exemplaire->update(['disponible' => false]);

        return Emprunt::create([
            'exemplaire_id' => $exemplaireId,
            'emprunteur_type' => get_class($emprunteur),
            'emprunteur_id' => $emprunteur->id,
            'date_emprunt' => now(),
            'date_retour_prevue' => now()->addDays($dureeJours),
        ]);
    }

   public function retourner(int $empruntId, string $etatRetour = 'bon'): object
{
    $emprunt = Emprunt::findOrFail($empruntId);

    $penalite = 0;
    if (now()->gt($emprunt->date_retour_prevue)) {
        // Carbon 3 : diffInDays() n'est plus absolu par défaut (le signe
        // indique le sens temporel) — absolute: true rétablit l'ancien
        // comportement, sinon un retard donne une pénalité négative.
        $joursRetard = (int) now()->diffInDays($emprunt->date_retour_prevue, absolute: true);
        $tarifJour = $this->parametrage->getParametre('penalite_bibliotheque_par_jour', 100);
        $penalite = $joursRetard * $tarifJour;
    }

    $emprunt->update(['date_retour_reelle' => now(), 'penalite' => $penalite]);

    ExemplaireLivre::where('id', $emprunt->exemplaire_id)->update([
        'disponible' => true,
        'etat' => $etatRetour,
    ]);

    return $emprunt;
}

    public function reserver(int $livreId, Model $emprunteur): object
    {
        return ReservationLivre::create([
            'livre_id' => $livreId,
            'emprunteur_type' => get_class($emprunteur),
            'emprunteur_id' => $emprunteur->id,
            'date_reservation' => now(),
            'statut' => 'active',
        ]);
    }

    public function getEmpruntsEnRetard(): Collection
    {
        return Emprunt::whereNull('date_retour_reelle')
            ->where('date_retour_prevue', '<', now())
            ->get();
    }

    public function getHistoriqueEmprunteur(Model $emprunteur): Collection
    {
        return Emprunt::where('emprunteur_type', get_class($emprunteur))
            ->where('emprunteur_id', $emprunteur->id)
            ->orderByDesc('date_emprunt')
            ->get();
    }
}
