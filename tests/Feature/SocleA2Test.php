<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Socle\Contracts\ParametrageServiceContract;
use App\Modules\Socle\Contracts\NumerotationServiceContract;
use App\Modules\Socle\Models\ConfigEtablissement;
use App\Modules\Socle\Models\FormatNumerotation;
use App\Modules\Socle\Models\JourFerie;
use App\Modules\Socle\Models\Niveau;
use App\Modules\Socle\Models\TemplateNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class SocleA2Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_configuration_is_a_cached_singleton_invalidated_on_save(): void
    {
        $configuration = ConfigEtablissement::get();
        $this->assertSame($configuration->id, ConfigEtablissement::get()->id);

        $configuration->update(['nom' => 'Ambassadors AEC']);

        $this->assertFalse(Cache::has('config_etablissement'));
        $this->assertSame('Ambassadors AEC', ConfigEtablissement::get()->nom);
    }

    public function test_public_contract_exposes_levels_and_typed_settings(): void
    {
        $niveau = Niveau::create(['nom' => 'Primaire', 'code' => 'PRI', 'ordre' => 1]);
        $service = app(ParametrageServiceContract::class);

        $this->assertSame($niveau->id, $service->getNiveau($niveau->id)?->id);
        $this->assertSame(15, $service->getParametre('delaiToleranceMinutes'));
        $this->assertSame('repli', $service->getParametre('inconnu', 'repli'));
    }

    public function test_active_notification_template_replaces_documented_placeholders(): void
    {
        TemplateNotification::create([
            'code' => 'ABSENCE',
            'canal' => 'sms',
            'contenu' => 'Bonjour {parent}, absence de {eleve}.',
            'corps' => 'compatibilite',
            'actif' => true,
        ]);

        $contenu = app(ParametrageServiceContract::class)->getTemplateNotification(
            'sms',
            'ABSENCE',
            ['parent' => 'Marie', 'eleve' => 'Paul'],
        );

        $this->assertSame('Bonjour Marie, absence de Paul.', $contenu);
        $this->assertSame('Bonjour {parent}, absence de {eleve}.', TemplateNotification::first()->corps);
    }

    public function test_numerotation_is_incremented_and_supports_documented_placeholders(): void
    {
        FormatNumerotation::create([
            'type_document' => 'recu',
            'format' => 'AMB-{annee}-{seq:5}',
            'prochain_numero' => 42,
        ]);

        $service = app(ParametrageServiceContract::class);

        $this->assertSame('AMB-2026-00042', $service->genererNumero('recu', ['annee' => '2026']));
        $this->assertSame(
            43,
            FormatNumerotation::where('type_document', 'recu')->firstOrFail()->prochain_numero,
        );
    }

    public function test_numbering_supports_dynamic_padding_and_school_year_reset(): void
    {
        $format = FormatNumerotation::create([
            'type_document' => 'bulletin_test',
            'libelle' => 'Bulletin scolaire',
            'format' => 'BUL-{ANNEE_SCOLAIRE}-{NIVEAU}-{SEQ:7}',
            'reinitialisation' => 'annee_scolaire',
            'prochain_numero' => 42,
        ]);

        $service = app(NumerotationServiceContract::class);

        $this->assertSame('BUL-2026-2027-PRIM-0000042', $service->next('bulletin_test', [
            'annee_scolaire' => '2026-2027',
            'niveau' => 'PRIM',
        ]));
        $this->assertSame(43, $format->fresh()->prochain_numero);

        $this->assertSame('BUL-2027-2028-PRIM-0000001', $service->next('bulletin_test', [
            'annee_scolaire' => '2027-2028',
            'niveau' => 'PRIM',
        ]));
        $this->assertSame(2, $format->fresh()->prochain_numero);
    }

    public function test_default_formats_are_clearly_marked_as_examples_to_validate(): void
    {
        $this->assertDatabaseHas('formats_numerotation', [
            'type_document' => 'facture',
            'a_valider' => true,
        ]);
        $this->assertDatabaseHas('formats_numerotation', [
            'type_document' => 'bulletin',
            'a_valider' => true,
        ]);
        $this->assertDatabaseHas('formats_numerotation', [
            'type_document' => 'carte_scolaire',
            'a_valider' => true,
        ]);
    }

    public function test_only_founder_can_manage_a2_resources(): void
    {
        $fondateur = User::factory()->create();
        $fondateur->assignRole('Fondateur');
        $enseignant = User::factory()->create();
        $enseignant->assignRole('Enseignant');

        $this->assertTrue(Gate::forUser($fondateur)->allows('viewAny', FormatNumerotation::class));
        $this->assertFalse(Gate::forUser($enseignant)->allows('viewAny', FormatNumerotation::class));
    }

    public function test_founder_can_open_every_a2_administration_screen(): void
    {
        $fondateur = User::factory()->create(['statut' => 'actif']);
        $fondateur->assignRole('Fondateur');
        $this->actingAs($fondateur);

        foreach ([
            '/admin/manage-etablissement-settings',
            '/admin/manage-system-settings',
            '/admin/niveaux',
            '/admin/format-numerotations',
            '/admin/template-documents',
            '/admin/template-notifications',
            '/admin/jour-feries',
            '/admin/seuil-validations',
        ] as $url) {
            $this->get($url)->assertSuccessful();
        }
    }

    public function test_holiday_can_be_global_or_limited_to_a_level(): void
    {
        $primaire = Niveau::create(['nom' => 'Primaire', 'code' => 'PRI', 'ordre' => 1]);
        $secondaire = Niveau::create(['nom' => 'Secondaire', 'code' => 'SEC', 'ordre' => 2]);
        JourFerie::create([
            'libelle' => 'Journée pédagogique',
            'date' => '2026-09-10',
            'date_debut' => '2026-09-10',
            'date_fin' => '2026-09-10',
            'niveau_id' => $primaire->id,
        ]);

        $service = app(ParametrageServiceContract::class);
        $date = new DateTimeImmutable('2026-09-10');

        $this->assertTrue($service->estJourFerie($date, $primaire->id));
        $this->assertFalse($service->estJourFerie($date, $secondaire->id));
    }
}
