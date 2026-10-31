<?php

namespace Tests\Feature\Communication;

use App\Models\User;
use App\Modules\Communication\Exceptions\WhatsAppTemplateNotApprovedException;
use App\Modules\Communication\Filament\Resources\CommunicationTemplateResource;
use App\Modules\Communication\Filament\Resources\CommunicationTemplateResource\Pages\ViewCommunicationTemplate;
use App\Modules\Communication\Jobs\SendWhatsAppMessage;
use App\Modules\Communication\Jobs\SyncWhatsAppTemplatesJob;
use App\Modules\Communication\Models\CommunicationTemplate;
use App\Modules\Communication\Services\WhatsAppCloudApiClient;
use App\Modules\Communication\Services\WhatsAppDispatcher;
use App\Modules\Communication\Services\WhatsAppTemplateResolver;
use App\Modules\Communication\Services\WhatsAppTemplateService;
use App\Modules\Communication\Services\WhatsAppTemplateValidator;
use App\Modules\Communication\Support\WhatsAppTemplatePermissions;
use Database\Seeders\CommunicationTemplateSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WhatsAppTemplateLifecycleTest extends TestCase
{
    private WhatsAppTemplateService $service;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::hasTable('communication_messages')) {
            (require base_path('app/Modules/Communication/database/migrations/2026_09_13_160000_create_whatsapp_test_tables.php'))->up();
        }
        if (! Schema::hasTable('communication_templates')) {
            (require base_path('app/Modules/Communication/database/migrations/2026_09_13_170000_create_communication_templates.php'))->up();
        }
        self::cleanTables();
        // Earlier tests use the original message schema; Eloquent caches its columns statically.
        (new \ReflectionProperty(\Illuminate\Database\Eloquent\Model::class, 'guardableColumns'))->setValue(null, []);
        if (! Schema::hasTable('users')) {
            Schema::create('users', function ($t) {
                $t->id();
                $t->string('name');
                $t->string('email');
                $t->string('password');
                $t->string('statut');
                $t->boolean('must_change_password')->default(false);
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('permissions')) {
            (require base_path('app/Modules/Socle/database/migrations/2026_08_14_135829_create_permission_tables.php'))->up();
        }
        $this->actor = User::withoutEvents(fn () => User::firstOrCreate(
            ['email' => 'admin@example.test'],
            ['name' => 'Test Admin', 'password' => 'password', 'statut' => 'actif']
        ));
        $this->actor->update(['statut' => 'actif']);
        $this->actingAs($this->actor);
        foreach (WhatsAppTemplatePermissions::ALL as $p) {
            Gate::define($p, fn () => true);
        }
        config(['whatsapp.enabled' => true, 'whatsapp.mode' => 'test', 'whatsapp.allowlist' => ['+237670097386'],
            'whatsapp.token' => 'test-secret', 'whatsapp.phone_id' => '123', 'whatsapp.waba_id' => '456', 'whatsapp.version' => 'v25.0', 'queue.default' => 'database']);
        Queue::fake();
        Http::preventStrayRequests();
        $this->service = app(WhatsAppTemplateService::class);
    }

    private function fake(array $callbacks = []): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake($callbacks);
    }

    private function draft(array $overrides = []): CommunicationTemplate
    {
        return $this->service->create(array_merge(['name' => 'Inscription confirmée', 'code' => 'parent_inscription_confirmee',
            'provider_template_name' => 'parent_inscription_confirmee', 'audience' => 'PARENT', 'module' => 'SCOLARITE',
            'language' => 'fr', 'provider_category' => 'UTILITY', 'header_type' => 'NONE', 'body' => 'Bonjour {{1}}, inscription de {{2}} confirmée. Merci.',
            'variables_schema' => [['component' => 'BODY', 'position' => 1, 'key' => 'parent_name', 'example' => 'Mme Exemple'], ['component' => 'BODY', 'position' => 2, 'key' => 'student_name', 'example' => 'Paul Exemple']], 'buttons' => []], $overrides));
    }

    private function remote(CommunicationTemplate $t, string $status = 'APPROVED'): array
    {
        return ['id' => '987', 'name' => $t->provider_template_name, 'language' => $t->language, 'status' => $status, 'category' => 'UTILITY',
            'components' => app(WhatsAppTemplateValidator::class)->validate($t)['components'], 'quality_score' => ['score' => 'GREEN']];
    }

    private function approved(): CommunicationTemplate
    {
        $t = $this->draft();
        $this->service->markReady($t);
        $this->fake(['*/message_templates' => Http::response(['id' => '987', 'status' => 'PENDING', 'category' => 'UTILITY'])]);
        $this->service->submitToMeta($t);
        $t->refresh();
        $this->fake(['*' => Http::response(['data' => [$this->remote($t)]])]);
        $this->service->syncTemplateStatus($t);
        $this->service->activate($t);

        return $t->fresh();
    }

    public function test_complete_lifecycle_and_worker_delivery(): void
    {
        $t = $this->approved();
        $this->assertSame('ACTIVE', $t->local_status);
        $this->assertSame('APPROVED', $t->meta_status);
        $this->fake(['*/messages' => Http::response(['messages' => [['id' => 'wamid.template']]])]);
        $m = app(WhatsAppDispatcher::class)->dispatchTemplate($t, '+237670097386');
        app()->call([new SendWhatsAppMessage($m->id), 'handle']);
        $this->assertSame('accepted', $m->fresh()->status, (string) $m->fresh()->reason);
        $this->assertSame('wamid.template', $m->fresh()->provider_message_id);
        $this->assertTrue($t->history()->where('event', 'template_submitted')->exists());
        $this->assertTrue($t->history()->where('event', 'template_approved')->exists());
    }

    public function test_local_record_never_implies_approval(): void
    {
        $t = $this->draft(['meta_status' => 'APPROVED', 'local_status' => 'ACTIVE']);
        $this->assertSame('NOT_SUBMITTED', $t->meta_status);
        $this->assertSame('DRAFT', $t->local_status);
        $this->expectException(WhatsAppTemplateNotApprovedException::class);
        app(WhatsAppTemplateResolver::class)->resolve($t, []);
    }

    public function test_invalid_name_blocks_submission_without_http(): void
    {
        $t = $this->draft(['provider_template_name' => 'bad name']);
        try {
            $this->service->markReady($t);
            $this->fail();
        } catch (ValidationException) {
            Http::assertNothingSent();
        }
    }

    public function test_missing_example_blocks(): void
    {
        $t = $this->draft(['variables_schema' => [['component' => 'BODY', 'position' => 1, 'key' => 'parent_name', 'example' => '']]]);
        $this->expectException(ValidationException::class);
        $this->service->markReady($t);
    }

    public function test_positions_must_match_the_schema(): void
    {
        $t = $this->draft(['body' => 'Bonjour {{1}}, inscription de {{3}} confirmée.']);
        $this->expectException(ValidationException::class);
        $this->service->markReady($t);
    }

    public function test_unsupported_language_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->service->markReady($this->draft(['language' => 'fr_FR']));
    }

    public function test_document_requires_sample_and_handle_is_hidden(): void
    {
        $t = $this->draft(['header_type' => 'DOCUMENT']);
        try {
            $this->service->markReady($t);
            $this->fail();
        } catch (ValidationException) {
        }
        $t = $this->service->update($t, ['header_handle' => 'sample-secret']);
        $this->service->markReady($t);
        $this->assertStringNotContainsString('sample-secret', $t->getRawOriginal('header_handle'));
        $this->assertArrayNotHasKey('header_handle', $t->toArray());
        $this->assertStringNotContainsString('sample-secret', $t->history->toJson());
    }

    public function test_second_submission_never_posts_again(): void
    {
        $t = $this->draft();
        $this->service->markReady($t);
        $this->fake(['*' => Http::response(['id' => '987', 'status' => 'PENDING'])]);
        $this->service->submitToMeta($t);
        try {
            $this->service->submitToMeta($t);
            $this->fail();
        } catch (ValidationException) {
            Http::assertSentCount(1);
        }
    }

    public function test_uncertain_submission_is_not_retried_and_response_is_redacted(): void
    {
        $t = $this->draft();
        $this->service->markReady($t);
        $this->fake(['*' => Http::response(['error' => ['code' => 190, 'message' => 'Bearer secret-token']], 401)]);
        try {
            $this->service->submitToMeta($t);
            $this->fail();
        } catch (ValidationException) {
        }
        $this->assertSame('UNKNOWN', $t->fresh()->meta_status);
        $this->assertSame('UNCERTAIN', $t->fresh()->submission_state);
        $this->assertStringNotContainsString('secret-token', $t->fresh()->toJson());
        try {
            $this->service->submitToMeta($t);
            $this->fail();
        } catch (ValidationException) {
            Http::assertSentCount(1);
        }
    }

    public function test_rejection_and_paused_states_disable_sending(): void
    {
        $t = $this->approved();
        foreach (['PENDING', 'REJECTED', 'PAUSED', 'DISABLED'] as $status) {
            $this->fake(['*' => Http::response(['data' => [array_merge($this->remote($t, $status), ['rejected_reason' => 'INVALID_FORMAT'])]])]);
            $this->service->syncTemplateStatus($t);
            $t->refresh();
            $this->assertSame($status, $t->meta_status);
            $this->assertSame('DISABLED', $t->local_status);
        }
    }

    public function test_queued_message_rechecks_disabled_template(): void
    {
        $t = $this->approved();
        $m = app(WhatsAppDispatcher::class)->dispatchTemplate($t, '+237670097386');
        $this->service->disable($t);
        $this->fake();
        app()->call([new SendWhatsAppMessage($m->id), 'handle']);
        $this->assertSame('blocked', $m->fresh()->status);
    }

    public function test_approved_version_is_immutable_and_duplicate_is_draft(): void
    {
        $t = $this->approved();
        try {
            $this->service->update($t, ['body' => 'Changed']);
            $this->fail();
        } catch (ValidationException) {
        }
        $copy = $this->service->duplicate($t);
        $this->assertSame('NOT_SUBMITTED', $copy->meta_status);
        $this->assertNull($copy->provider_template_id);
        $this->assertNotSame($t->provider_template_name, $copy->provider_template_name);
    }

    public function test_provider_content_drift_disables_active_template(): void
    {
        $t = $this->approved();
        $remote = $this->remote($t);
        $remote['components'][0]['text'] = 'Different approved content.';
        $this->fake(['*' => Http::response(['data' => [$remote]])]);
        $this->service->syncTemplateStatus($t);
        $this->assertSame('DISABLED', $t->fresh()->local_status);
        $this->expectException(WhatsAppTemplateNotApprovedException::class);
        $this->service->activate($t);
    }

    public function test_waba_change_blocks_use(): void
    {
        $t = $this->approved();
        config(['whatsapp.waba_id' => 'different']);
        $this->expectException(WhatsAppTemplateNotApprovedException::class);
        app(WhatsAppTemplateResolver::class)->resolve($t, []);
    }

    public function test_old_status_blocks_use(): void
    {
        $t = $this->approved();
        $this->travel(61)->minutes();
        $this->expectException(WhatsAppTemplateNotApprovedException::class);
        app(WhatsAppTemplateResolver::class)->resolve($t, []);
    }

    public function test_permissions_checked_in_service(): void
    {
        Gate::define('submit_whatsapp_template', fn () => false);
        $t = $this->draft();
        $this->service->markReady($t);
        $this->expectException(AuthorizationException::class);
        $this->service->submitToMeta($t);
    }

    public function test_unknown_recipient_and_production_block_test(): void
    {
        $t = $this->approved();
        try {
            app(WhatsAppDispatcher::class)->dispatchTemplate($t, '+33600000000');
            $this->fail();
        } catch (\RuntimeException) {
        }
        config(['whatsapp.mode' => 'production']);
        $this->expectException(\RuntimeException::class);
        app(WhatsAppDispatcher::class)->dispatchTemplate($t, '+237670097386');
    }

    public function test_sync_imports_demo_without_recreating_it_and_preserves_drafts(): void
    {
        $t = $this->draft();
        $remote = $this->remote($t);
        $remote['name'] = 'hello_world';
        $remote['language'] = 'en_US';
        $this->fake(['*' => Http::response(['data' => [$remote]])]);
        $this->service->syncTemplates();
        $this->service->syncTemplates();
        $this->assertSame(2, CommunicationTemplate::count());
        $this->assertSame('NOT_SUBMITTED', $t->fresh()->meta_status);
        Http::assertSent(fn ($r) => $r->method() === 'GET');
    }

    public function test_pagination_only_follows_cursor_on_meta_host(): void
    {
        $t = $this->draft();
        $remote = $this->remote($t);
        Http::fakeSequence()->push(['data' => [$remote], 'paging' => ['next' => 'https://evil.test/?access_token=secret', 'cursors' => ['after' => 'cursor']]])->push(['data' => []]);
        $this->service->syncTemplates();
        Http::assertSentCount(2);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'evil.test'));
    }

    public function test_signed_template_webhook_blocks_and_enqueues_reconciliation(): void
    {
        $t = $this->approved();
        config(['whatsapp.app_secret' => 'secret']);
        $body = json_encode(['entry' => [['id' => '456', 'time' => now()->timestamp, 'changes' => [['field' => 'message_template_status_update', 'value' => ['event' => 'PAUSED', 'message_template_id' => '987']]]]]]);
        $this->call('POST', '/webhooks/whatsapp', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'secret')], $body)->assertOk();
        $this->assertSame('PAUSED', $t->fresh()->meta_status);
        $this->assertSame('DISABLED', $t->fresh()->local_status);
        Queue::assertPushed(SyncWhatsAppTemplatesJob::class);
    }

    public function test_seeder_is_idempotent_local_only_and_validates_initial_texts(): void
    {
        self::cleanTables();
        (new CommunicationTemplateSeeder)->run();
        (new CommunicationTemplateSeeder)->run();
        $this->assertSame(36, CommunicationTemplate::count());
        foreach (CommunicationTemplate::where('header_type', 'NONE')->get() as $t) {
            $this->service->markReady($t);
        }
        Http::assertNothingSent();
        $this->assertSame(0, CommunicationTemplate::where('meta_status', '!=', 'NOT_SUBMITTED')->count());
    }

    public function test_resource_hides_unauthorized_and_can_build_form_and_actions(): void
    {
        $this->assertTrue(CommunicationTemplateResource::canViewAny());
        $this->assertNotEmpty(CommunicationTemplateResource::workflowActions());
        $schema = CommunicationTemplateResource::form(\Filament\Schemas\Schema::make());
        $this->assertNotEmpty($schema->getComponents());
        Gate::define('view_whatsapp_templates', fn () => false);
        $this->assertFalse(CommunicationTemplateResource::canViewAny());
    }

    public function test_pdf_example_is_uploaded_and_stored_encrypted(): void
    {
        $t = $this->draft(['header_type' => 'DOCUMENT']);
        $this->fake(['*/app/uploads*' => Http::response(['id' => 'upload:session?sig=abc']), '*/upload:session*' => Http::response(['h' => 'sample-handle'])]);
        $this->service->prepareDocumentExample($t);
        $this->assertSame('sample-handle', $t->fresh()->header_handle);
        $this->assertStringNotContainsString('sample-handle', $t->fresh()->getRawOriginal('header_handle'));
        Http::assertSentCount(2);
        $this->service->markReady($t);
    }

    public function test_inactive_user_cannot_submit(): void
    {
        $t = $this->draft();
        $this->service->markReady($t);
        $this->actor->statut = 'inactif';
        $this->expectException(AuthorizationException::class);
        $this->service->submitToMeta($t);
    }

    public function test_forged_template_webhook_does_not_change_status(): void
    {
        $t = $this->approved();
        config(['whatsapp.app_secret' => 'secret']);
        $this->postJson('/webhooks/whatsapp', ['entry' => [['id' => '456']]])->assertForbidden();
        $this->assertSame('APPROVED', $t->fresh()->meta_status);
    }

    public function test_resolver_does_not_allow_unvalidated_content_changes(): void
    {
        $t = $this->approved();
        $t->update(['body' => 'Changed locally.']);
        $this->expectException(WhatsAppTemplateNotApprovedException::class);
        app(WhatsAppTemplateResolver::class)->resolve($t, []);
    }

    public function test_rendered_template_details_never_show_handle(): void
    {
        $t = $this->draft(['header_type' => 'DOCUMENT', 'header_handle' => 'secret-handle']);
        $page = new ViewCommunicationTemplate;
        $page->record = $t;
        $this->assertSame($t->id, $page->getRecord()->id);
        $preview = view('communication::templates.preview', ['text' => '<script>alert(1)</script>'])->render();
        $this->assertStringNotContainsString('<script>', $preview);
    }

    public function test_direct_api_client_cannot_bypass_template_approval(): void
    {
        $t = $this->draft();
        $this->expectException(WhatsAppTemplateNotApprovedException::class);
        app(WhatsAppCloudApiClient::class)->send('237670097386', ['type' => 'template', 'template' => ['name' => $t->provider_template_name, 'language' => ['code' => 'fr']]]);
    }

    public function test_reconciliation_cannot_overwrite_a_newer_pause_webhook(): void
    {
        $t = $this->approved();
        $remote = $this->remote($t);
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(function () use ($t, $remote) {
            $this->service->webhook(['message_template_id' => $t->provider_template_id, 'event' => 'PAUSED'], now()->timestamp);

            return Http::response(['data' => [$remote]]);
        });
        $this->service->syncTemplateStatus($t);
        $this->assertSame('PAUSED', $t->fresh()->meta_status);
        $this->assertSame('DISABLED', $t->fresh()->local_status);
        $this->assertNull($t->fresh()->last_synced_at);
    }

    public function test_changing_provider_name_cannot_reuse_approval(): void
    {
        $t = $this->approved();
        $t->provider_template_name = 'another_template';
        $this->expectException(WhatsAppTemplateNotApprovedException::class);
        app(WhatsAppTemplateResolver::class)->assertApproved($t);
    }

    private static function cleanTables(): void
    {
        if (Schema::hasTable('communication_template_events')) {
            \Illuminate\Support\Facades\DB::table('communication_template_events')->delete();
        }
        if (Schema::hasTable('communication_messages')) {
            \Illuminate\Support\Facades\DB::table('communication_messages')->delete();
        }
        if (Schema::hasTable('communication_templates')) {
            \Illuminate\Support\Facades\DB::table('communication_templates')->delete();
        }
    }

    protected function tearDown(): void
    {
        self::cleanTables();
        parent::tearDown();
    }
}
