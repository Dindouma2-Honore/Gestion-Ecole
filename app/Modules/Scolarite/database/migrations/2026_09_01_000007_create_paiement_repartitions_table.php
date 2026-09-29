<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Répartition en cascade d'un versement sur les frais dus. C'est cette
 * table qui permet de savoir précisément combien reste sur CHAQUE frais
 * individuel (pas seulement le reste à payer global) — voir
 * PaiementService::enregistrerPaiement().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paiement_repartitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paiement_id')->constrained('paiements_scolarite');
            $table->foreignId('frais_eleve_id')->constrained('frais_eleve');
            $table->decimal('montant_alloue', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paiement_repartitions');
    }
};
