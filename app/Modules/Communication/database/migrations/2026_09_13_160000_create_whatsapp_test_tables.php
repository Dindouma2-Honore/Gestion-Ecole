<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_messages', function (Blueprint $t) {
            $t->id();
            $t->string('idempotency_key')->unique();
            $t->string('intended_recipient');
            $t->string('actual_recipient')->nullable();
            $t->boolean('is_test')->default(true);
            $t->string('mode')->default('test');
            $t->string('phone_id')->nullable();
            $t->string('status')->index();
            $t->text('payload');
            $t->string('provider_message_id')->nullable()->unique();
            $t->json('provider_response')->nullable();
            $t->string('reason')->nullable();
            $t->timestamps();
        });
        Schema::create('whatsapp_status_receipts', function (Blueprint $t) {
            $t->id();
            $t->string('provider_message_id');
            $t->string('status');
            $t->timestamps();
            $t->unique(['provider_message_id', 'status']);
        });
        Schema::create('whatsapp_template_metadata', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('provider_name')->nullable();
            $t->string('language')->default('fr');
            $t->string('status')->default('LOCAL_ONLY');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_status_receipts');
        Schema::dropIfExists('whatsapp_template_metadata');
        Schema::dropIfExists('communication_messages');
    }
};
