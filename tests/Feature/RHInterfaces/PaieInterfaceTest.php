<?php

declare(strict_types=1);

namespace Tests\Feature\RHInterfaces;

use App\Models\User;
use App\Modules\Finances\Contracts\CaisseServiceContract;
use App\Modules\RH\Filament\Resources\BulletinPaieResource;
use App\Modules\RH\Filament\Resources\BulletinPaieResource\Pages\CreateBulletinPaie;
use App\Modules\RH\Filament\Resources\BulletinPaieResource\Pages\ListBulletinPaies;
use App\Modules\RH\Models\BulletinPaie;
use App\Modules\RH\Models\Contrat;
use App\Modules\RH\Models\Employe;
use App\Modules\RH\Models\Pointage;
use App\Modules\RH\Models\Prime;
use App\Modules\RH\Models\TypePrime;
use App\Modules\Socle\Models\AnneeScolaire;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PaieInterfaceTest extends TestCase
{
    use RefreshDatabase;

    private User $fondateurUser;

    private User $comptableUser;

    private AnneeScolaire $anneeScolaire;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->fondateurUser = User::factory()->create(['statut' => 'actif']);
        $this->fondateurUser->assignRole('Fondateur');

        Permission::firstOrCreate(['name' => 'module.rh']);
        Role::firstOrCreate(['name' => 'Comptable', 'guard_name' => 'web']);
        $this->comptableUser = User::factory()->create(['statut' => 'actif']);
        $this->comptableUser->assignRole('Comptable');
        $this->comptableUser->givePermissionTo('module.rh');

        $this->anneeScolaire = AnneeScolaire::create([
            'libelle' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-07-31',
            'statut' => 'active',
        ]);
    }

    private function creerEmploye(string $matricule, string $nom, string $prenom): Employe
    {
        return Employe::query()->create([
            'matricule' => $matricule,
            'nom' => $nom,
            'prenom' => $prenom,
            'poste' => 'Agent Administratif',
            'date_embauche' => '2025-01-01',
            'statut' => 'actif',
        ]);
    }

    private function creerContrat(Employe $employe, array $attributes = []): Contrat
    {
        $contrat = Contrat::query()->create(array_merge([
            'employe_id' => $employe->id,
            'type' => 'CDI',
            'categorie_paie' => 'fixe',
            'salaire_base' => 200000,
            'date_debut' => '2025-01-01',
            'statut' => 'actif',
        ], $attributes));

        $employe->update(['contrat_id' => $contrat->id]);

        return $contrat;
    }

    public function test_acces_page_bulletins_selon_permission(): void
    {
        // 1. Utilisateur non habilité -> 403
        $unauthorized = User::factory()->create(['statut' => 'actif']);
        $this->actingAs($unauthorized);
        $this->get(BulletinPaieResource::getUrl())->assertForbidden();

        // 2. Comptable avec habilitation RH -> 200
        $this->actingAs($this->comptableUser);
        $this->get(BulletinPaieResource::getUrl())->assertSuccessful();

        // 3. Fondateur -> 200
        $this->actingAs($this->fondateurUser);
        $this->get(BulletinPaieResource::getUrl())->assertSuccessful();
    }

    public function test_liste_bulletins_recherche_et_filtres(): void
    {
        $this->actingAs($this->fondateurUser);

        $emp1 = $this->creerEmploye('EMP-PAIE-01', 'Essomba', 'Michel');
        $contrat1 = $this->creerContrat($emp1, ['salaire_base' => 220000]);

        $emp2 = $this->creerEmploye('EMP-PAIE-02', 'Ngono', 'Chantal');
        $contrat2 = $this->creerContrat($emp2, ['salaire_base' => 180000]);

        $bulletin1 = BulletinPaie::query()->create([
            'employe_id' => $emp1->id,
            'contrat_id' => $contrat1->id,
            'mois' => 9,
            'annee' => 2026,
            'annee_scolaire_id' => $this->anneeScolaire->id,
            'periode_debut' => '2026-09-01',
            'periode_fin' => '2026-09-30',
            'salaire_base' => 220000,
            'net_a_payer' => 210760,
            'statut' => 'calcule',
        ]);

        $bulletin2 = BulletinPaie::query()->create([
            'employe_id' => $emp2->id,
            'contrat_id' => $contrat2->id,
            'mois' => 9,
            'annee' => 2026,
            'annee_scolaire_id' => $this->anneeScolaire->id,
            'periode_debut' => '2026-09-01',
            'periode_fin' => '2026-09-30',
            'salaire_base' => 180000,
            'net_a_payer' => 172440,
            'statut' => 'valide',
        ]);

        Livewire::test(ListBulletinPaies::class)
            ->assertCanSeeTableRecords([$bulletin1, $bulletin2])
            ->searchTable('Essomba')
            ->assertCanSeeTableRecords([$bulletin1])
            ->assertCanNotSeeTableRecords([$bulletin2])
            ->searchTable('')
            ->filterTable('statut', 'valide')
            ->assertCanSeeTableRecords([$bulletin2])
            ->assertCanNotSeeTableRecords([$bulletin1])
            ->filterTable('statut', null)
            ->filterTable('mois', 9)
            ->assertCanSeeTableRecords([$bulletin1, $bulletin2]);
    }

    public function test_generation_calcul_bulletin_fixe_depuis_interface(): void
    {
        $this->actingAs($this->fondateurUser);

        $employe = $this->creerEmploye('EMP-CALC-FIX', 'Atangana', 'Luc');
        $this->creerContrat($employe, [
            'categorie_paie' => 'fixe',
            'salaire_base' => 250000,
        ]);

        Livewire::test(ListBulletinPaies::class)
            ->callAction('calculer_bulletin', data: [
                'employe_id' => $employe->id,
                'periode_debut' => '2026-09-01',
                'periode_fin' => '2026-09-30',
            ])
            ->assertHasNoActionErrors()
            ->assertNotified('Bulletin calculé avec succès');

        $this->assertDatabaseHas('bulletins_paie', [
            'employe_id' => $employe->id,
            'mois' => 9,
            'annee' => 2026,
            'salaire_base' => 250000,
            'statut' => 'calcule',
        ]);

        $bulletin = BulletinPaie::where('employe_id', $employe->id)->first();
        $this->assertNotNull($bulletin);
        $this->assertGreaterThan(0, (float) $bulletin->net_a_payer);
        $this->assertGreaterThan(0, (float) $bulletin->total_cotisations);
    }

    public function test_generation_calcul_bulletin_horaire_et_mixte(): void
    {
        $this->actingAs($this->fondateurUser);

        // 1. Rémunération Horaire avec pointages
        $empHoraire = $this->creerEmploye('EMP-CALC-HOR', 'Manga', 'Paul');
        $this->creerContrat($empHoraire, [
            'type' => 'Prestataire',
            'categorie_paie' => 'horaire',
            'salaire_base' => 0,
            'taux_horaire' => 5000,
        ]);

        // 2 pointages de 4 heures = 8 heures
        Pointage::create([
            'employe_id' => $empHoraire->id,
            'date_pointage' => '2026-09-05',
            'heure_arrivee' => '08:00',
            'heure_depart' => '12:00',
            'mode_pointage' => 'manuel',
        ]);
        Pointage::create([
            'employe_id' => $empHoraire->id,
            'date_pointage' => '2026-09-06',
            'heure_arrivee' => '14:00',
            'heure_depart' => '18:00',
            'mode_pointage' => 'manuel',
        ]);

        Livewire::test(ListBulletinPaies::class)
            ->callAction('calculer_bulletin', data: [
                'employe_id' => $empHoraire->id,
                'periode_debut' => '2026-09-01',
                'periode_fin' => '2026-09-30',
            ])
            ->assertHasNoActionErrors();

        $bulletinHoraire = BulletinPaie::where('employe_id', $empHoraire->id)->first();
        $this->assertNotNull($bulletinHoraire);
        // 8h * 5000 = 40 000 FCFA base
        $this->assertSame(40000.0, (float) $bulletinHoraire->salaire_base);

        // 2. Rémunération Mixte
        $empMixte = $this->creerEmploye('EMP-CALC-MIX', 'Owona', 'Béatrice');
        $this->creerContrat($empMixte, [
            'type' => 'CDD',
            'categorie_paie' => 'mixte',
            'salaire_base' => 120000,
            'taux_horaire' => 4000,
        ]);

        Pointage::create([
            'employe_id' => $empMixte->id,
            'date_pointage' => '2026-09-10',
            'heure_arrivee' => '08:00',
            'heure_depart' => '13:00', // 5 heures = 20 000 FCFA
            'mode_pointage' => 'manuel',
        ]);

        Livewire::test(ListBulletinPaies::class)
            ->callAction('calculer_bulletin', data: [
                'employe_id' => $empMixte->id,
                'periode_debut' => '2026-09-01',
                'periode_fin' => '2026-09-30',
            ])
            ->assertHasNoActionErrors();

        $bulletinMixte = BulletinPaie::where('employe_id', $empMixte->id)->first();
        $this->assertNotNull($bulletinMixte);
        // 120 000 fixe + 20 000 horaire = 140 000 FCFA base
        $this->assertSame(140000.0, (float) $bulletinMixte->salaire_base);
    }

    public function test_integration_primes_et_retenues_non_dupliquees(): void
    {
        $this->actingAs($this->fondateurUser);

        $employe = $this->creerEmploye('EMP-PRIME-01', 'Tchoupo', 'Alain');
        $this->creerContrat($employe, ['salaire_base' => 200000]);

        $typePrime = TypePrime::query()->create([
            'libelle' => 'Prime de performance',
            'code' => 'PERF_2026',
            'mode_calcul' => 'montant_fixe',
            'montant_defaut' => 35000,
            'imposable' => false,
            'actif' => true,
        ]);

        $prime = Prime::query()->create([
            'employe_id' => $employe->id,
            'type_prime_id' => $typePrime->id,
            'montant' => 35000,
            'mois' => 9,
            'annee' => 2026,
            'date_attribution' => '2026-09-15',
            'statut' => 'validee',
            'motif' => 'Excellents résultats trimestriels',
            'proposee_par' => $this->fondateurUser->id,
        ]);

        Livewire::test(ListBulletinPaies::class)
            ->callAction('calculer_bulletin', data: [
                'employe_id' => $employe->id,
                'periode_debut' => '2026-09-01',
                'periode_fin' => '2026-09-30',
            ])
            ->assertHasNoActionErrors();

        $bulletin = BulletinPaie::where('employe_id', $employe->id)->first();
        $this->assertNotNull($bulletin);
        $this->assertSame(35000.0, (float) $bulletin->total_primes);

        // La prime est rattachée au bulletin
        $this->assertSame($bulletin->id, $prime->fresh()->bulletin_paie_id);
    }

    public function test_validation_bulletin_verrouille_modification_et_suppression(): void
    {
        $this->actingAs($this->fondateurUser);

        $employe = $this->creerEmploye('EMP-VAL-01', 'Kenfack', 'Gérard');
        $contrat = $this->creerContrat($employe, ['salaire_base' => 190000]);

        $bulletin = BulletinPaie::query()->create([
            'employe_id' => $employe->id,
            'contrat_id' => $contrat->id,
            'mois' => 9,
            'annee' => 2026,
            'annee_scolaire_id' => $this->anneeScolaire->id,
            'periode_debut' => '2026-09-01',
            'periode_fin' => '2026-09-30',
            'salaire_base' => 190000,
            'net_a_payer' => 182020,
            'statut' => 'calcule',
        ]);

        // 1. Validation via l'action du tableau
        Livewire::test(ListBulletinPaies::class)
            ->callTableAction('valider', $bulletin)
            ->assertHasNoTableActionErrors()
            ->assertNotified('Bulletin validé avec succès');

        $this->assertSame('valide', $bulletin->fresh()->statut);

        // 2. Modification interdite (403 si tentative d'accès)
        $this->get(BulletinPaieResource::getUrl('edit', ['record' => $bulletin]))
            ->assertForbidden();

        // 3. canEdit et canDelete retournent false
        $this->assertFalse(BulletinPaieResource::canEdit($bulletin->fresh()));
        $this->assertFalse(BulletinPaieResource::canDelete($bulletin->fresh()));
    }

    public function test_paiement_bulletin_succes_finance_et_statut_paye(): void
    {
        $this->actingAs($this->fondateurUser);

        $employe = $this->creerEmploye('EMP-PAY-01', 'Nkomo', 'Pierre');
        $contrat = $this->creerContrat($employe, ['salaire_base' => 300000]);

        $bulletin = BulletinPaie::query()->create([
            'employe_id' => $employe->id,
            'contrat_id' => $contrat->id,
            'mois' => 9,
            'annee' => 2026,
            'annee_scolaire_id' => $this->anneeScolaire->id,
            'periode_debut' => '2026-09-01',
            'periode_fin' => '2026-09-30',
            'salaire_base' => 300000,
            'net_a_payer' => 287400,
            'statut' => 'valide',
        ]);

        $this->mock(CaisseServiceContract::class, function ($mock) use ($bulletin): void {
            $mock->shouldReceive('enregistrerMouvement')
                ->once()
                ->with('decaissement', 287400.0, \Mockery::on(fn ($b) => $b->id === $bulletin->id), null, 'Salaires', 'RH', 'Salaires')
                ->andReturn((object) ['id' => 999]);
        });

        Livewire::test(ListBulletinPaies::class)
            ->callTableAction('payer', $bulletin)
            ->assertHasNoTableActionErrors()
            ->assertNotified('Bulletin payé et décaissement Finance enregistré');

        $bulletinPaye = $bulletin->fresh();
        $this->assertSame('paye', $bulletinPaye->statut);
        $this->assertNotNull($bulletinPaye->date_paiement);
    }

    public function test_echec_paiement_finance_laisse_bulletin_non_paye(): void
    {
        $this->actingAs($this->fondateurUser);

        $employe = $this->creerEmploye('EMP-FAIL-01', 'Mebenga', 'Guy');
        $contrat = $this->creerContrat($employe, ['salaire_base' => 210000]);

        $bulletin = BulletinPaie::query()->create([
            'employe_id' => $employe->id,
            'contrat_id' => $contrat->id,
            'mois' => 9,
            'annee' => 2026,
            'annee_scolaire_id' => $this->anneeScolaire->id,
            'periode_debut' => '2026-09-01',
            'periode_fin' => '2026-09-30',
            'salaire_base' => 210000,
            'net_a_payer' => 201180,
            'statut' => 'valide',
        ]);

        // Simuler un refus de la Caisse Finance (solde insuffisant)
        $this->mock(CaisseServiceContract::class, function ($mock): void {
            $mock->shouldReceive('enregistrerMouvement')
                ->once()
                ->andThrow(new \DomainException('Solde de caisse insuffisant pour régler les salaires.'));
        });

        Livewire::test(ListBulletinPaies::class)
            ->callTableAction('payer', $bulletin)
            ->assertNotified('Échec du paiement');

        // Le bulletin doit impérativement rester 'valide'
        $this->assertSame('valide', $bulletin->fresh()->statut);
        $this->assertNull($bulletin->fresh()->date_paiement);
    }

    public function test_double_paiement_impossible(): void
    {
        $this->actingAs($this->fondateurUser);

        $employe = $this->creerEmploye('EMP-DBLPAY-01', 'Ndjock', 'Joseph');
        $contrat = $this->creerContrat($employe, ['salaire_base' => 170000]);

        $bulletin = BulletinPaie::query()->create([
            'employe_id' => $employe->id,
            'contrat_id' => $contrat->id,
            'mois' => 9,
            'annee' => 2026,
            'annee_scolaire_id' => $this->anneeScolaire->id,
            'periode_debut' => '2026-09-01',
            'periode_fin' => '2026-09-30',
            'salaire_base' => 170000,
            'net_a_payer' => 162860,
            'statut' => 'paye',
            'date_paiement' => '2026-09-30',
        ]);

        // L'action payer n'est pas visible
        Livewire::test(ListBulletinPaies::class)
            ->assertTableActionHidden('payer', $bulletin);
    }

    public function test_telechargement_et_impression_pdf_selon_permission(): void
    {
        $employe = $this->creerEmploye('EMP-PDF-01', 'Ewodo', 'Marc');
        $contrat = $this->creerContrat($employe, ['salaire_base' => 150000]);

        $bulletin = BulletinPaie::query()->create([
            'employe_id' => $employe->id,
            'contrat_id' => $contrat->id,
            'mois' => 9,
            'annee' => 2026,
            'annee_scolaire_id' => $this->anneeScolaire->id,
            'periode_debut' => '2026-09-01',
            'periode_fin' => '2026-09-30',
            'salaire_base' => 150000,
            'net_a_payer' => 143700,
            'statut' => 'valide',
        ]);

        // 1. Utilisateur non habilité -> 403
        $unauthorized = User::factory()->create(['statut' => 'actif']);
        $this->actingAs($unauthorized);
        $this->get(route('rh.bulletins-paie.telecharger', $bulletin))->assertForbidden();
        $this->get(route('rh.bulletins-paie.imprimer', $bulletin))->assertForbidden();

        // 2. Utilisateur habilité -> 200 avec headers PDF
        $this->actingAs($this->fondateurUser);
        $respTelecharger = $this->get(route('rh.bulletins-paie.telecharger', $bulletin));
        $respTelecharger->assertSuccessful();
        $this->assertSame('application/pdf', $respTelecharger->headers->get('Content-Type'));

        $respImprimer = $this->get(route('rh.bulletins-paie.imprimer', $bulletin));
        $respImprimer->assertSuccessful();
        $this->assertSame('application/pdf', $respImprimer->headers->get('Content-Type'));
    }

    public function test_erreurs_ui_gerees_sans_exception_brute(): void
    {
        $this->actingAs($this->fondateurUser);

        $employe = $this->creerEmploye('EMP-ERR-01', 'Zambo', 'Serge');
        $this->creerContrat($employe, ['salaire_base' => 180000]);

        // 1. Période invalide (fin < début)
        Livewire::test(ListBulletinPaies::class)
            ->callAction('calculer_bulletin', data: [
                'employe_id' => $employe->id,
                'periode_debut' => '2026-09-30',
                'periode_fin' => '2026-09-01',
            ])
            ->assertHasActionErrors(['periode_fin']);

        // 2. Calcul valide d'abord
        Livewire::test(ListBulletinPaies::class)
            ->callAction('calculer_bulletin', data: [
                'employe_id' => $employe->id,
                'periode_debut' => '2026-09-01',
                'periode_fin' => '2026-09-30',
            ])
            ->assertHasNoActionErrors();

        // 3. Tentative de recalcul en doublon -> notification danger, aucune 500
        Livewire::test(ListBulletinPaies::class)
            ->callAction('calculer_bulletin', data: [
                'employe_id' => $employe->id,
                'periode_debut' => '2026-09-01',
                'periode_fin' => '2026-09-30',
            ])
            ->assertNotified('Erreur lors du calcul du bulletin');

        // 4. Tentative de création manuelle en doublon via CreateBulletinPaie
        Livewire::test(CreateBulletinPaie::class)
            ->fillForm([
                'employe_id' => $employe->id,
                'contrat_id' => $employe->contrat_id,
                'mois' => 9,
                'annee' => 2026,
                'salaire_base' => 180000,
                'net_a_payer' => 180000,
            ])
            ->call('create')
            ->assertNotified('Bulletin déjà existant');
    }
}
