<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reclamations', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['reclamation_parent', 'incident_scolaire', 'plainte']);
            $table->string('categorie')->nullable();
            $table->text('description');
            $table->enum('priorite', ['basse', 'normale', 'haute', 'urgente'])->default('normale');
            $table->string('signale_par_type')->nullable();
            $table->unsignedBigInteger('signale_par_id')->nullable();
            $table->unsignedBigInteger('responsable_id')->nullable(); // users.id
            $table->date('delai_reponse')->nullable();
            $table->string('statut', 30)->default('ouverte');
            $table->text('reponse')->nullable();
            $table->unsignedBigInteger('tache_id')->nullable(); // module Socle
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reclamations');
    }
};
