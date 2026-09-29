<?php

use App\Modules\Logistique\Contracts\CantineServiceInterface;
use App\Modules\Logistique\Models\AbonnementCantine;
use App\Modules\Socle\Models\AnneeScolaire;

beforeEach(function () {
    AnneeScolaire::create([
        'libelle' => '2026-2027',
        'date_debut' => '2026-09-01',
        'date_fin' => '2027-06-30',
        'statut' => AnneeScolaire::STATUT_ACTIVE,
    ]);

    $this->service ??= app(CantineServiceInterface::class);
});

it('souscrit un abonnement cantine', function () {
    $abonnement = $this->service->souscrireAbonnement(eleveId: 1, type: 'mensuel', dateDebut: now());

    expect($abonnement)->not->toBeNull()
        ->and($abonnement->type)->toBe('mensuel')
        ->and($abonnement->statut)->toBe('actif');
    // NOTE POUR DINDOUMA : la migration abonnements_cantine exige aussi
    // annee_scolaire_id (NOT NULL, pas de défaut) — vérifie que
    // CantineService::souscrireAbonnement() la renseigne bien (ex: via
    // AnneeScolaireServiceContract::getAnneeCouranteId(), Socle), sinon ce
    // test échouera avec la même erreur SQL que celle déjà rencontrée sur
    // le formulaire Filament.
});

it('enregistre une présence au repas', function () {
    $abonnement = AbonnementCantine::create([
        'eleve_id' => 1, 'annee_scolaire_id' => 1, 'type' => 'mensuel',
        'date_debut' => now(), 'date_fin' => now()->addMonth(),
        'montant' => 15000, 'statut' => 'actif',
    ]);

    $this->service->enregistrerPresenceRepas($abonnement->id, now(), present: true);

    expect(\App\Modules\Logistique\Models\PresenceCantine::where('abonnement_id', $abonnement->id)->exists())->toBeTrue();
});

it('vérifie la compatibilité du menu du jour avec les allergies de l\'élève', function () {
    // TODO: dépend de SanteServiceInterface (module VieScolaire) — nécessite
    // un DossierSante réel avec allergies renseignées pour être testé
    // significativement. Pour l'instant, vérifie juste que l'appel ne casse
    // pas et retourne un tableau.
    $resultat = $this->service->verifierCompatibiliteMenu(eleveId: 1, date: now());

    expect($resultat)->toBeArray();
});

it('calcule l\'effectif prévu pour une date donnée', function () {
    AbonnementCantine::create([
        'eleve_id' => 1, 'annee_scolaire_id' => 1, 'type' => 'mensuel',
        'date_debut' => now()->subDay(), 'date_fin' => now()->addMonth(),
        'montant' => 15000, 'statut' => 'actif',
    ]);
    AbonnementCantine::create([
        'eleve_id' => 2, 'annee_scolaire_id' => 1, 'type' => 'mensuel',
        'date_debut' => now()->subDay(), 'date_fin' => now()->addMonth(),
        'montant' => 15000, 'statut' => 'suspendu',
    ]);

    $effectif = $this->service->getEffectifPrevu(now());

    expect($effectif)->toBe(1);
    // Hypothèse testée : seuls les abonnements "actif" comptent dans
    // l'effectif prévu — à ajuster si CantineService compte différemment.
});
