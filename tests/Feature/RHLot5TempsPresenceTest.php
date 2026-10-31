<?php

use App\Models\User;
use App\Modules\RH\Contracts\AbsenceServiceContract;
use App\Modules\RH\Contracts\CongeServiceContract;
use App\Modules\RH\Contracts\PointageServiceContract;
use App\Modules\RH\Exceptions\ChevauchementCongeException;
use App\Modules\RH\Exceptions\PointageDejaExistantException;
use App\Modules\RH\Models\AbsencePersonnel;
use App\Modules\RH\Models\Conge;
use App\Modules\RH\Models\Contrat;
use App\Modules\RH\Models\Employe;
use App\Modules\RH\Models\Pointage;
use App\Modules\Socle\Models\Document;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->congeService = app(CongeServiceContract::class);
    $this->pointageService = app(PointageServiceContract::class);
    $this->absenceService = app(AbsenceServiceContract::class);
    $this->admin = User::firstOrCreate(['email' => 'admin.test@example.com'], [
        'name' => 'Admin Test',
        'password' => bcrypt('password'),
    ]);
});

test('1. un employe sans compte User peut avoir un conge, une absence et un pointage', function () {
    $employe = Employe::create([
        'matricule' => 'EMP-TEST-01',
        'nom' => 'Kouam',
        'prenom' => 'Pierre',
        'email' => 'pierre.kouam@example.com',
        'user_id' => null,
        'poste' => 'Assistant',
        'date_embauche' => now()->toDateString(),
        'statut' => 'actif',
    ]);

    // Congé
    $conge = $this->congeService->demanderConge(
        $employe->id,
        'permission_exceptionnelle',
        now()->addDays(2),
        now()->addDays(4),
        'Événement familial'
    );
    expect($conge->employe_id)->toBe($employe->id);

    // Absence
    $absence = $this->absenceService->enregistrerAbsence(
        $employe->id,
        now()->addDays(10),
        now()->addDays(11),
        'absence_justifiee',
        'Rendez-vous médical',
        null,
        'en_attente',
        $this->admin->id
    );
    expect($absence->employe_id)->toBe($employe->id);

    // Pointage
    $datePointage = now()->addDays(1);
    $this->pointageService->enregistrerPointage(
        $employe->id,
        $datePointage->copy()->setTime(8, 0, 0),
        'arrivee',
        'manuel'
    );
    $pointage = Pointage::where('employe_id', $employe->id)->whereDate('date_pointage', $datePointage->toDateString())->first();
    expect($pointage)->not->toBeNull();
    expect($pointage->heure_arrivee)->toBe('08:00:00');
});

test('2. validation des dates de conge : fin doit etre >= debut', function () {
    $employe = Employe::create([
        'matricule' => 'EMP-TEST-02',
        'nom' => 'Mballa',
        'prenom' => 'Alice',
        'email' => 'alice.mballa@example.com',
        'poste' => 'Secrétaire',
        'date_embauche' => now()->toDateString(),
        'statut' => 'actif',
    ]);

    $debut = now()->addDays(5);
    $finInvalide = now()->addDays(2); // fin antérieure

    expect(fn () => $this->congeService->demanderConge(
        $employe->id,
        'permission_exceptionnelle',
        $debut,
        $finInvalide,
        'Test date invalide'
    ))->toThrow(InvalidArgumentException::class);
});

test('3. chevauchement de conge incoherent bloque', function () {
    $employe = Employe::create([
        'matricule' => 'EMP-TEST-03',
        'nom' => 'Ngo',
        'prenom' => 'Jeanne',
        'email' => 'jeanne.ngo@example.com',
        'poste' => 'Gestionnaire',
        'date_embauche' => now()->toDateString(),
        'statut' => 'actif',
    ]);

    $debut1 = Carbon::create(2026, 11, 10);
    $fin1 = Carbon::create(2026, 11, 15);

    $this->congeService->demanderConge(
        $employe->id,
        'permission_exceptionnelle',
        $debut1,
        $fin1,
        'Premier congé'
    );

    $debutChevauchant = Carbon::create(2026, 11, 12);
    $finChevauchant = Carbon::create(2026, 11, 18);

    expect(fn () => $this->congeService->demanderConge(
        $employe->id,
        'permission_exceptionnelle',
        $debutChevauchant,
        $finChevauchant,
        'Congé chevauchant'
    ))->toThrow(ChevauchementCongeException::class);
});

test('4. absence rattachee au bon employe', function () {
    $emp1 = Employe::create([
        'matricule' => 'EMP-A-01',
        'nom' => 'Emp1',
        'prenom' => 'P1',
        'email' => 'p1@example.com',
        'poste' => 'Poste 1',
        'date_embauche' => now()->toDateString(),
        'statut' => 'actif',
    ]);
    $emp2 = Employe::create([
        'matricule' => 'EMP-A-02',
        'nom' => 'Emp2',
        'prenom' => 'P2',
        'email' => 'p2@example.com',
        'poste' => 'Poste 2',
        'date_embauche' => now()->toDateString(),
        'statut' => 'actif',
    ]);

    $absence = $this->absenceService->enregistrerAbsence(
        $emp1->id,
        now()->addDays(1),
        now()->addDays(2),
        'absence_justifiee',
        'Motif emp 1',
        null,
        'en_attente',
        $this->admin->id
    );

    expect($absence->employe_id)->toBe($emp1->id);
    expect($absence->employe->matricule)->toBe('EMP-A-01');
    expect(AbsencePersonnel::where('employe_id', $emp2->id)->count())->toBe(0);
});

test('5. justifications conservees pour conge et absence', function () {
    $employe = Employe::create([
        'matricule' => 'EMP-J-01',
        'nom' => 'Tchinda',
        'prenom' => 'Gilles',
        'email' => 'gilles.tchinda@example.com',
        'poste' => 'Comptable',
        'date_embauche' => now()->toDateString(),
        'statut' => 'actif',
    ]);

    $doc = Document::create([
        'nom' => 'Justificatif.pdf',
        'categorie' => 'justificatif',
        'fichier_path' => 'docs/justif.pdf',
        'mime_type' => 'application/pdf',
        'taille' => 1024,
        'documentable_type' => Employe::class,
        'documentable_id' => $employe->id,
        'created_by' => $this->admin->id,
    ]);

    $conge = $this->congeService->demanderConge(
        $employe->id,
        'permission_exceptionnelle',
        now()->addDays(5),
        now()->addDays(6),
        'Mariage civil',
        $doc->id
    );

    expect($conge->motif)->toBe('Mariage civil');
    expect($conge->justificatif_document_id)->toBe($doc->id);

    $absence = $this->absenceService->enregistrerAbsence(
        $employe->id,
        now()->addDays(10),
        now()->addDays(11),
        'absence_justifiee',
        'Certificat hospitalisation',
        $doc->id,
        'en_attente',
        $this->admin->id
    );

    expect($absence->motif)->toBe('Certificat hospitalisation');
    expect($absence->piece_justificative_document_id)->toBe($doc->id);
});

test('6. pointage arrivee et depart coherent : depart doit etre >= arrivee', function () {
    $employe = Employe::create([
        'matricule' => 'EMP-P-01',
        'nom' => 'Fotso',
        'prenom' => 'Alain',
        'email' => 'alain.fotso@example.com',
        'poste' => 'Technicien',
        'date_embauche' => now()->toDateString(),
        'statut' => 'actif',
    ]);

    $dateJour = now();

    // Arrivée à 08:30
    $this->pointageService->enregistrerPointage(
        $employe->id,
        $dateJour->copy()->setTime(8, 30, 0),
        'arrivee',
        'manuel'
    );

    // Tentative de départ à 07:00 (incohérent car antérieur à l'arrivée)
    expect(fn () => $this->pointageService->enregistrerPointage(
        $employe->id,
        $dateJour->copy()->setTime(7, 0, 0),
        'depart',
        'manuel'
    ))->toThrow(InvalidArgumentException::class);

    // Départ valide à 17:00
    $this->pointageService->enregistrerPointage(
        $employe->id,
        $dateJour->copy()->setTime(17, 0, 0),
        'depart',
        'manuel'
    );

    $pointage = Pointage::where('employe_id', $employe->id)->whereDate('date_pointage', $dateJour->toDateString())->first();
    expect($pointage->heure_arrivee)->toBe('08:30:00');
    expect($pointage->heure_depart)->toBe('17:00:00');
});

test('7. doublon d arrivee de pointage incoherent bloque', function () {
    $employe = Employe::create([
        'matricule' => 'EMP-P-02',
        'nom' => 'Ewondo',
        'prenom' => 'Samuel',
        'email' => 'samuel.ewondo@example.com',
        'poste' => 'Chauffeur',
        'date_embauche' => now()->toDateString(),
        'statut' => 'actif',
    ]);

    $dateJour = now();

    $this->pointageService->enregistrerPointage(
        $employe->id,
        $dateJour->copy()->setTime(7, 50, 0),
        'arrivee',
        'manuel'
    );

    // Seconde arrivée le même jour -> exception PointageDejaExistantException
    expect(fn () => $this->pointageService->enregistrerPointage(
        $employe->id,
        $dateJour->copy()->setTime(8, 10, 0),
        'arrivee',
        'manuel'
    ))->toThrow(PointageDejaExistantException::class);
});

test('8. conge n altere pas User.statut ni Employe.statut', function () {
    $user = User::create([
        'name' => 'Utilisateur Conge',
        'email' => 'user.conge@example.com',
        'password' => bcrypt('password'),
        'statut' => 'actif',
    ]);

    $employe = Employe::create([
        'matricule' => 'EMP-ST-01',
        'nom' => 'Abena',
        'prenom' => 'Thérèse',
        'email' => 'user.conge@example.com',
        'user_id' => $user->id,
        'poste' => 'Assistante RH',
        'date_embauche' => now()->toDateString(),
        'statut' => 'actif',
    ]);

    $conge = $this->congeService->demanderConge(
        $employe->id,
        'permission_exceptionnelle',
        now()->addDays(1),
        now()->addDays(3),
        'Congé test'
    );

    $this->congeService->approuver($conge->id, $user->id, 'Approuvé');

    $user->refresh();
    $employe->refresh();

    expect($user->statut)->toBe('actif');
    expect($employe->statut)->toBe('actif');
});

test('9. absence n altere pas User.statut ni Employe.statut', function () {
    $user = User::create([
        'name' => 'Utilisateur Absence',
        'email' => 'user.absence@example.com',
        'password' => bcrypt('password'),
        'statut' => 'actif',
    ]);

    $employe = Employe::create([
        'matricule' => 'EMP-ST-02',
        'nom' => 'Bikoro',
        'prenom' => 'Roger',
        'email' => 'user.absence@example.com',
        'user_id' => $user->id,
        'poste' => 'Surveillant',
        'date_embauche' => now()->toDateString(),
        'statut' => 'actif',
    ]);

    $this->absenceService->enregistrerAbsence(
        $employe->id,
        now()->addDays(1),
        now()->addDays(2),
        'absence_non_justifiee',
        'Non justifiée',
        null,
        'validee',
        $user->id
    );

    $user->refresh();
    $employe->refresh();

    expect($user->statut)->toBe('actif');
    expect($employe->statut)->toBe('actif');
});

test('10. pointage n altere pas User.statut ni Employe.statut', function () {
    $user = User::create([
        'name' => 'Utilisateur Pointage',
        'email' => 'user.pointage@example.com',
        'password' => bcrypt('password'),
        'statut' => 'actif',
    ]);

    $employe = Employe::create([
        'matricule' => 'EMP-ST-03',
        'nom' => 'Kamga',
        'prenom' => 'Luc',
        'email' => 'user.pointage@example.com',
        'user_id' => $user->id,
        'poste' => 'Agent',
        'date_embauche' => now()->toDateString(),
        'statut' => 'actif',
    ]);

    $this->pointageService->enregistrerPointage(
        $employe->id,
        now()->setTime(8, 0, 0),
        'arrivee',
        'manuel'
    );

    $user->refresh();
    $employe->refresh();

    expect($user->statut)->toBe('actif');
    expect($employe->statut)->toBe('actif');
});

test('11. Contrat.statut reste inchange lors des operations de conge, absence et pointage', function () {
    $employe = Employe::create([
        'matricule' => 'EMP-CTR-01',
        'nom' => 'Essomba',
        'prenom' => 'Patrice',
        'email' => 'patrice.essomba@example.com',
        'poste' => 'Juriste',
        'date_embauche' => now()->toDateString(),
        'statut' => 'actif',
    ]);

    $contrat = Contrat::create([
        'employe_id' => $employe->id,
        'type' => 'CDI',
        'categorie_paie' => 'fixe',
        'date_debut' => now()->subMonths(2)->toDateString(),
        'salaire_base' => 350000,
        'statut' => 'actif',
    ]);

    $employe->update(['contrat_id' => $contrat->id]);

    $this->congeService->demanderConge($employe->id, 'permission_exceptionnelle', now()->addDays(1), now()->addDays(2), 'Motif');
    $this->absenceService->enregistrerAbsence($employe->id, now()->addDays(5), now()->addDays(6), 'absence_justifiee', 'Motif', null, 'validee');
    $this->pointageService->enregistrerPointage($employe->id, now()->setTime(8, 0, 0), 'arrivee', 'manuel');

    $contrat->refresh();
    expect($contrat->statut)->toBe('actif');
});

test('12. absence justifiee et conge autorise ne sont pas comptes comme absence non justifiee dans le rapport de pointage', function () {
    $employe = Employe::create([
        'matricule' => 'EMP-COH-01',
        'nom' => 'Ondoa',
        'prenom' => 'Fabrice',
        'email' => 'fabrice.ondoa@example.com',
        'poste' => 'Opérateur',
        'date_embauche' => now()->toDateString(),
        'statut' => 'actif',
    ]);

    // Jour ouvré 1: Conge approuvé (2 Nov 2026 est un Lundi)
    $conge = $this->congeService->demanderConge(
        $employe->id,
        'permission_exceptionnelle',
        Carbon::create(2026, 11, 2),
        Carbon::create(2026, 11, 2),
        'Congé jour ouvré'
    );
    $this->congeService->approuver($conge->id, $this->admin->id);

    // Jour ouvré 2: Absence justifiée validée (3 Nov 2026 est un Mardi)
    $this->absenceService->enregistrerAbsence(
        $employe->id,
        Carbon::create(2026, 11, 3),
        Carbon::create(2026, 11, 3),
        'absence_justifiee',
        'Mission autorisée',
        null,
        'validee',
        $this->admin->id
    );

    // Jour ouvré 3: Présence pointée (4 Nov 2026 est un Mercredi)
    $this->pointageService->enregistrerPointage(
        $employe->id,
        Carbon::create(2026, 11, 4, 8, 0, 0),
        'arrivee',
        'manuel'
    );

    // Calculer les absences non justifiées du mois de novembre 2026
    $absencesNonJustifiees = $this->pointageService->getJoursAbsenceNonJustifiee($employe->id, 11, 2026);

    // Le total des jours ouvrables de nov 2026 sans le jour 2 (congé), jour 3 (absence justifiée), et jour 4 (pointé)
    $totalJoursOuvrables = $this->pointageService->genererRapportMensuel($employe->id, 11, 2026)->total_jours_ouvrables;
    expect($absencesNonJustifiees)->toBe($totalJoursOuvrables - 3);
});
