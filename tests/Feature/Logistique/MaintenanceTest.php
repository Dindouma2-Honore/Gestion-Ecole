<?php

use App\Modules\Logistique\Contracts\MaintenanceServiceInterface;
use App\Modules\Logistique\Models\DemandeIntervention;
use App\Modules\Logistique\Models\Equipement;

beforeEach(function () {
    $this->service ??= app(MaintenanceServiceInterface::class);
    $this->equipement = Equipement::create([
        'nom' => 'Climatiseur', 'numero_identification' => 'EQ-100', 'date_acquisition' => now(),
    ]);
});

it('signale une panne (point d\'entrée appelé par EquipementService::declarerPanne)', function () {
    $demande = $this->service->signalerPanne($this->equipement->id, 'Ne refroidit plus');

    expect($demande)->not->toBeNull()
        ->and($demande->equipement_id)->toBe($this->equipement->id)
        ->and($demande->statut)->toBe('signalee');
});

it('diagnostique une demande et enregistre le coût estimé', function () {
    $demande = $this->service->signalerPanne($this->equipement->id, 'Fuite de gaz');

    $this->service->diagnostiquer($demande->id, 'Compresseur HS', 45000.0);

    $demande->refresh();
    expect($demande->statut)->toBe('diagnostiquee')
        ->and($demande->diagnostic)->toBe('Compresseur HS');
});

it('termine une réparation et enregistre le coût réel', function () {
    $demande = DemandeIntervention::create([
        'equipement_id' => $this->equipement->id,
        'description' => 'Panne moteur',
        'type' => 'curative',
        'statut' => 'diagnostiquee',
    ]);

    $this->service->terminerReparation($demande->id, 38000.0, 'Courroie + roulement');

    $demande->refresh();
    expect($demande->statut)->toBe('terminee')
        ->and((float) $demande->cout)->toBe(38000.0)
        ->and($demande->pieces_utilisees)->toBe('Courroie + roulement');
});

it('planifie une maintenance préventive avec une échéance calculée', function () {
    $plan = $this->service->planifierMaintenancePreventive($this->equipement->id, frequenceJours: 90);

    expect($plan)->not->toBeNull()
        ->and($plan->frequence_jours)->toBe(90)
        ->and($plan->prochaine_echeance)->not->toBeNull();
});

it('retourne les maintenances arrivées à échéance', function () {
    // NB: planifierMaintenancePreventive() calcule une échéance FUTURE
    // (now() + frequenceJours) — donc pour tester "arrivée à échéance", on
    // crée les Plans directement via le Model avec une date déjà passée,
    // plutôt que de passer par le Service qui ne produirait jamais ce cas.
    \App\Modules\Logistique\Models\PlanMaintenancePreventive::create([
        'equipement_id' => $this->equipement->id,
        'frequence_jours' => 30,
        'prochaine_echeance' => now()->subDay(),
    ]);

    $equipementB = Equipement::create([
        'nom' => 'Photocopieur', 'numero_identification' => 'EQ-101', 'date_acquisition' => now(),
    ]);
    // Plan encore loin de son échéance -> ne doit pas apparaître
    \App\Modules\Logistique\Models\PlanMaintenancePreventive::create([
        'equipement_id' => $equipementB->id,
        'frequence_jours' => 365,
        'prochaine_echeance' => now()->addMonths(6),
    ]);

    $dues = $this->service->getMaintenancesDues();

    expect($dues->pluck('equipement_id'))->toContain($this->equipement->id)
        ->and($dues->pluck('equipement_id'))->not->toContain($equipementB->id);
});
