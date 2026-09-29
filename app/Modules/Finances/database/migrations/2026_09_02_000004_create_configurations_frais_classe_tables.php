<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configurations_frais_classe', function (Blueprint $table): void {
            $table->id();
            // Références inter-modules : validées par Contracts, sans FK physique.
            $table->unsignedBigInteger('classe_id');
            $table->unsignedBigInteger('annee_scolaire_id');
            $table->decimal('montant_total', 12, 2);
            $table->enum('politique_validation_inscription', [
                'frais_inscription', 'premiere_tranche', 'inscription_et_premiere_tranche', 'montant_minimum',
            ])->default('frais_inscription');
            $table->decimal('montant_minimum_inscription', 12, 2)->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->unique(['classe_id', 'annee_scolaire_id'], 'config_frais_classe_annee_unique');
            $table->index(['annee_scolaire_id', 'actif']);
        });

        Schema::create('tranches_frais_classe', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('configuration_frais_classe_id')
                ->constrained('configurations_frais_classe')->cascadeOnDelete();
            $table->unsignedTinyInteger('ordre');
            $table->string('libelle', 100);
            $table->decimal('montant', 12, 2);
            $table->date('date_echeance');
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->unique(['configuration_frais_classe_id', 'ordre'], 'tranche_config_ordre_unique');
            $table->index(['date_echeance', 'actif']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tranches_frais_classe');
        Schema::dropIfExists('configurations_frais_classe');
    }
};
