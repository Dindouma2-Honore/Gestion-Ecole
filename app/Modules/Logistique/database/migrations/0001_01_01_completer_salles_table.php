<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Complète la table `salles` déjà créée en D.26 (Emplois du temps)
     * avec les colonnes manquantes — voir Models/Salle.php. Un ALTER TABLE
     * et non un CREATE TABLE, pour ne pas dupliquer la définition posée
     * par le module D.26 qui a créé cette table en premier.
     */
    public function up(): void
    {
        Schema::table('salles', function (Blueprint $table) {
            $table->enum('type', ['classe', 'bureau', 'laboratoire', 'terrain', 'autre'])
                ->default('classe');
            $table->unsignedInteger('capacite')->nullable();
            $table->unsignedBigInteger('niveau_id')->nullable(); // module Scolarité, NULL si partagée entre niveaux
            $table->enum('etat', ['bon', 'a_renover', 'hors_service'])->default('bon');
        });
    }

    public function down(): void
    {
        Schema::table('salles', function (Blueprint $table) {
            $table->dropColumn(['type', 'capacite', 'niveau_id', 'etat']);
        });
    }
};
