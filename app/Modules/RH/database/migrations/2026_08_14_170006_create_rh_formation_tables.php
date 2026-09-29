<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formations', function (Blueprint $table): void {
            $table->id();
            $table->string('titre', 255);
            $table->text('description')->nullable();
            $table->date('date_debut');
            $table->date('date_fin');
            $table->decimal('cout', 10, 2)->nullable();
            $table->string('organisme', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('formation_participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('formation_id')->constrained('formations')->cascadeOnDelete();
            $table->foreignId('employe_id')->constrained('employes')->cascadeOnDelete();
            $table->boolean('present')->nullable();
            $table->decimal('evaluation_note', 4, 2)->nullable();
            $table->text('competences_acquises')->nullable();
            $table->foreignId('attestation_document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formation_participants');
        Schema::dropIfExists('formations');
    }
};
