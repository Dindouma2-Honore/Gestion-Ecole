<?php

use App\Modules\VieScolaire\Contracts\SortieEleveServiceInterface;
use App\Modules\VieScolaire\Exceptions\PersonneNonAutoriseeException;

use App\Modules\Scolarite\Models\Eleve;
use App\Modules\Scolarite\Models\ParentTuteur;
use App\Models\User;

beforeEach(function () {
    $this->service ??= app(SortieEleveServiceInterface::class);
    $this->actingAs(User::factory()->create(['statut' => 'actif']));

    $this->eleve ??= Eleve::create(['nom' => 'Dupont', 'prenom' => 'Amina']);
    $this->parentAutorise ??= ParentTuteur::create(['nom' => 'Dupont', 'prenom' => 'Marc']);
    $this->parentNonAutorise ??= ParentTuteur::create(['nom' => 'Inconnu', 'prenom' => 'Paul']);

    $this->eleve->parentsTuteurs()->attach($this->parentAutorise->id, ['autorise_recuperation' => true]);
    $this->eleve->parentsTuteurs()->attach($this->parentNonAutorise->id, ['autorise_recuperation' => false]);
});

it('enregistre une sortie normale pour un parent autorisé', function () {
    $sortie = $this->service->enregistrerSortieNormale($this->eleve->id, $this->parentAutorise->id);

    expect($sortie)->not->toBeNull()->and($sortie->type)->toBe('normale');
});

it('refuse une sortie normale pour un parent non autorisé', function () {
    expect(fn () => $this->service->enregistrerSortieNormale($this->eleve->id, $this->parentNonAutorise->id))
        ->toThrow(PersonneNonAutoriseeException::class);
});