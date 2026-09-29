<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employe_id')->constrained('employes')->cascadeOnDelete();
            $table->enum('type', ['conge_annuel', 'permission_exceptionnelle', 'conge_maladie', 'conge_maternite', 'autre']);
            $table->date('date_debut');
            $table->date('date_fin');
            $table->decimal('nombre_jours', 4, 1);
            $table->text('motif')->nullable();
            $table->foreignId('justificatif_document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->string('statut', 30)->default('demande');
            $table->foreignId('remplacant_temporaire_id')->nullable()->constrained('employes')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('conge_historique_statuts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conge_id')->constrained('conges')->cascadeOnDelete();
            $table->string('statut', 30);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('commentaire')->nullable();
            $table->timestamp('changed_at')->useCurrent();
        });

        Schema::create('solde_conges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employe_id')->constrained('employes')->cascadeOnDelete();
            $table->foreignId('annee_scolaire_id')->constrained('annees_scolaires')->cascadeOnDelete();
            $table->decimal('jours_acquis', 4, 1);
            $table->decimal('jours_pris', 4, 1)->default(0);
            $table->timestamps();
            $table->unique(['employe_id', 'annee_scolaire_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solde_conges');
        Schema::dropIfExists('conge_historique_statuts');
        Schema::dropIfExists('conges');
    }
};
