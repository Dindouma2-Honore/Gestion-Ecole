<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Auth\LoginResponse;
use App\Filament\Support\AmbassadorsDesign;
use App\Models\User;
use App\Modules\Socle\Models\AnneeScolaire;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VisualFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_public_home_uses_the_shared_school_identity(): void
    {
        $this->get('/')
            ->assertSuccessful()
            ->assertSee('/accueil')
            ->assertSee('Ambassadors Educational Complex')
            ->assertSee('Foi')
            ->assertSee('Vision')
            ->assertSee('Discipline');

        $this->get('/accueil')
            ->assertRedirect('/admin/login');

        $user = User::factory()->create(['statut' => 'actif']);

        $this->actingAs($user)->get('/accueil')
            ->assertSuccessful()
            ->assertSee('Bienvenue dans votre espace')
            ->assertSee('Foi · Vision · Discipline');

        $this->get('/admin')->assertRedirect('/accueil');
    }

    public function test_admin_login_uses_the_branded_layout(): void
    {
        $this->withoutExceptionHandling();

        $this->assertInstanceOf(LoginResponse::class, app(LoginResponseContract::class));

        $this->get('/admin/login')
            ->assertSuccessful()
            ->assertSee('Bienvenue à nouveau')
            ->assertSee('Accès sécurisé et fiable');
    }

    public function test_admin_theme_reuses_brand_colors_for_the_topbar(): void
    {
        $theme = file_get_contents(resource_path('css/filament/admin/theme.css'));

        $this->assertIsString($theme);
        $this->assertStringContainsString('.fi-topbar {', $theme);
        $this->assertStringContainsString('var(--amb-navy-950)', $theme);
        $this->assertStringContainsString('var(--amb-navy-900)', $theme);
        $this->assertStringContainsString('var(--amb-gold-500)', $theme);
        $this->assertStringNotContainsString('.fi-topbar nav', $theme);
    }

    public function test_sidebar_hover_keeps_navigation_readable_on_navy(): void
    {
        $theme = file_get_contents(resource_path('css/filament/admin/theme.css'));

        $this->assertIsString($theme);
        $this->assertStringContainsString('.fi-sidebar-item:not(.fi-active)', $theme);
        $this->assertStringContainsString('background: rgb(255 255 255 / 9%)', $theme);
        $this->assertStringContainsString('color: #ffffff', $theme);
    }

    public function test_module_home_contains_no_programmed_scrolling_or_autoplay(): void
    {
        $home = file_get_contents(resource_path('views/home.blade.php'));

        $this->assertIsString($home);
        $this->assertStringContainsString('Navigation manuelle', $home);
        $this->assertStringNotContainsString('setInterval', $home);
        $this->assertStringNotContainsString('setTimeout', $home);
        $this->assertStringNotContainsString('scrollTo', $home);
        $this->assertStringNotContainsString('scrollIntoView', $home);
        $this->assertStringNotContainsString('pointermove', $home);
        $this->assertStringNotContainsString('wire:poll', $home);
        $this->assertStringNotContainsString('scroll-snap', $home);
    }

    public function test_reference_resource_displays_shared_stats_and_statuses(): void
    {
        $this->withoutExceptionHandling();

        $fondateur = User::factory()->create(['statut' => 'actif']);
        $fondateur->assignRole('Fondateur');
        AnneeScolaire::create([
            'libelle' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-07-31',
            'statut' => 'active',
        ]);

        $this->actingAs($fondateur)
            ->get('/admin/annee-scolaires')
            ->assertSuccessful()
            ->assertSee('Année active')
            ->assertSee('Contexte de travail actuel');

        $this->get('/admin/socle')
            ->assertSuccessful()
            ->assertSee('Administration générale');

        $this->assertSame('warning', AmbassadorsDesign::VALIDATION_COLOR);
        $this->assertSame('gray', AmbassadorsDesign::ADMINISTRATION_KPI_COLOR);
        $this->assertSame('success', AmbassadorsDesign::statutColor('active'));
        $this->assertSame('danger', AmbassadorsDesign::statutColor('annule'));
    }

    public function test_all_filament_tables_share_the_reference_row_style_and_hover(): void
    {
        $theme = file_get_contents(resource_path('css/filament/admin/theme.css'));

        $this->assertStringContainsString('Tables métier', $theme);
        $this->assertStringContainsString('background-color: #edf4ff', $theme);
        $this->assertStringContainsString('var(--amb-blue-700)', $theme);
        $this->assertStringContainsString('.dark .fi-ta-ctn', $theme);
        $this->assertStringContainsString('.fi-ta-table .fi-badge', $theme);
        $this->assertStringContainsString('table-layout: fixed;', $theme);
        $this->assertStringContainsString('overflow-x: clip;', $theme);
        $this->assertStringContainsString('line-height: 1.5;', $theme);
        $this->assertStringContainsString('min-height: 1.4rem;', $theme);
        $this->assertStringContainsString('white-space: nowrap;', $theme);
        $this->assertStringContainsString('text-overflow: ellipsis;', $theme);
        $this->assertStringContainsString('.fi-ta-actions-header-cell', $theme);
        $this->assertStringContainsString('.fi-ac-icon-btn-group', $theme);

        $provider = file_get_contents(app_path('Providers/AppServiceProvider.php'));
        $this->assertStringContainsString("recordActionsColumnLabel('Actions')", $provider);
        $this->assertStringContainsString('modifyUngroupedRecordActionsUsing', $provider);
        $this->assertStringContainsString('ActionGroup::configureUsing', $provider);
        $this->assertStringContainsString("heroicon-m-ellipsis-vertical", $provider);
        $this->assertStringContainsString('defaultPaginationPageOption(5)', $provider);
    }
}
