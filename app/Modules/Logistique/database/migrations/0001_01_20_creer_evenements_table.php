<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evenements', function (Blueprint $table) {
            $table->id();
            $table->string('titre');
            $table->text('description')->nullable();
            $table->dateTime('date_debut');
            $table->dateTime('date_fin');
            $table->string('lieu')->nullable();
            $table->decimal('budget_prevu', 10, 2)->nullable();
            $table->boolean('necessite_transport')->default(false);
            $table->boolean('necessite_autorisation_parentale')->default(false);
            $table->unsignedBigInteger('responsable_id'); // users.id (module Socle), pas de FK inter-module
            $table->enum('statut', ['planifie', 'en_cours', 'termine', 'annule'])->default('planifie');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evenements');
    }
};
