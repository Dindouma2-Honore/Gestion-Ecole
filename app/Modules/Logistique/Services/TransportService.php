<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Services;

use App\Modules\Logistique\Contracts\TransportServiceInterface;
use App\Modules\Logistique\Exceptions\CapaciteVehiculeDepasseeException;
use App\Modules\Logistique\Models\CircuitTransport;
use App\Modules\Logistique\Models\IncidentTransport;
use App\Modules\Logistique\Models\InscriptionTransport;
use App\Modules\Logistique\Models\PresenceTransport;
use Illuminate\Support\Collection;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;

class TransportService implements TransportServiceInterface
{
    public function __construct(
        private readonly AnneeScolaireServiceContract $anneeScolaire,
    ) {}

    public function inscrireEleve(int $eleveId, int $circuitId, int $arretId): object
    {
        $circuit = CircuitTransport::with('vehicule')->findOrFail($circuitId);
        $effectifActuel = InscriptionTransport::where('circuit_id', $circuitId)->where('statut', 'actif')->count();

        // Vérifie la capacité du véhicule AVANT d'inscrire — même principe
        // déjà vu en C.22 (capacité de classe), réappliqué ici pour un
        // contexte différent (capacité de véhicule).
        if ($effectifActuel >= $circuit->vehicule->capacite) {
            throw new CapaciteVehiculeDepasseeException($circuitId);
        }

        return InscriptionTransport::create([
            'eleve_id' => $eleveId,
            'circuit_id' => $circuitId,
            'arret_id' => $arretId,
            'annee_scolaire_id' => $this->anneeScolaire->getAnneeCouranteId(),
            'statut' => 'actif',
        ]);
    }

    public function enregistrerPresenceTrajet(int $inscriptionId, string $trajet, bool $present): void
    {
        PresenceTransport::updateOrCreate(
            ['inscription_transport_id' => $inscriptionId, 'date_trajet' => now()->toDateString(), 'trajet' => $trajet],
            ['present' => $present]
        );

        // Si l'élève est absent au trajet du matin, ça pourrait informer
        // D.28/D.29 (Présences/Détection) — à discuter avec Joel si un
        // élève transporté absent au bus doit automatiquement générer une
        // alerte, ou si les deux systèmes restent indépendants.
    }

    public function signalerIncident(int $circuitId, string $description, string $gravite): object
    {
        return IncidentTransport::create([
            'circuit_id' => $circuitId,
            'description' => $description,
            'date_incident' => now(),
            'gravite' => $gravite,
        ]);
    }

    public function getListeEleveParCircuit(int $circuitId): Collection
    {
        return InscriptionTransport::where('circuit_id', $circuitId)
            ->where('statut', 'actif')
            ->with('arret')
            ->get();
    }

    public function getTauxOccupation(int $circuitId): float
    {
        $circuit = CircuitTransport::with('vehicule')->findOrFail($circuitId);
        $effectif = InscriptionTransport::where('circuit_id', $circuitId)->where('statut', 'actif')->count();

        return round(($effectif / $circuit->vehicule->capacite) * 100, 1);
    }
}
