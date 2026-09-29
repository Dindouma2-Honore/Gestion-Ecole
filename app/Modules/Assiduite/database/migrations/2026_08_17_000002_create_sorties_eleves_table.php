<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sorties_eleves')) {
            Schema::create('sorties_eleves', function (Blueprint $table) {
                $table->id();
                $table->foreignId('eleve_id')->constrained('eleves')->cascadeOnDelete();
                $table->foreignId('parent_id')->nullable()->constrained('parents_tuteurs')->nullOnDelete();
                $table->string('personne_autorisee_nom')->nullable();
                $table->enum('type', ['normale', 'exceptionnelle'])->default('normale');
                $table->timestamp('heure_sortie');
                $table->unsignedBigInteger('justificatif_document_id')->nullable();
                $table->foreignId('remis_par')->constrained('users')->cascadeOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sorties_eleves');
    }
};
