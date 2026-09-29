<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enseignants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employe_id')->unique()->constrained('employes')->cascadeOnDelete();
            $table->string('specialite', 100)->nullable();
            $table->enum('statut_contractuel', ['titulaire', 'vacataire', 'contractuel']);
            $table->decimal('charge_horaire_hebdo', 4, 1)->nullable();
            $table->timestamps();
        });

        Schema::create('enseignant_matiere_niveau', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('enseignant_id')->constrained('enseignants')->cascadeOnDelete();
            $table->unsignedBigInteger('matiere_id');
            $table->foreignId('niveau_id')->constrained('niveaux')->cascadeOnDelete();
            $table->foreignId('annee_scolaire_id')->constrained('annees_scolaires')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('inspections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('enseignant_id')->constrained('enseignants')->cascadeOnDelete();
            $table->date('date_inspection');
            $table->foreignId('inspecteur_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('note', 4, 2)->nullable();
            $table->text('observations')->nullable();
            $table->text('recommandations')->nullable();
            $table->timestamps();
        });

        Schema::create('remplacements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('enseignant_absent_id')->constrained('enseignants')->cascadeOnDelete();
            $table->foreignId('enseignant_remplacant_id')->nullable()->constrained('enseignants')->nullOnDelete();
            $table->date('date_debut');
            $table->date('date_fin')->nullable();
            $table->string('motif', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remplacements');
        Schema::dropIfExists('inspections');
        Schema::dropIfExists('enseignant_matiere_niveau');
        Schema::dropIfExists('enseignants');
    }
};
