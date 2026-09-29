<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('types_primes', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('libelle', 100);
            $table->enum('mode_calcul', ['fixe', 'pourcentage_base', 'variable', 'montant_fixe', 'pourcentage_salaire', 'bareme_anciennete']);
            $table->decimal('valeur_defaut', 10, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('primes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employe_id')->constrained('employes')->cascadeOnDelete();
            $table->foreignId('type_prime_id')->constrained('types_primes')->cascadeOnDelete();
            $table->decimal('montant', 10, 2);
            $table->tinyInteger('mois');
            $table->smallInteger('annee');
            $table->text('justification')->nullable();
            $table->enum('statut', ['proposee', 'validee', 'rejetee', 'integree_paie'])->default('proposee');
            $table->foreignId('proposee_par')->constrained('users')->cascadeOnDelete();
            $table->foreignId('validee_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('primes');
        Schema::dropIfExists('types_primes');
    }
};
