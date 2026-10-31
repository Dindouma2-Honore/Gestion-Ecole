<?php

namespace Tests\Feature\Communication;

use App\Modules\Communication\Jobs\SendWhatsAppMessage;
use App\Modules\Communication\Models\CommunicationMessage;
use App\Modules\Communication\Services\WhatsAppCloudApiClient;
use App\Modules\Communication\Services\WhatsAppDispatcher;
use App\Modules\Communication\Services\WhatsAppStatusService;
use App\Modules\Scolarite\Contracts\EnrollmentValidated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class WhatsAppTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::hasTable('communication_messages')) {
            (require base_path('app/Modules/Communication/database/migrations/2026_09_13_160000_create_whatsapp_test_tables.php'))->up();
        } else {
            CommunicationMessage::query()->delete();
        }
        config(['whatsapp.enabled' => true, 'whatsapp.mode' => 'test', 'whatsapp.allowlist' => ['+2250700000000'], 'whatsapp.token' => 'fake', 'whatsapp.phone_id' => '123', 'whatsapp.waba_id' => '456', 'whatsapp.version' => 'v25.0', 'queue.default' => 'database']);
        Http::preventStrayRequests();
        Queue::fake();
    }

    private function send(bool $simulation = false, string $recipient = '+2250700000000'): CommunicationMessage
    {
        return app(WhatsAppDispatcher::class)->dispatch($recipient, ['type' => 'template', 'template' => ['name' => 'hello_world', 'language' => ['code' => 'en_US']]], simulation: $simulation);
    }

    public function test_blocked_recipient_never_queues(): void
    {
        $this->assertSame('blocked', $this->send(recipient: '+33612345678')->status);
        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    public function test_simulation_never_sends(): void
    {
        $this->assertSame('simulated', $this->send(true)->status);
        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    public function test_worker_rechecks_mode(): void
    {
        $m = $this->send();
        config(['whatsapp.mode' => 'production']);
        app()->call([new SendWhatsAppMessage($m->id), 'handle']);
        $this->assertSame('blocked', $m->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_worker_claim_prevents_duplicate_send(): void
    {
        Http::fake(['*' => Http::response(['messages' => [['id' => 'wamid.test']]])]);
        $m = $this->send();
        $job = new SendWhatsAppMessage($m->id);
        app()->call([$job, 'handle']);
        app()->call([$job, 'handle']);
        Http::assertSentCount(1);
        $this->assertSame('accepted', $m->fresh()->status);
    }

    public function test_uncertain_send_is_not_retried(): void
    {
        Http::fake(['*' => Http::response([], 500)]);
        $m = $this->send();
        $job = new SendWhatsAppMessage($m->id);
        app()->call([$job, 'handle']);
        app()->call([$job, 'handle']);
        Http::assertSentCount(1);
        $this->assertSame('unknown', $m->fresh()->status);
    }

    public function test_configuration_missing_blocks(): void
    {
        config(['whatsapp.token' => null]);
        $this->assertSame('blocked', $this->send()->status);
        Queue::assertNothingPushed();
    }

    public function test_override_is_simulation_only(): void
    {
        config(['whatsapp.override' => '+2250700000000']);
        $m = $this->send(true, '+33612345678');
        $this->assertSame('simulated', $m->status);
        $this->assertSame('2250700000000', $m->actual_recipient);
        $this->assertSame('blocked', $this->send(false, '+33612345678')->status);
    }

    public function test_idempotency_key(): void
    {
        $d = app(WhatsAppDispatcher::class);
        $a = $d->dispatch('+2250700000000', [], 'event:1', true);
        $b = $d->dispatch('+2250700000000', [], 'event:1', true);
        $this->assertSame($a->id, $b->id);
    }

    public function test_webhook_signature_and_monotonic_status(): void
    {
        $this->postJson('/webhooks/whatsapp', [])->assertForbidden();
        config(['whatsapp.app_secret' => 'secret']);
        $m = $this->send();
        $m->update(['provider_message_id' => 'wamid.test', 'status' => 'accepted']);
        foreach (['read', 'delivered', 'failed', 'read'] as $status) {
            $body = json_encode(['entry' => [['id' => '456', 'changes' => [['value' => ['metadata' => ['phone_number_id' => '123'], 'statuses' => [['id' => 'wamid.test', 'status' => $status]]]]]]]]);
            $this->call('POST', '/webhooks/whatsapp', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'secret')], $body)->assertOk();
        }
        $this->assertSame('read', $m->fresh()->status);
    }

    public function test_challenge(): void
    {
        config(['whatsapp.verify_token' => 'verify']);
        $this->get('/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=verify&hub_challenge=123')->assertOk()->assertSee('123');
    }

    public function test_transaction_rollback_discards_message(): void
    {
        DB::beginTransaction();
        $this->send();
        DB::rollBack();
        $this->assertSame(0, CommunicationMessage::count());
    }

    public function test_page_actions_require_permissions(): void
    {
        $page = new \App\Modules\Communication\Filament\Pages\WhatsAppTest;
        $this->expectException(HttpException::class);
        $page->send();
    }

    public function test_early_webhook_reconciles_after_send(): void
    {
        app(WhatsAppStatusService::class)->receive('wamid.early', 'read');
        Http::fake(['*' => Http::response(['messages' => [['id' => 'wamid.early']]])]);
        $m = $this->send();
        app()->call([new SendWhatsAppMessage($m->id), 'handle']);
        $this->assertSame('read', $m->fresh()->status);
    }

    public function test_fictitious_pdf_is_uploaded_and_sent(): void
    {
        Http::fake(['*/media' => Http::response(['id' => 'media.test']), '*/messages' => Http::response(['messages' => [['id' => 'wamid.pdf']]])]);
        $m = app(WhatsAppDispatcher::class)->dispatch('+2250700000000', ['type' => 'fictitious_pdf']);
        app()->call([new SendWhatsAppMessage($m->id), 'handle']);
        $this->assertSame('accepted', $m->fresh()->status);
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/messages') && $request['document']['id'] === 'media.test');
    }

    public function test_arbitrary_real_payload_is_blocked(): void
    {
        $m = app(WhatsAppDispatcher::class)->dispatch('+2250700000000', ['type' => 'text', 'text' => ['body' => 'private']]);
        $this->assertSame('blocked', $m->status);
        Queue::assertNothingPushed();
    }

    public function test_business_event_waits_for_commit_and_rollback_cancels_it(): void
    {
        DB::beginTransaction();
        event(new EnrollmentValidated(101, '+2250700000000'));
        $this->assertSame(0, CommunicationMessage::count());
        DB::rollBack();
        $this->assertSame(0, CommunicationMessage::count());
        DB::beginTransaction();
        event(new EnrollmentValidated(101, '+2250700000000'));
        DB::commit();
        $this->assertSame('simulated', CommunicationMessage::first()->status);
        Queue::assertNothingPushed();
    }

    public function test_worker_rechecks_allowlist_and_disabled_flag(): void
    {
        $first = $this->send();
        config(['whatsapp.allowlist' => []]);
        app()->call([new SendWhatsAppMessage($first->id), 'handle']);
        $this->assertSame('blocked', $first->fresh()->status);
        config(['whatsapp.allowlist' => ['+2250700000000']]);
        $second = $this->send();
        config(['whatsapp.enabled' => false]);
        app()->call([new SendWhatsAppMessage($second->id), 'handle']);
        $this->assertSame('blocked', $second->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_production_is_always_forbidden(): void
    {
        $this->app->instance('env', 'production');
        $this->assertSame('blocked', $this->send()->status);
        $this->assertSame('blocked', $this->send(true)->status);
        Http::assertNothingSent();
    }

    public function test_connection_only_uses_get(): void
    {
        Http::fake(['*' => Http::response(['id' => '123'])]);
        $this->assertTrue(app(WhatsAppCloudApiClient::class)->connection());
        Http::assertSent(fn ($request) => $request->method() === 'GET');
        Http::assertSentCount(1);
    }
}
