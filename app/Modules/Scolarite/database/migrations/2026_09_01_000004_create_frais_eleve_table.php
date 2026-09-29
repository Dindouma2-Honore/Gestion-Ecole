<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Charges effectivement rattachées à un dossier d'inscription — résolues
 * une fois (montant figé) au moment d'InscriptionService::inscrire(), pour
 * ne jamais dépendre d'une grille ou d'un tarif qui changerait ensuite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('frais_eleve', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inscription_id')->constrained('inscriptions');
            $table->foreignId('frais_id')->constrained('frais');

            // Résolu depuis la grille tarifaire si le frais l'exige, sinon
            // recopié depuis frais.montant — jamais recalculé dynamiquement.
            $table->decimal('montant', 10, 2);

            $table->enum('statut', ['du', 'annule'])->default('du');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('frais_eleve');
    }
};
