<?php

use App\Models\User;
use App\Modules\RH\Models\Contrat;
use App\Modules\RH\Models\Employe;
use App\Modules\RH\Models\PosteAdministratif;
use App\Modules\RH\Services\EmployeService;
use App\Modules\Scolarite\Models\Niveau;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->employeService = app(EmployeService::class);
});

test('changement de poste met a jour poste_administratif_id et synchronise le libelle', function () {
    $ancienPoste = PosteAdministratif::create([
        'nom' => 'Ancien Poste',
        'actif' => true,
    ]);
    $nouveauPoste = PosteAdministratif::create([
        'nom' => 'Nouveau Poste',
        'actif' => true,
    ]);

    $employe = Employe::create([
        'matricule' => 'EMP-001',
        'nom' => 'Doe',
        'prenom' => 'John',
        'email' => 'john.doe@example.com',
        'poste_administratif_id' => $ancienPoste->id,
        'poste' => $ancienPoste->nom,
        'date_embauche' => now()->toDateString(),
        'statut' => 'actif',
    ]);

    $this->employeService->muter($employe->id, $nouveauPoste->id, null, 'Mutation de routine');

    $employe->refresh();

    expect($employe->poste_administratif_id)->toBe($nouveauPoste->id);
    expect($employe->poste)->toBe($nouveauPoste->nom);
});

test('mutation genere un enregistrement dans historiqueCarriere', function () {
    $ancienPoste = PosteAdministratif::create(['nom' => 'Poste 1', 'actif' => true]);
    $nouveauPoste = PosteAdministratif::create(['nom' => 'Poste 2', 'actif' => true]);

    $employe = Employe::create([
        'matricule' => 'EMP-002',
        'nom' => 'Smith',
        'prenom' => 'Alice',
        'email' => 'alice.smith@example.com',
        'poste_administratif_id' => $ancienPoste->id,
        'poste' => $ancienPoste->nom,
        'date_embauche' => now()->toDateString(),
        'statut' => 'actif',
    ]);

    $this->employeService->muter($employe->id, $nouveauPoste->id, null, 'Changement de service');

    expect($employe->historiqueCarriere()->count())->toBe(1);

    $ligneCarriere = $employe->historiqueCarriere()->first();
    expect($ligneCarriere->evenement)->toBe('mutation');
    expect($ligneCarriere->ancien_poste)->toBe($ancienPoste->nom);
    expect($ligneCarriere->nouveau_poste)->toBe($nouveauPoste->nom);
    expect($ligneCarriere->commentaire)->toBe('Changement de service');
});

test('promotion met a jour le niveau et la remuneration negocee sur le contrat', function () {
    $ancienPoste = PosteAdministratif::create(['nom' => 'Dev Junior', 'actif' => true]);
    $nouveauPoste = PosteAdministratif::create(['nom' => 'Dev Senior', 'actif' => true]);

    $niveauInit = Niveau::create(['code' => 'N1', 'nom' => 'Niveau 1']);
    $niveauNouveau = Niveau::create(['code' => 'N2', 'nom' => 'Niveau 2']);

    $employe = Employe::create([
        'matricule' => 'EMP-003',
        'nom' => 'Durand',
        'prenom' => 'Paul',
        'email' => 'paul.durand@example.com',
        'poste_administratif_id' => $ancienPoste->id,
        'poste' => $ancienPoste->nom,
        'niveau_id' => $niveauInit->id,
        'date_embauche' => now()->toDateString(),
        'statut' => 'actif',
    ]);

    $contrat = Contrat::create([
        'employe_id' => $employe->id,
        'type' => 'CDI',
        'categorie_paie' => 'fixe',
        'date_debut' => now()->subMonth()->toDateString(),
        'salaire_base' => 2000.0,
        'statut' => 'actif',
    ]);

    $employe->update(['contrat_id' => $contrat->id]);

    $this->employeService->promouvoir($employe->id, $nouveauPoste->id, 'Promotion au merite', 3000.0, null);

    $employe->refresh();
    $contrat->refresh();

    expect($employe->poste_administratif_id)->toBe($nouveauPoste->id);
    expect((float) $contrat->salaire_base)->toBe(3000.0);

    // Vérifier l'historique carrière
    $ligneCarriere = $employe->historiqueCarriere()->first();
    expect($ligneCarriere->evenement)->toBe('promotion');
    expect($ligneCarriere->ancien_poste)->toBe($ancienPoste->nom);
    expect($ligneCarriere->nouveau_poste)->toBe($nouveauPoste->nom);

    // Vérifier ContratFonctionRemuneration
    expect($contrat->fonctionsRemunerees()->count())->toBe(1);
    $rem = $contrat->fonctionsRemunerees()->first();
    expect((float) $rem->montant)->toBe(3000.0);
    expect($rem->poste_administratif_id)->toBe($nouveauPoste->id);
});

test('changement de niveau met a jour le user via UtilisateurServiceInterface', function () {
    $user = User::create([
        'name' => 'Jean Martin',
        'email' => 'jean.martin@example.com',
        'password' => bcrypt('password'),
    ]);

    $ancienPoste = PosteAdministratif::create(['nom' => 'Ingenieur 1', 'actif' => true]);
    $nouveauPoste = PosteAdministratif::create(['nom' => 'Ingenieur 2', 'actif' => true]);

    $niveauNouveau = Niveau::create(['code' => 'N3', 'nom' => 'Niveau 3']);

    $employe = Employe::create([
        'matricule' => 'EMP-004',
        'nom' => 'Martin',
        'prenom' => 'Jean',
        'email' => 'jean.martin@example.com',
        'user_id' => $user->id,
        'poste_administratif_id' => $ancienPoste->id,
        'poste' => $ancienPoste->nom,
        'date_embauche' => now()->toDateString(),
        'statut' => 'actif',
    ]);

    $this->employeService->muter($employe->id, $nouveauPoste->id, $niveauNouveau->id, 'Mutation avec passage niveau');

    $user->refresh();
    expect($user->niveau_id)->toBe($niveauNouveau->id);
});

test('muter un employe sans compte User fonctionne sans erreur', function () {
    $ancienPoste = PosteAdministratif::create(['nom' => 'Tech 1', 'actif' => true]);
    $nouveauPoste = PosteAdministratif::create(['nom' => 'Tech 2', 'actif' => true]);

    $employe = Employe::create([
        'matricule' => 'EMP-005',
        'nom' => 'SansUser',
        'prenom' => 'Marc',
        'email' => 'marc.sansuser@example.com',
        'user_id' => null,
        'poste_administratif_id' => $ancienPoste->id,
        'poste' => $ancienPoste->nom,
        'date_embauche' => now()->toDateString(),
        'statut' => 'actif',
    ]);

    $this->employeService->muter($employe->id, $nouveauPoste->id, null, 'Mutation sans user');

    $employe->refresh();
    expect($employe->poste_administratif_id)->toBe($nouveauPoste->id);
    expect($employe->user_id)->toBeNull();
});

test('mutation ou promotion ne modifie pas Employe.statut ni User.statut', function () {
    $user = User::create([
        'name' => 'Statut Check',
        'email' => 'statut.check@example.com',
        'password' => bcrypt('password'),
        'statut' => 'actif',
    ]);

    $p1 = PosteAdministratif::create(['nom' => 'Poste S1', 'actif' => true]);
    $p2 = PosteAdministratif::create(['nom' => 'Poste S2', 'actif' => true]);

    $employe = Employe::create([
        'matricule' => 'EMP-006',
        'nom' => 'Check',
        'prenom' => 'Statut',
        'email' => 'statut.check@example.com',
        'user_id' => $user->id,
        'poste_administratif_id' => $p1->id,
        'poste' => $p1->nom,
        'date_embauche' => now()->toDateString(),
        'statut' => 'suspendu',
    ]);

    $this->employeService->muter($employe->id, $p2->id, null, 'Test de preservation des statuts');

    $employe->refresh();
    $user->refresh();

    expect($employe->statut)->toBe('suspendu');
    expect($user->statut)->toBe('actif');
});
