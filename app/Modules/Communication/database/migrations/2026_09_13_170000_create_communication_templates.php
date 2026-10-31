<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_templates', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('code');
            $t->string('provider_template_name', 150);
            $t->string('waba_id', 64);
            $t->string('audience');
            $t->string('module');
            $t->string('trigger_event')->nullable();
            $t->string('language', 20)->default('fr');
            $t->string('provider_category')->default('UTILITY');
            $t->string('provider_category_requested')->nullable();
            $t->string('provider_category_effective')->nullable();
            $t->string('header_type')->default('NONE');
            $t->text('header_text')->nullable();
            $t->text('header_handle')->nullable();
            $t->text('body');
            $t->string('footer', 60)->nullable();
            $t->json('buttons')->nullable();
            $t->json('variables_schema')->nullable();
            $t->string('local_status')->default('DRAFT')->index();
            $t->string('meta_status')->default('NOT_SUBMITTED')->index();
            $t->string('provider_template_id', 100)->nullable();
            $t->string('submission_state')->nullable();
            $t->json('provider_response')->nullable();
            $t->json('provider_components')->nullable();
            $t->string('content_hash', 64)->nullable();
            $t->string('quality_rating')->nullable();
            $t->timestamp('quality_updated_at')->nullable();
            $t->timestamp('submitted_at')->nullable();
            $t->unsignedBigInteger('submitted_by')->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->timestamp('rejected_at')->nullable();
            $t->timestamp('last_synced_at')->nullable();
            $t->timestamp('last_webhook_at')->nullable();
            $t->text('rejection_reason')->nullable();
            $t->string('provider_error_code')->nullable();
            $t->text('provider_feedback')->nullable();
            $t->timestamps();
            $t->unique(['waba_id', 'provider_template_name', 'language'], 'communication_template_provider_unique');
            $t->unique(['waba_id', 'code', 'language'], 'communication_template_code_unique');
        });
        Schema::create('communication_template_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('communication_template_id')->constrained('communication_templates');
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('event');
            $t->json('old_values')->nullable();
            $t->json('new_values')->nullable();
            $t->json('result')->nullable();
            $t->timestamps();
        });
        Schema::table('communication_messages', function (Blueprint $t) {
            $t->foreignId('communication_template_id')->nullable()->constrained('communication_templates');
            $t->string('template_content_hash', 64)->nullable();
            $t->unsignedBigInteger('initiated_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('communication_messages', function (Blueprint $t) {
            $t->dropConstrainedForeignId('communication_template_id');
            $t->dropColumn(['template_content_hash', 'initiated_by']);
        });
        Schema::dropIfExists('communication_template_events');
        Schema::dropIfExists('communication_templates');
    }
};
