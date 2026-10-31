<?php

namespace Tests\Feature;

use App\Filament\Support\ModuleNavigation;
use App\Filament\Support\SchoolFinanceNavigation;
use App\Models\User;
use App\Modules\Finances\Filament\Resources\MouvementCaisseResource;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Tests\TestCase;

class SchoolFinanceNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_school_finance_links_are_only_in_school_navigation(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(['statut' => 'actif']);
        $user->assignRole('Fondateur');
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $urls = fn (string $module) => collect(ModuleNavigation::forModule($module)->getNavigation())->flatMap(fn ($group) => $group->getItems())->map(fn ($item) => $item->getUrl())->all();
        $school = $urls('Scolarite');
        $finance = $urls('Finances');
        foreach (SchoolFinanceNavigation::COMPONENTS as $component => $label) {
            $this->assertContains($component::getUrl(), $school, $label);
            $this->assertNotContains($component::getUrl(), $finance, $label);
        }
        $this->assertContains(MouvementCaisseResource::getUrl(), $finance);
    }

    public function test_invoice_and_payment_routes_keep_school_context(): void
    {
        foreach (array_keys(SchoolFinanceNavigation::COMPONENTS) as $component) {
            $page = is_subclass_of($component, \Filament\Resources\Resource::class) ? $component::getPages()['index']->getPage() : $component;
            $request = Request::create('/test');
            $route = new Route('GET', '/test', ['uses' => $page.'@__invoke', 'controller' => $page.'@__invoke']);
            $request->setRouteResolver(fn () => $route);
            app()->instance('request', $request);
            $this->assertSame('Scolarite', ModuleNavigation::currentModuleCode());
        }
    }
}
