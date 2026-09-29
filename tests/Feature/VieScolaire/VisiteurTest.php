<?php

use App\Modules\VieScolaire\Contracts\VisiteurServiceInterface;
use App\Modules\VieScolaire\Models\Visiteur;
use App\Modules\Scolarite\Models\Eleve;
use App\Modules\Scolarite\Models\ParentTuteur;
use App\Models\User;

beforeEach(function () {
    $this->service ??= app(VisiteurServiceInterface::class);
    $this->actingAs(User::factory()->create(['statut' => 'actif']));

    $this->eleve = Eleve::create(['nom' => 'Dupont', 'prenom' => 'Amina']);
    $this->parentAutorise = ParentTuteur::create(['nom' => 'Dupont', 'prenom' => 'Marc']);
    $this->eleve->parentsTuteurs()->attach($this->parentAutorise->id, ['autorise_recuperation' => true]);
});

it('enregistre l\'entrée d\'un visiteur', function () {
    $visiteur = $this->service->enregistrerEntree('Jean Dupont', 'Rendez-vous administratif', null, '699000000');

    expect($visiteur)->not->toBeNull()->and($visiteur->nom)->toBe('Jean Dupont');
});

it('enregistre la sortie d\'un visiteur présent', function () {
    $visiteur = $this->service->enregistrerEntree('Jean Dupont', 'Rendez-vous', null);
    $this->service->enregistrerSortie($visiteur->id);

    expect($visiteur->fresh()->heure_sortie)->not->toBeNull();
});

it('liste uniquement les visiteurs encore présents', function () {
    $present = $this->service->enregistrerEntree('Présent', 'motif', null);
    $parti = $this->service->enregistrerEntree('Parti', 'motif', null);
    $this->service->enregistrerSortie($parti->id);

    $presents = $this->service->getVisiteursPresents();

    expect($presents)->toHaveCount(1)->and($presents->first()->id)->toBe($present->id);
});

it('vérifie l\'autorisation de récupération par nom', function () {
    $resultat = $this->service->verifierAutorisationRecuperationEleve('Marc Dupont', $this->eleve->id);

    expect($resultat)->toBeTrue();
});