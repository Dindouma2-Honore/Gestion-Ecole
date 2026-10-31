<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('programme_analyses')) {
            Schema::create('programme_analyses', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('programme_id')
                    ->constrained('programmes')
                    ->cascadeOnDelete();
                $table->string('status', 30)->default('en_attente'); // en_attente, en_cours, termine, echec
                $table->longText('extracted_text')->nullable();
                $table->json('analysis_result')->nullable();
                $table->string('ai_provider', 50)->nullable();
                $table->string('ai_model', 50)->nullable();
                $table->text('error_message')->nullable();
                $table->unsignedInteger('tokens_used')->nullable();
                $table->timestamp('analyzed_at')->nullable();
                $table->timestamps();

                $table->index(['programme_id', 'status']);
                $table->index('analyzed_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('programme_analyses');
    }
};
