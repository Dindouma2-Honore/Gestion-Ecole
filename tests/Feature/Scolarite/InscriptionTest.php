<?php

declare(strict_types=1);

use App\Modules\Scolarite\Contracts\ClasseServiceInterface;
use App\Modules\Scolarite\Contracts\EleveServiceInterface;
use App\Modules\Scolarite\Contracts\InscriptionServiceInterface;
use App\Modules\Scolarite\Exceptions\CapaciteClasseDepasseeException;
use App\Modules\Scolarite\Exceptions\DoubleInscriptionException;
use App\Modules\Scolarite\Models\Classe;

it('inscrit un élève et lui attribue un matricule', function () {
    $eleveId = app(EleveServiceInterface::class)->creer(['nom' => 'Doe', 'prenom' => 'Jane'])['id'];

    $classe = Classe::create([
        'nom' => 'CP1 A',
        'niveau_id' => 1,
        'annee_scolaire_id' => 1,
        'capacite_max' => 30,
    ]);

    $service = app(InscriptionServiceInterface::class);
    $inscriptionId = $service->inscrire($eleveId, $classe->id, 1);

    expect($inscriptionId)->toBeInt();
    expect($service->estInscrit($eleveId, 1))->toBeTrue();

    $eleve = app(EleveServiceInterface::class)->getEleve($eleveId);
    expect($eleve['classe_id'])->toBe($classe->id);
});

it('empêche la double inscription du même élève la même année', function () {
    $eleveId = app(EleveServiceInterface::class)->creer(['nom' => 'Doe', 'prenom' => 'John'])['id'];

    $classe1 = Classe::create(['nom' => 'CP1 A', 'niveau_id' => 1, 'annee_scolaire_id' => 2, 'capacite_max' => 30]);
    $classe2 = Classe::create(['nom' => 'CP1 B', 'niveau_id' => 1, 'annee_scolaire_id' => 2, 'capacite_max' => 30]);

    $service = app(InscriptionServiceInterface::class);
    $service->inscrire($eleveId, $classe1->id, 2);

    expect(fn () => $service->inscrire($eleveId, $classe2->id, 2))
        ->toThrow(DoubleInscriptionException::class);
});

it('refuse une inscription si la classe est pleine', function () {
    $classe = Classe::create(['nom' => 'CP1 A', 'niveau_id' => 1, 'annee_scolaire_id' => 3, 'capacite_max' => 1]);
    $service = app(InscriptionServiceInterface::class);

    $premier = app(EleveServiceInterface::class)->creer(['nom' => 'Doe', 'prenom' => 'A'])['id'];
    $second = app(EleveServiceInterface::class)->creer(['nom' => 'Doe', 'prenom' => 'B'])['id'];

    $service->inscrire($premier, $classe->id, 3);

    expect(fn () => $service->inscrire($second, $classe->id, 3))
        ->toThrow(CapaciteClasseDepasseeException::class);
});

it('calcule correctement les places restantes d\'une classe', function () {
    $classe = Classe::create(['nom' => 'CP1 A', 'niveau_id' => 1, 'annee_scolaire_id' => 5, 'capacite_max' => 2]);
    $classeService = app(ClasseServiceInterface::class);

    expect($classeService->getPlacesRestantes($classe->id))->toBe(2);

    $eleveId = app(EleveServiceInterface::class)->creer(['nom' => 'Doe', 'prenom' => 'C'])['id'];
    app(InscriptionServiceInterface::class)->inscrire($eleveId, $classe->id, 5);

    expect($classeService->getPlacesRestantes($classe->id))->toBe(1);
});
