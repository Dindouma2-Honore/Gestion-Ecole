<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sanctions_personnel', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employe_id')->constrained('employes')->cascadeOnDelete();
            $table->enum('type', ['avertissement', 'avertissement_verbal', 'avertissement_ecrit', 'demande_explication', 'blame', 'mise_en_demeure', 'suspension', 'licenciement']);
            $table->text('motif');
            $table->date('date_sanction');
            $table->unsignedInteger('duree_jours')->nullable();
            $table->foreignId('document_justificatif_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->enum('statut', ['en_attente_validation', 'validee', 'annulee'])->default('en_attente_validation');
            $table->foreignId('valide_par')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sanctions_personnel');
    }
};
