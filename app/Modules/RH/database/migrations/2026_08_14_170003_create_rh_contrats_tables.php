<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contrats', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employe_id')->constrained('employes')->cascadeOnDelete();
            $table->enum('type', ['CDI', 'CDD', 'vacation']);
            $table->date('date_debut');
            $table->date('date_fin')->nullable();
            $table->date('periode_essai_fin')->nullable();
            $table->decimal('salaire_base', 10, 2);
            $table->enum('statut', ['actif', 'suspendu', 'resilie', 'expire'])->default('actif');
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('contrat_avenants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contrat_id')->constrained('contrats')->cascadeOnDelete();
            $table->text('description');
            $table->date('date_effet');
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contrat_avenants');
        Schema::dropIfExists('contrats');
    }
};
