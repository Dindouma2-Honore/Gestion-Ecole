<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('frais', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 150);

            // Montant par défaut / unique pour les frais hors grille (cantine,
            // tenues, transport...). Ignoré si utilise_grille_tarifaire = true.
            $table->decimal('montant', 10, 2)->default(0);

            $table->foreignId('categorie_frais_id')->constrained('categories_frais');

            // true uniquement pour les 3 frais de "Frais de scolarité"
            // (inscription, tranche 1, tranche 2) — leur montant vient alors
            // de la grille tarifaire par classe/année, jamais de la colonne
            // montant ci-dessus.
            $table->boolean('utilise_grille_tarifaire')->default(false);

            // Priorité de répartition en cascade des versements :
            // 1 = frais d'inscription, 2 = tranche 1, 3 = tranche 2 (ordre
            // métier par défaut demandé au §1), 999 = frais divers (départagés
            // par date de création entre eux — voir PaiementService).
            $table->unsignedInteger('ordre_repartition')->default(999);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('frais');
    }
};
