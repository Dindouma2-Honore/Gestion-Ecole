<?php

declare(strict_types=1);

use App\Filament\Support\HabilitationCatalog;
use App\Filament\Support\ModuleAccess;
use App\Filament\Support\ModuleNavigation;
use App\Models\User;
use App\Modules\Scolarite\Models\Niveau;
use App\Modules\Scolarite\Policies\NiveauPolicy;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

it('discovers each school resource once with its pages model and existing feature', function (string $name): void {
    $resource = 'App\\Modules\\Scolarite\\Filament\\Resources\\'.$name.'Resource';
    $legacy = 'App\\Modules\\Socle\\Filament\\Resources\\'.$name.'Resource';
    $panel = Filament::getPanel('admin');
    Filament::setCurrentPanel($panel);

    expect(array_count_values($panel->getResources())[$resource] ?? 0)->toBe(1)
        ->and(class_exists($legacy))->toBeFalse()
        ->and(file_exists(app_path('Modules/Socle/Filament/Resources/'.$name.'Resource.php')))->toBeFalse()
        ->and($resource::getModel())->toBe('App\\Modules\\Scolarite\\Models\\'.$name)
        ->and(HabilitationCatalog::featureFor($resource))->toBe('socle.parametres')
        ->and(HabilitationCatalog::featureFor($legacy))->toBeNull()
        ->and(ModuleAccess::moduleFromComponent($resource))->toBe('Scolarite');

    foreach ($resource::getPages() as $registration) {
        $page = $registration->getPage();
        expect($page::getResource())->toBe($resource)
            ->and(ModuleAccess::featureCodeFromComponent($page))->toBe('socle.parametres');
    }

    Role::findOrCreate('Fondateur', 'web');
    $user = User::factory()->create(['statut' => 'actif', 'must_change_password' => false]);
    $user->assignRole('Fondateur');
    $this->actingAs($user);
    $this->get($resource::getUrl())->assertSuccessful();
    $this->get($resource::getUrl('create'))->assertSuccessful();

    $urls = fn ($module) => collect(ModuleNavigation::forModule($module)->getNavigation())
        ->flatMap(fn ($group) => $group->getItems())->map(fn ($item) => $item->getUrl());
    expect($urls('Scolarite')->filter(fn ($url) => $url === $resource::getUrl())->count())->toBe(1)
        ->and($urls('Socle')->contains($resource::getUrl()))->toBeFalse();
})->with(['Cycle', 'Niveau', 'Filiere', 'SectionScolaire']);

it('preserves founder only niveau policy for the school model', function (): void {
    Role::findOrCreate('Fondateur', 'web');
    $founder = User::factory()->create();
    $founder->assignRole('Fondateur');
    $other = User::factory()->create();

    foreach ([Niveau::class] as $model) {
        expect(Gate::getPolicyFor($model))->toBeInstanceOf(NiveauPolicy::class);
        foreach (['viewAny', 'create'] as $ability) {
            expect(Gate::forUser($founder)->allows($ability, $model))->toBeTrue()
                ->and(Gate::forUser($other)->allows($ability, $model))->toBeFalse();
        }
        foreach (['view', 'update', 'delete'] as $ability) {
            expect(Gate::forUser($founder)->allows($ability, new $model))->toBeTrue()
                ->and(Gate::forUser($other)->allows($ability, new $model))->toBeFalse();
        }
    }
});
