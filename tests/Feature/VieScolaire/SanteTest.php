<?php

use App\Modules\VieScolaire\Contracts\SanteServiceInterface;
use App\Modules\VieScolaire\Exceptions\DossierSanteInexistantException;
use App\Models\User;

beforeEach(function () {
    $this->service ??= app(SanteServiceInterface::class);
    $this->actingAs(User::factory()->create(['statut' => 'actif']));
});

it('crée un dossier santé', function () {
    $dossier = $this->service->creerOuMettreAJourDossier(1, [
        'groupe_sanguin' => 'O+',
        'allergies' => 'Arachides',
        'contact_urgence_nom' => 'Mère de l\'élève',
        'contact_urgence_telephone' => '699000000',
    ]);

    expect($dossier)->not->toBeNull()
        ->and($dossier->groupe_sanguin)->toBe('O+');
});

it('lève une exception si on consulte un dossier inexistant', function () {
    expect(fn () => $this->service->consulterDossier(999))
        ->toThrow(DossierSanteInexistantException::class);
});

it('consulte un dossier existant', function () {
    $this->service->creerOuMettreAJourDossier(1, [
        'contact_urgence_nom' => 'Mère', 'contact_urgence_telephone' => '699000000',
    ]);

    $dossier = $this->service->consulterDossier(1);

    expect($dossier)->not->toBeNull()
        ->and($dossier->eleve_id)->toBe(1);
    // NOTE POUR DINDOUMA : le point le plus important de ce test n'est
    // pas visible ici — consulterDossier() doit appeler en interne
    // AuditServiceContract::enregistrerConsultation(). Si tu as accès à
    // un moyen d'espionner/mocker ce contrat (Mockery::spy ou équivalent
    // déjà utilisé côté SocleA4Test pour l'audit), ajoute une assertion
    // explicite ici que enregistrerConsultation() a bien été appelé.
});

it('enregistre une visite infirmerie mineure', function () {
    $visite = $this->service->enregistrerVisite(1, 'Mal de tête', 'mineure');

    expect($visite)->not->toBeNull()
        ->and($visite->evacuation_necessaire)->toBeFalse();
});

it('marque l\'évacuation nécessaire pour une visite grave', function () {
    $visite = $this->service->enregistrerVisite(1, 'Chute avec traumatisme', 'grave');

    expect($visite->evacuation_necessaire)->toBeTrue();
});

it('retourne une structure vide pour les infos urgence si aucun dossier', function () {
    $infos = $this->service->getInfosUrgence(999);

    expect($infos->groupe_sanguin)->toBeNull()
        ->and($infos->contact_urgence)->toBeNull();
});

it('retourne les infos urgence formatées si le dossier existe', function () {
    $this->service->creerOuMettreAJourDossier(1, [
        'groupe_sanguin' => 'A+',
        'contact_urgence_nom' => 'Père',
        'contact_urgence_telephone' => '677000000',
    ]);

    $infos = $this->service->getInfosUrgence(1);

    expect($infos->groupe_sanguin)->toBe('A+')
        ->and($infos->contact_urgence)->toBe('Père — 677000000');
});
