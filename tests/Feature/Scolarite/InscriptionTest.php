<?php

declare(strict_types=1);

use App\Modules\Scolarite\Contracts\ClasseServiceInterface;
use App\Modules\Scolarite\Contracts\EleveServiceInterface;
use App\Modules\Scolarite\Contracts\InscriptionServiceInterface;
use App\Modules\Scolarite\Exceptions\CapaciteClasseDepasseeException;
use App\Modules\Scolarite\Exceptions\DoubleInscriptionException;
use App\Modules\Scolarite\Models\Classe;
use App\Modules\Scolarite\Models\Eleve;

it('conserve toutes les informations du dossier de nouvelle admission', function () {
    $resultat = app(EleveServiceInterface::class)->creer([
        'nom' => 'Mbarga', 'prenom' => 'Alice', 'date_naissance' => '2016-05-12',
        'lieu_naissance' => 'Yaoundé', 'sexe' => 'F', 'nationalite' => 'Camerounaise',
        'email' => 'alice@example.test', 'ancien_etablissement' => 'École du Centre',
        'nom_pere' => 'Paul Mbarga', 'nom_mere' => 'Marie Mbarga', 'nom_tuteur' => 'Paul Mbarga',
        'telephone_tuteur' => '677000000', 'redoublant' => true, 'transfere' => true,
        'numero_decision' => 'DEC-2026-15', 'exonere_frais_examen' => true,
        'info_sms' => true, 'moyenne_legacy' => 14.5, 'bonus_scolaire' => 2,
        'exclu_inscription' => false, 'observation' => 'Dossier complet.',
    ]);

    $eleve = Eleve::findOrFail($resultat['id']);
    expect($eleve->lieu_naissance)->toBe('Yaoundé')
        ->and($eleve->ancien_etablissement)->toBe('École du Centre')
        ->and($eleve->nom_pere)->toBe('Paul Mbarga')
        ->and($eleve->nom_mere)->toBe('Marie Mbarga')
        ->and($eleve->telephone_tuteur)->toBe('677000000')
        ->and($eleve->redoublant)->toBeTrue()
        ->and($eleve->transfere)->toBeTrue()
        ->and($eleve->exonere_frais_examen)->toBeTrue()
        ->and($eleve->info_sms)->toBeTrue()
        ->and((float) $eleve->moyenne_legacy)->toBe(14.5)
        ->and($eleve->bonus_scolaire)->toBe(2)
        ->and($eleve->statut)->toBe('prospect');
});

it('inscrit un élève et lui attribue un matricule', function () {
    $eleveId = app(EleveServiceInterface::class)->creer(['nom' => 'Doe', 'prenom' => 'Jane'])['id'];
    Eleve::whereKey($eleveId)->update(['statut' => 'admis']);

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
    Eleve::whereKey($eleveId)->update(['statut' => 'admis']);

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
    Eleve::whereKey([$premier, $second])->update(['statut' => 'admis']);

    $service->inscrire($premier, $classe->id, 3);

    expect(fn () => $service->inscrire($second, $classe->id, 3))
        ->toThrow(CapaciteClasseDepasseeException::class);
});

it('calcule correctement les places restantes d\'une classe', function () {
    $classe = Classe::create(['nom' => 'CP1 A', 'niveau_id' => 1, 'annee_scolaire_id' => 5, 'capacite_max' => 2]);
    $classeService = app(ClasseServiceInterface::class);

    expect($classeService->getPlacesRestantes($classe->id))->toBe(2);

    $eleveId = app(EleveServiceInterface::class)->creer(['nom' => 'Doe', 'prenom' => 'C'])['id'];
    Eleve::whereKey($eleveId)->update(['statut' => 'admis']);
    app(InscriptionServiceInterface::class)->inscrire($eleveId, $classe->id, 5);

    expect($classeService->getPlacesRestantes($classe->id))->toBe(1);
});

it('autorise l inscription d un élève quel que soit le statut de son dossier', function () {
    $eleveId = app(EleveServiceInterface::class)->creer(['nom' => 'Prospect', 'prenom' => 'Test'])['id'];
    $classe = Classe::create(['nom' => 'CE1 A', 'niveau_id' => 1, 'annee_scolaire_id' => 6, 'capacite_max' => 30]);

    $inscriptionId = app(InscriptionServiceInterface::class)->inscrire($eleveId, $classe->id, 6);

    expect($inscriptionId)->toBeInt();
    expect(Eleve::findOrFail($eleveId)->statut)->toBe('inscrit');
});

it('réactive une inscription annulée sans lever de DoubleInscriptionException', function () {
    $eleveId = app(EleveServiceInterface::class)->creer(['nom' => 'Annule', 'prenom' => 'Jean'])['id'];
    $classe1 = Classe::create(['nom' => 'CM1 A', 'niveau_id' => 1, 'annee_scolaire_id' => 7, 'capacite_max' => 30]);
    $classe2 = Classe::create(['nom' => 'CM1 B', 'niveau_id' => 1, 'annee_scolaire_id' => 7, 'capacite_max' => 30]);

    $service = app(InscriptionServiceInterface::class);
    $premierId = $service->inscrire($eleveId, $classe1->id, 7);

    // Annulation de la première inscription
    \App\Modules\Scolarite\Models\Inscription::whereKey($premierId)->update(['statut' => 'annulee']);
    expect($service->estInscrit($eleveId, 7))->toBeFalse();

    // Réinscription pour la même année scolaire
    $nouveauId = $service->inscrire($eleveId, $classe2->id, 7);
    expect($nouveauId)->toBe($premierId);
    expect($service->estInscrit($eleveId, 7))->toBeTrue();

    $inscription = \App\Modules\Scolarite\Models\Inscription::findOrFail($nouveauId);
    expect($inscription->classe_id)->toBe($classe2->id)
        ->and($inscription->statut)->toBe('validee');
});

it('synchronise automatiquement le parent tuteur lors de la création ou modification du dossier élève', function () {
    $resultat = app(EleveServiceInterface::class)->creer([
        'nom' => 'Mbassi',
        'prenom' => 'Paul',
        'statut' => 'admis',
        'nom_pere' => 'Mbassi Pierre',
        'telephone_tuteur' => '677999888',
        'email' => 'mbassi.pierre@test.com',
    ]);

    $eleve = Eleve::findOrFail($resultat['id']);
    expect($eleve->statut)->toBe('admis');
    expect($eleve->parentsTuteurs)->toHaveCount(1);

    $parent = $eleve->parentsTuteurs->first();
    expect($parent->nom)->toBe('Mbassi Pierre')
        ->and($parent->telephone)->toBe('677999888')
        ->and((bool) $parent->pivot->responsable_paiement)->toBeTrue();

    // Modification des informations du tuteur
    $eleve->update([
        'nom_tuteur' => 'Mbassi Tuteur Officiel',
        'telephone_tuteur' => '699111222',
    ]);

    $parentActu = $eleve->fresh()->parentsTuteurs->first();
    expect($parentActu->nom)->toBe('Mbassi Tuteur Officiel')
        ->and($parentActu->telephone)->toBe('699111222')
        ->and($parentActu->pivot->lien)->toBe('tuteur');
});
