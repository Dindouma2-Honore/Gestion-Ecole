<?php

declare(strict_types=1);

namespace Tests\Feature\Pedagogie;

use App\Models\User;
use App\Modules\Pedagogie\Contracts\MatiereServiceInterface;
use App\Modules\Pedagogie\Filament\Resources\CategorieMatiereResource;
use App\Modules\Pedagogie\Filament\Resources\CategorieMatiereResource\Pages\CreateCategorieMatiere;
use App\Modules\Pedagogie\Filament\Resources\CategorieMatiereResource\Pages\EditCategorieMatiere;
use App\Modules\Pedagogie\Filament\Resources\MatiereResource\Pages\CreateMatiere;
use App\Modules\Pedagogie\Filament\Resources\MatiereResource\Pages\EditMatiere;
use App\Modules\Pedagogie\Filament\Resources\MatiereResource\Pages\ListMatieres;
use App\Modules\Pedagogie\Models\CategorieMatiere;
use App\Modules\Pedagogie\Models\Matiere;
use App\Modules\Pedagogie\Services\CatalogueMatiereService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class MatiereTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_matiere_code_is_generated_unique_and_stable(): void
    {
        $this->authenticateFounder();

        $categorieId = CategorieMatiere::query()->where('code', 'NON_CLASSEE')->value('id');

        Livewire::test(CreateMatiere::class)
            ->fillForm([
                'nom' => 'Mathématiques',
                'categorie_matiere_id' => $categorieId,
                'description' => 'Matière générale',
                'actif' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $matiere = Matiere::query()->where('nom', 'Mathématiques')->firstOrFail();
        $codeInitial = $matiere->code;
        $this->assertNotEmpty($codeInitial);

        Livewire::test(CreateMatiere::class)
            ->fillForm([
                'nom' => 'Mathématiques',
                'categorie_matiere_id' => $categorieId,
                'description' => 'Deuxième création',
                'actif' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $codes = Matiere::query()->where('nom', 'Mathématiques')->pluck('code');
        $this->assertCount(2, $codes->unique());

        Livewire::test(EditMatiere::class, ['record' => $matiere->getRouteKey()])
            ->fillForm(['description' => 'Description modifiée'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($codeInitial, $matiere->fresh()->code);
        Livewire::test(ListMatieres::class)
            ->assertCanSeeTableRecords([$matiere->fresh()])
            ->assertTableColumnExists('code');
    }

    public function test_categorie_code_is_generated_and_order_is_not_required_by_form(): void
    {
        $this->authenticateFounder();

        Livewire::test(CreateCategorieMatiere::class)
            ->fillForm([
                'nom' => 'Sciences humaines',
                'description' => 'Catégorie pédagogique',
                'actif' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $categorie = CategorieMatiere::query()->where('nom', 'Sciences humaines')->firstOrFail();
        $codeInitial = $categorie->code;
        $this->assertSame('sciences_humaines', $codeInitial);
        $this->assertSame(0, (int) $categorie->ordre_affichage);

        Livewire::test(CreateCategorieMatiere::class)
            ->fillForm(['nom' => 'Sciences humaines', 'description' => 'Autre catégorie', 'actif' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertCount(2, CategorieMatiere::query()->where('nom', 'Sciences humaines')->pluck('code')->unique());

        Livewire::test(EditCategorieMatiere::class, ['record' => $categorie->getRouteKey()])
            ->fillForm(['nom' => 'Sciences sociales'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($codeInitial, $categorie->fresh()->code);
    }

    public function test_matiere_service_persists_business_discriminant(): void
    {
        $this->authenticateFounder();

        $domaine = app(MatiereServiceInterface::class)->creer(nom: 'Éveil', code: 'EVEIL-T', coefficient: 1.0, type: Matiere::TYPE_DOMAINE_ACTIVITE);
        $domaineModel = Matiere::query()->findOrFail($domaine['id']);
        $this->assertSame('domaine', $domaineModel->nature);
        $this->assertSame(Matiere::TYPE_DOMAINE_ACTIVITE, $domaineModel->type);

        $matiere = app(MatiereServiceInterface::class)->creer(nom: 'Histoire', code: 'HIST-T', coefficient: 2.0);
        $matiereModel = Matiere::query()->findOrFail($matiere['id']);
        $this->assertSame('matiere', $matiereModel->nature);
        $this->assertSame(Matiere::TYPE_MATIERE, $matiereModel->type);

        app(CatalogueMatiereService::class)->enregistrer([
            'nom' => 'Éveil',
            'code' => 'EVEIL-T',
            'nature' => 'activite',
            'categorie_matiere_id' => CategorieMatiere::query()->where('code', 'NON_CLASSEE')->value('id'),
            'coefficient_defaut' => 1.5,
            'actif' => true,
        ], $domaineModel->getKey());

        $domaineModel->refresh();
        $this->assertSame('activite', $domaineModel->nature);
        $this->assertSame(1.5, (float) $domaineModel->coefficient_defaut);
        $this->assertSame(Matiere::TYPE_DOMAINE_ACTIVITE, $domaineModel->type);
    }

    public function test_coefficient_defaut_is_administered_through_catalogue(): void
    {
        $this->authenticateFounder();

        $categorieId = CategorieMatiere::query()->where('code', 'NON_CLASSEE')->value('id');

        Livewire::test(CreateMatiere::class)
            ->fillForm([
                'nom' => 'Physique-Chimie',
                'code' => 'PHYS-T',
                'categorie_matiere_id' => $categorieId,
                'coefficient_defaut' => 4.5,
                'actif' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $matiere = Matiere::query()->where('code', 'PHYS-T')->firstOrFail();
        $this->assertSame(4.5, (float) $matiere->coefficient_defaut);

        Livewire::test(EditMatiere::class, ['record' => $matiere->getRouteKey()])
            ->fillForm(['coefficient_defaut' => 3.0])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(3.0, (float) $matiere->fresh()->coefficient_defaut);
        $this->assertSame($categorieId, (int) $matiere->fresh()->categorie_matiere_id);
    }

    public function test_used_category_cannot_be_deleted(): void
    {
        $this->authenticateFounder();

        $service = app(CatalogueMatiereService::class);
        $categorie = $service->enregistrerCategorie(['nom' => 'Sciences expérimentales', 'actif' => true]);

        $service->enregistrer([
            'nom' => 'Physique',
            'code' => 'PHY-CAT',
            'nature' => 'matiere',
            'categorie_matiere_id' => $categorie->getKey(),
            'actif' => true,
        ]);

        $this->assertFalse(CategorieMatiereResource::canDelete($categorie));

        try {
            $service->supprimerCategorie($categorie->getKey());
            $this->fail('La suppression d’une catégorie utilisée doit être refusée.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('categorie', $exception->errors());
        }

        $this->assertNotNull($categorie->fresh());

        $parDefaut = CategorieMatiere::query()->where('code', 'NON_CLASSEE')->firstOrFail();

        try {
            $service->supprimerCategorie($parDefaut->getKey());
            $this->fail('La catégorie par défaut doit être protégée.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('categorie', $exception->errors());
        }

        $this->assertNotNull($parDefaut->fresh());
    }

    private function authenticateFounder(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(['statut' => 'actif']);
        $user->assignRole('Fondateur');
        $this->actingAs($user);
    }
}
