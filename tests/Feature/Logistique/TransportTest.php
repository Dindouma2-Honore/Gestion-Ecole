<?php

use App\Modules\Logistique\Contracts\TransportServiceInterface;
use App\Modules\Logistique\Exceptions\CapaciteVehiculeDepasseeException;
use App\Modules\Logistique\Models\ArretCircuit;
use App\Modules\Logistique\Models\CircuitTransport;
use App\Modules\Logistique\Models\Vehicule;
use App\Modules\Socle\Models\AnneeScolaire;

beforeEach(function () {
    AnneeScolaire::create([
        'libelle' => '2026-2027',
        'date_debut' => '2026-09-01',
        'date_fin' => '2027-06-30',
        'statut' => AnneeScolaire::STATUT_ACTIVE,
    ]);

    $this->service ??= app(TransportServiceInterface::class);

    $this->vehicule = Vehicule::create(['immatriculation' => 'CE-001-AB', 'capacite' => 2]);
    $this->circuit = CircuitTransport::create([
        'nom' => 'Circuit Nord', 'vehicule_id' => $this->vehicule->id, 'chauffeur_id' => 1,
    ]);
    $this->arret = ArretCircuit::create(['circuit_id' => $this->circuit->id, 'nom' => 'Arrêt A', 'ordre' => 1]);
});

it('inscrit un élève à un circuit', function () {
    $inscription = $this->service->inscrireEleve(eleveId: 1, circuitId: $this->circuit->id, arretId: $this->arret->id);

    expect($inscription)->not->toBeNull()
        ->and($inscription->statut)->toBe('actif');
});

it('refuse une inscription si le véhicule est déjà plein', function () {
    $this->service->inscrireEleve(1, $this->circuit->id, $this->arret->id);
    $this->service->inscrireEleve(2, $this->circuit->id, $this->arret->id);
    // véhicule de capacité 2, déjà 2 inscrits actifs -> le 3e doit être refusé

    expect(fn () => $this->service->inscrireEleve(3, $this->circuit->id, $this->arret->id))
        ->toThrow(CapaciteVehiculeDepasseeException::class);
});

it('enregistre une présence au trajet du matin', function () {
    $inscription = $this->service->inscrireEleve(1, $this->circuit->id, $this->arret->id);

    $this->service->enregistrerPresenceTrajet($inscription->id, 'matin', present: true);

    expect(\App\Modules\Logistique\Models\PresenceTransport::where('inscription_transport_id', $inscription->id)->exists())->toBeTrue();
});

it('signale un incident sur un circuit', function () {
    $incident = $this->service->signalerIncident($this->circuit->id, 'Panne moteur', 'majeur');

    expect($incident)->not->toBeNull()
        ->and($incident->gravite)->toBe('majeur');
});

it('liste les élèves inscrits à un circuit', function () {
    $this->service->inscrireEleve(1, $this->circuit->id, $this->arret->id);

    $liste = $this->service->getListeEleveParCircuit($this->circuit->id);

    expect($liste)->toHaveCount(1);
});

it('calcule le taux d\'occupation d\'un circuit', function () {
    $this->service->inscrireEleve(1, $this->circuit->id, $this->arret->id);
    // capacité 2, 1 inscrit -> 50%

    $taux = $this->service->getTauxOccupation($this->circuit->id);

    expect($taux)->toBe(50.0);
});
