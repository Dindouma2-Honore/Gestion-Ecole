<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\RH\Filament\Resources\BulletinPaieResource\Pages\CreateBulletinPaie;
use App\Modules\RH\Filament\Resources\PrimeResource\Pages\CreatePrime;
use App\Modules\RH\Filament\Resources\SanctionPersonnelResource\Pages\CreateSanctionPersonnel;
use App\Modules\RH\Filament\Resources\TypePrimeResource\Pages\CreateTypePrime;
use App\Modules\RH\Models\Contrat;
use App\Modules\RH\Models\Employe;
use App\Modules\RH\Models\TypePrime;
use App\Modules\Socle\Models\AnneeScolaire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RHCreationFormsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Employe $employe;

    private AnneeScolaire $anneeScolaire;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Role::firstOrCreate(['name' => 'Comptable']);
        $this->user->assignRole('Comptable');
        $this->actingAs($this->user);

        $this->employe = Employe::create([
            'user_id' => $this->user->id,
            'matricule' => 'EMP-001',
            'nom' => 'KOUASSI',
            'prenom' => 'Jean',
            'date_embauche' => now()->subYear(),
            'poste' => 'Enseignant',
            'statut' => 'actif',
        ]);

        $this->anneeScolaire = AnneeScolaire::create([
            'libelle' => '2025-2026',
            'date_debut' => '2025-09-01',
            'date_fin' => '2026-06-30',
            'statut' => AnneeScolaire::STATUT_ACTIVE,
        ]);
    }

    public function test_peut_creer_discipline_du_personnel(): void
    {
        Livewire::test(CreateSanctionPersonnel::class)
            ->fillForm([
                'employe_id' => $this->employe->id,
                'type' => 'avertissement_verbal',
                'motif' => 'Retard répété lors des cours du matin',
                'date_sanction' => '2026-08-25',
                'statut' => 'en_attente_validation',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('sanctions_personnel', [
            'employe_id' => $this->employe->id,
            'type' => 'avertissement_verbal',
            'motif' => 'Retard répété lors des cours du matin',
            'created_by' => $this->user->id,
        ]);
    }

    public function test_peut_creer_type_de_prime(): void
    {
        Livewire::test(CreateTypePrime::class)
            ->fillForm([
                'libelle' => 'Prime Exceptionnelle',
                'mode_calcul' => 'montant_fixe',
                'valeur_defaut' => 50000,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('types_primes', [
            'libelle' => 'Prime Exceptionnelle',
            'mode_calcul' => 'montant_fixe',
        ]);

        $typePrime = TypePrime::query()->where('libelle', 'Prime Exceptionnelle')->firstOrFail();
        $this->assertMatchesRegularExpression('/^PRIME-[A-Z0-9]{8}$/', $typePrime->code);
    }

    public function test_peut_creer_gestion_de_prime(): void
    {
        $typePrime = TypePrime::create([
            'code' => 'PRIME_REND',
            'libelle' => 'Prime de Rendement',
            'mode_calcul' => 'fixe',
            'valeur_defaut' => 25000,
        ]);

        Livewire::test(CreatePrime::class)
            ->fillForm([
                'employe_id' => $this->employe->id,
                'type_prime_id' => $typePrime->id,
                'montant' => 30000,
                'mois' => 8,
                'annee' => 2026,
                'justification' => 'Performance excellente',
                'statut' => 'proposee',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('primes', [
            'employe_id' => $this->employe->id,
            'type_prime_id' => $typePrime->id,
            'montant' => 30000,
            'proposee_par' => $this->user->id,
        ]);
    }

    public function test_peut_creer_bulletin_de_paie(): void
    {
        $contrat = Contrat::create([
            'employe_id' => $this->employe->id,
            'type' => 'CDI',
            'date_debut' => '2025-09-01',
            'salaire_base' => 250000,
            'statut' => 'actif',
        ]);

        Livewire::test(CreateBulletinPaie::class)
            ->fillForm([
                'employe_id' => $this->employe->id,
                'contrat_id' => $contrat->id,
                'mois' => 8,
                'annee' => 2026,
                'salaire_base' => 250000,
                'total_primes' => 30000,
                'total_retenues' => 0,
                'total_cotisations' => 10500,
                'avances_deduites' => 0,
                'net_a_payer' => 269500,
                'statut' => 'brouillon',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('bulletins_paie', [
            'employe_id' => $this->employe->id,
            'contrat_id' => $contrat->id,
            'annee_scolaire_id' => $this->anneeScolaire->id,
            'statut' => 'brouillon',
            'net_a_payer' => 269500,
        ]);
    }
}
