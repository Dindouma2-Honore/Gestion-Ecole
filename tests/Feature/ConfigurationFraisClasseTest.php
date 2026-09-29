<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Finances\Contracts\ConfigurationFraisClasseServiceContract;
use App\Modules\Finances\Exceptions\RepartitionTranchesInvalideException;
use App\Modules\Finances\Models\ConfigurationFraisClasse;
use App\Modules\Scolarite\Models\Classe;
use App\Modules\Socle\Models\AnneeScolaire;
use App\Modules\Socle\Models\Niveau;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfigurationFraisClasseTest extends TestCase
{
    use RefreshDatabase;

    public function test_une_classe_possede_une_configuration_annuelle_en_trois_tranches(): void
    {
        [$classe, $annee] = $this->contexte();

        $configuration = app(ConfigurationFraisClasseServiceContract::class)->configurer(
            $classe->id,
            $annee->id,
            135000,
            [
                ['ordre' => 1, 'libelle' => 'Tranche 1', 'montant' => 50000, 'date_echeance' => '2026-09-15'],
                ['ordre' => 2, 'libelle' => 'Tranche 2', 'montant' => 45000, 'date_echeance' => '2026-12-15'],
                ['ordre' => 3, 'libelle' => 'Tranche 3', 'montant' => 40000, 'date_echeance' => '2027-03-15'],
            ],
        );

        $this->assertSame(135000.0, (float) $configuration->montant_total);
        $this->assertSame(135000.0, (float) $configuration->tranches->sum('montant'));
        $this->assertCount(3, $configuration->tranches);
    }

    public function test_la_somme_des_tranches_doit_correspondre_au_total(): void
    {
        [$classe, $annee] = $this->contexte();
        $this->expectException(RepartitionTranchesInvalideException::class);

        app(ConfigurationFraisClasseServiceContract::class)->configurer($classe->id, $annee->id, 135000, [
            ['ordre' => 1, 'libelle' => 'Tranche incomplète', 'montant' => 50000, 'date_echeance' => '2026-09-15'],
        ]);
    }

    public function test_les_tarifs_de_deux_annees_restent_historises(): void
    {
        [$classe, $annee] = $this->contexte();
        $anneeSuivante = AnneeScolaire::create(['libelle' => '2027-2028', 'date_debut' => '2027-09-01', 'date_fin' => '2028-07-31', 'statut' => 'active']);
        $service = app(ConfigurationFraisClasseServiceContract::class);
        $service->configurer($classe->id, $annee->id, 135000, [['ordre' => 1, 'libelle' => 'Total', 'montant' => 135000, 'date_echeance' => '2026-09-15']]);
        $service->configurer($classe->id, $anneeSuivante->id, 150000, [['ordre' => 1, 'libelle' => 'Total', 'montant' => 150000, 'date_echeance' => '2027-09-15']]);

        $this->assertSame(2, ConfigurationFraisClasse::query()->where('classe_id', $classe->id)->count());
        $this->assertSame(135000.0, (float) $service->obtenir($classe->id, $annee->id)['montant_total']);
        $this->assertSame(150000.0, (float) $service->obtenir($classe->id, $anneeSuivante->id)['montant_total']);
    }

    /** @return array{Classe, AnneeScolaire} */
    private function contexte(): array
    {
        $niveau = Niveau::create(['nom' => '6e', 'code' => '6E', 'ordre' => 1]);
        $annee = AnneeScolaire::create(['libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31', 'statut' => 'active']);
        $classe = Classe::create(['nom' => '6e A', 'code' => '6EA', 'niveau_id' => $niveau->id, 'annee_scolaire_id' => $annee->id, 'capacite_max' => 45]);

        return [$classe, $annee];
    }
}
