<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avances_salaires', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employe_id')->constrained('employes')->cascadeOnDelete();
            $table->decimal('montant', 10, 2);
            $table->date('date_demande');
            $table->text('motif')->nullable();
            $table->enum('statut', ['demande', 'approuvee', 'rejetee', 'remboursee'])->default('demande');
            $table->decimal('montant_deja_deduit', 10, 2)->default(0);
            $table->foreignId('valide_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('bulletins_paie', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employe_id')->constrained('employes')->cascadeOnDelete();
            $table->foreignId('contrat_id')->constrained('contrats')->cascadeOnDelete();
            $table->tinyInteger('mois');
            $table->smallInteger('annee');
            $table->foreignId('annee_scolaire_id')->constrained('annees_scolaires')->cascadeOnDelete();
            $table->decimal('salaire_base', 10, 2);
            $table->decimal('total_primes', 10, 2)->default(0);
            $table->decimal('total_retenues', 10, 2)->default(0);
            $table->decimal('total_cotisations', 10, 2)->default(0);
            $table->decimal('avances_deduites', 10, 2)->default(0);
            $table->decimal('net_a_payer', 10, 2);
            $table->enum('statut', ['brouillon', 'calcule', 'valide', 'paye', 'annule'])->default('calcule');
            $table->date('date_paiement')->nullable();
            $table->timestamps();
            $table->unique(['employe_id', 'mois', 'annee']);
        });

        Schema::create('bulletin_lignes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bulletin_paie_id')->constrained('bulletins_paie')->cascadeOnDelete();
            $table->enum('type', ['gain', 'prime', 'retenue', 'cotisation', 'avance']);
            $table->string('libelle', 150);
            $table->decimal('montant', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulletin_lignes');
        Schema::dropIfExists('bulletins_paie');
        Schema::dropIfExists('avances_salaires');
    }
};
