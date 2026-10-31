<?php

use App\Models\User;
use App\Modules\Finances\Contracts\CaisseServiceContract;
use App\Modules\RH\Contracts\PaieServiceContract;
use App\Modules\RH\Exceptions\BulletinDejaExistantException;
use App\Modules\RH\Exceptions\BulletinDejaValideException;
use App\Modules\RH\Models\AbsencePersonnel;
use App\Modules\RH\Models\BulletinPaie;
use App\Modules\RH\Models\Contrat;
use App\Modules\RH\Models\Employe;
use App\Modules\RH\Models\Pointage;
use App\Modules\RH\Models\Prime;
use App\Modules\RH\Models\TypePrime;
use App\Modules\Socle\Models\AnneeScolaire;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['statut' => 'actif']);
    $this->actingAs($this->admin);
    AnneeScolaire::create([
        'libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31', 'statut' => 'active',
    ]);
});

function lot6Employe(?int $userId = null, string $matricule = 'PAIE-L6-001'): Employe
{
    return Employe::create([
        'user_id' => $userId, 'matricule' => $matricule, 'nom' => 'Paie', 'prenom' => 'Lot6',
        'date_embauche' => '2025-01-01', 'poste' => 'Agent', 'statut' => 'actif',
    ]);
}

function lot6Contrat(Employe $employe, array $attributes = []): Contrat
{
    return Contrat::create(array_merge([
        'employe_id' => $employe->id, 'type' => 'CDI', 'categorie_paie' => 'fixe',
        'date_debut' => '2025-01-01', 'salaire_base' => 100000, 'statut' => 'actif',
    ], $attributes));
}

function lot6Pointage(Employe $employe, string $date, ?string $arrivee, ?string $depart): void
{
    Pointage::create([
        'employe_id' => $employe->id, 'date_pointage' => $date,
        'heure_arrivee' => $arrivee, 'heure_depart' => $depart, 'mode_pointage' => 'manuel',
    ]);
}

function lot6Paie(): PaieServiceContract
{
    return app(PaieServiceContract::class);
}

function lot6Prime(Employe $employe, float $montant, int $mois = 9): Prime
{
    $type = TypePrime::firstOrCreate(
        ['code' => 'L6'],
        ['libelle' => 'Prime lot 6', 'mode_calcul' => 'montant_fixe', 'valeur_defaut' => 0],
    );

    return Prime::create([
        'employe_id' => $employe->id, 'type_prime_id' => $type->id, 'montant' => $montant,
        'mois' => $mois, 'annee' => 2026, 'statut' => 'validee', 'proposee_par' => User::query()->value('id'),
    ]);
}

test('1. paie fixe : salaire du contrat, cotisation sur la base, net cohérent', function () {
    $employe = lot6Employe();
    lot6Contrat($employe, ['salaire_base' => 100000]);

    $bulletin = lot6Paie()->calculerBulletin($employe->id, 9, 2026);

    expect((float) $bulletin->salaire_base)->toBe(100000.0);
    expect((float) $bulletin->total_cotisations)->toBe(4200.0);
    expect((float) $bulletin->net_a_payer)->toBe(95800.0);
    expect($bulletin->statut)->toBe('calcule');
});

test('2. paie horaire : taux du contrat x heures pointées complètes de la période', function () {
    $employe = lot6Employe();
    lot6Contrat($employe, ['categorie_paie' => 'horaire', 'salaire_base' => 0, 'taux_horaire' => 2000]);
    lot6Pointage($employe, '2026-09-01', '08:00:00', '16:00:00'); // 8h
    lot6Pointage($employe, '2026-09-02', '08:00:00', '14:30:00'); // 6,5h
    lot6Pointage($employe, '2026-09-03', '08:00:00', null);        // incomplet : ignoré
    lot6Pointage($employe, '2026-08-31', '08:00:00', '16:00:00'); // hors période : ignoré

    $bulletin = lot6Paie()->calculerBulletin($employe->id, 9, 2026);

    expect((float) $bulletin->salaire_base)->toBe(29000.0);
    expect((float) $bulletin->total_cotisations)->toBe(1218.0);
    expect((float) $bulletin->net_a_payer)->toBe(27782.0);
});

test('3. paie mixte : fixe + horaire cumulés', function () {
    $employe = lot6Employe();
    lot6Contrat($employe, ['categorie_paie' => 'mixte', 'salaire_base' => 100000, 'taux_horaire' => 2000]);
    lot6Pointage($employe, '2026-09-04', '09:00:00', '14:00:00'); // 5h

    $bulletin = lot6Paie()->calculerBulletin($employe->id, 9, 2026);

    expect((float) $bulletin->salaire_base)->toBe(110000.0);
    expect((float) $bulletin->total_cotisations)->toBe(4620.0);
    expect((float) $bulletin->net_a_payer)->toBe(105380.0);
});

test('4. une prime ponctuelle est appliquée une seule fois et libérée si le bulletin calculé est supprimé', function () {
    $employe = lot6Employe();
    lot6Contrat($employe);
    $prime = lot6Prime($employe, 10000);

    $septembre = lot6Paie()->calculerBulletin($employe->id, 9, 2026);
    expect((float) $septembre->total_primes)->toBe(10000.0);
    expect($septembre->lignes()->where('type', 'prime')->count())->toBe(1);
    expect($prime->fresh()->statut)->toBe('integree_paie');
    expect($prime->fresh()->bulletin_paie_id)->toBe($septembre->id);

    // Bulletin d'octobre dont la période recoupe septembre : la prime ne doit pas être ré-appliquée.
    $octobre = lot6Paie()->calculerBulletin($employe->id, 10, 2026, Carbon::create(2026, 9, 15), Carbon::create(2026, 10, 31));
    expect((float) $octobre->total_primes)->toBe(0.0);

    // Suppression du bulletin calculé de septembre : la prime redevient applicable (pas de perte silencieuse).
    $septembre->delete();
    expect($prime->fresh()->statut)->toBe('validee');
    expect($prime->fresh()->bulletin_paie_id)->toBeNull();
});

test('5. une retenue d absence est appliquée une seule fois (absences non justifiées validées uniquement)', function () {
    $employe = lot6Employe();
    lot6Contrat($employe);
    $creer = fn (string $debut, string $fin, string $type, string $statut) => AbsencePersonnel::create([
        'employe_id' => $employe->id, 'date_debut' => $debut, 'date_fin' => $fin, 'type' => $type,
        'motif' => 'Test', 'statut' => $statut, 'created_by' => $this->admin->id,
    ]);
    $creer('2026-09-07', '2026-09-08', 'absence_non_justifiee', 'validee'); // 2 jours ouvrés
    $creer('2026-09-11', '2026-09-13', 'absence_non_justifiee', 'validee'); // ven + week-end = 1 jour ouvré
    $creer('2026-09-09', '2026-09-09', 'absence_non_justifiee', 'en_attente'); // non validée : ignorée
    $creer('2026-09-10', '2026-09-10', 'absence_justifiee', 'validee'); // justifiée : ignorée

    $bulletin = lot6Paie()->calculerBulletin($employe->id, 9, 2026);

    expect((float) $bulletin->total_retenues)->toBe(13636.36); // 100000 / 22 x 3
    expect($bulletin->lignes()->where('libelle', 'Absences non justifiées')->count())->toBe(1);
    expect((float) $bulletin->net_a_payer)->toEqualWithDelta(82163.64, 0.01);
});

test('6. absences et pointages sont pris en compte selon les règles existantes', function () {
    $employe = lot6Employe();
    lot6Contrat($employe, ['categorie_paie' => 'mixte', 'salaire_base' => 100000, 'taux_horaire' => 1000]);
    AbsencePersonnel::create([
        'employe_id' => $employe->id, 'date_debut' => '2026-09-14', 'date_fin' => '2026-09-14',
        'type' => 'absence_non_justifiee', 'motif' => 'Test', 'statut' => 'validee', 'created_by' => $this->admin->id,
    ]);
    lot6Pointage($employe, '2026-09-15', '08:00:00', '12:00:00'); // 4h

    $bulletin = lot6Paie()->calculerBulletin($employe->id, 9, 2026);

    expect((float) $bulletin->salaire_base)->toBe(104000.0);
    expect((float) $bulletin->total_retenues)->toBe(4545.45); // 100000 / 22 x 1 (retenue sur la composante fixe)
});

test('7. le calcul est déterministe : mêmes données, même résultat', function () {
    $employe = lot6Employe();
    lot6Contrat($employe, ['categorie_paie' => 'mixte', 'salaire_base' => 120000, 'taux_horaire' => 1500]);
    lot6Pointage($employe, '2026-09-02', '08:00:00', '13:00:00');
    lot6Prime($employe, 5000);
    AbsencePersonnel::create([
        'employe_id' => $employe->id, 'date_debut' => '2026-09-16', 'date_fin' => '2026-09-17',
        'type' => 'absence_non_justifiee', 'motif' => 'Test', 'statut' => 'validee', 'created_by' => $this->admin->id,
    ]);

    $premier = lot6Paie()->calculerBulletin($employe->id, 9, 2026);
    $valeurs = $premier->only(['salaire_base', 'total_primes', 'total_retenues', 'total_cotisations', 'net_a_payer']);
    $lignes = $premier->lignes()->orderBy('id')->get(['type', 'libelle', 'montant'])->toArray();
    $premier->delete();

    $second = lot6Paie()->calculerBulletin($employe->id, 9, 2026);

    expect($second->only(['salaire_base', 'total_primes', 'total_retenues', 'total_cotisations', 'net_a_payer']))->toEqual($valeurs);
    expect($second->lignes()->orderBy('id')->get(['type', 'libelle', 'montant'])->toArray())->toEqual($lignes);
});

test('8. pas de doublon Employe + période', function () {
    $employe = lot6Employe();
    lot6Contrat($employe);

    lot6Paie()->calculerBulletin($employe->id, 9, 2026);

    expect(fn () => lot6Paie()->calculerBulletin($employe->id, 9, 2026))->toThrow(BulletinDejaExistantException::class);
    expect(BulletinPaie::where('employe_id', $employe->id)->where('mois', 9)->where('annee', 2026)->count())->toBe(1);
});

test('9. un bulletin validé est protégé : pas de recalcul, de modification ni de re-validation', function () {
    $employe = lot6Employe();
    lot6Contrat($employe);
    $bulletin = lot6Paie()->calculerBulletin($employe->id, 9, 2026);

    lot6Paie()->validerBulletin($bulletin->id, $this->admin->id);
    $bulletin->refresh();
    expect($bulletin->statut)->toBe('valide');

    expect(fn () => lot6Paie()->calculerBulletin($employe->id, 9, 2026))->toThrow(BulletinDejaExistantException::class);
    expect(fn () => $bulletin->update(['net_a_payer' => 1]))->toThrow(BulletinDejaValideException::class);
    expect(fn () => lot6Paie()->validerBulletin($bulletin->id, $this->admin->id))->toThrow(BulletinDejaValideException::class);
    expect((float) $bulletin->fresh()->net_a_payer)->toBe(95800.0);
});

test('10. un Employe sans User est payable', function () {
    $employe = lot6Employe(null);
    lot6Contrat($employe);
    $this->mock(CaisseServiceContract::class, fn ($mock) => $mock->shouldReceive('enregistrerMouvement')->once()->andReturn((object) []));

    $bulletin = lot6Paie()->calculerBulletin($employe->id, 9, 2026);
    lot6Paie()->validerBulletin($bulletin->id, $this->admin->id);
    lot6Paie()->marquerPaye($bulletin->id, today());

    expect($employe->user_id)->toBeNull();
    expect($bulletin->fresh()->statut)->toBe('paye');
});

test('11. User.statut et Employe.statut ne sont pas modifiés par la paie', function () {
    $user = User::factory()->create(['statut' => 'actif']);
    $employe = lot6Employe($user->id);
    lot6Contrat($employe);
    $this->mock(CaisseServiceContract::class, fn ($mock) => $mock->shouldReceive('enregistrerMouvement')->once()->andReturn((object) []));

    $bulletin = lot6Paie()->calculerBulletin($employe->id, 9, 2026);
    lot6Paie()->validerBulletin($bulletin->id, $this->admin->id);
    lot6Paie()->marquerPaye($bulletin->id, today());

    expect($user->fresh()->statut)->toBe('actif');
    expect($employe->fresh()->statut)->toBe('actif');
});

test('12. le Contrat est la source de la rémunération et n est pas altéré', function () {
    $employe = lot6Employe();
    $contrat = lot6Contrat($employe, ['salaire_base' => 123456]);

    $bulletin = lot6Paie()->calculerBulletin($employe->id, 9, 2026);

    expect($bulletin->contrat_id)->toBe($contrat->id);
    expect((float) $bulletin->salaire_base)->toBe(123456.0);
    expect((float) $contrat->fresh()->salaire_base)->toBe(123456.0);
    expect($contrat->fresh()->statut)->toBe('actif');
});

test('13. RH ne manipule aucune caisse : aucun accès aux modèles de caisse Finance', function () {
    $violations = collect(File::allFiles(app_path('Modules/RH')))
        ->filter(fn ($fichier) => $fichier->getExtension() === 'php')
        ->filter(fn ($fichier) => preg_match('/MouvementCaisse|SessionCaisse|Finances\\\\Models/', $fichier->getContents()))
        ->map(fn ($fichier) => $fichier->getRelativePathname())
        ->values()
        ->all();

    expect($violations)->toBe([]);
});

test('14. le paiement passe uniquement par la frontière Finance et PAYÉ n est posé qu après son succès', function () {
    $employe = lot6Employe();
    lot6Contrat($employe);
    $bulletin = lot6Paie()->calculerBulletin($employe->id, 9, 2026);

    // Non validé : Finance n'est jamais sollicitée.
    $this->mock(CaisseServiceContract::class, fn ($mock) => $mock->shouldNotReceive('enregistrerMouvement'));
    expect(fn () => lot6Paie()->marquerPaye($bulletin->id, today()))->toThrow(BulletinDejaValideException::class);
    expect($bulletin->fresh()->statut)->toBe('calcule');

    lot6Paie()->validerBulletin($bulletin->id, $this->admin->id);

    // Finance en échec : le bulletin reste VALIDÉ (pas de faux paiement).
    $this->mock(CaisseServiceContract::class, fn ($mock) => $mock->shouldReceive('enregistrerMouvement')->once()->andThrow(new RuntimeException('Caisse indisponible')));
    expect(fn () => lot6Paie()->marquerPaye($bulletin->id, today()))->toThrow(RuntimeException::class);
    expect($bulletin->fresh()->statut)->toBe('valide');
    expect($bulletin->fresh()->date_paiement)->toBeNull();

    // Finance OK : un seul mouvement de décaissement du net dû, puis PAYÉ ; un second paiement est refusé.
    $this->mock(CaisseServiceContract::class, fn ($mock) => $mock->shouldReceive('enregistrerMouvement')
        ->once()
        ->withArgs(fn ($type, $montant, $source, $justificatif, $rubrique, $module) => $type === 'decaissement'
            && abs($montant - 95800.0) < 0.01
            && $source instanceof BulletinPaie
            && $rubrique === 'Salaires'
            && $module === 'RH')
        ->andReturn((object) []));
    lot6Paie()->marquerPaye($bulletin->id, today());

    expect($bulletin->fresh()->statut)->toBe('paye');
    expect(fn () => lot6Paie()->marquerPaye($bulletin->id, today()))->toThrow(BulletinDejaValideException::class);
});
