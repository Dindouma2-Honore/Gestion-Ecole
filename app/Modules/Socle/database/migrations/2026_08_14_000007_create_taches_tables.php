<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taches', function (Blueprint $table) {
            $table->id();
            $table->string('titre');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('responsable_id');
            $table->unsignedBigInteger('createur_id');
            $table->dateTime('echeance');
            $table->enum('priorite', ['basse', 'normale', 'haute', 'urgente'])->default('normale');
            $table->string('statut', 30)->default('a_faire');
            $table->nullableMorphs('taskable');
            $table->timestamps();

            $table->foreign('responsable_id')->references('id')->on('users');
            $table->foreign('createur_id')->references('id')->on('users');
        });

        Schema::create('tache_validations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tache_id');
            $table->unsignedBigInteger('validateur_id');
            $table->unsignedTinyInteger('niveau_validation')->default(1);
            $table->enum('statut', ['en_attente', 'validee', 'rejetee'])->default('en_attente');
            $table->text('commentaire')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();

            $table->foreign('tache_id')->references('id')->on('taches')->onDelete('cascade');
            $table->foreign('validateur_id')->references('id')->on('users');
        });

        Schema::create('tache_historique_statuts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tache_id');
            $table->string('statut', 30);
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->text('commentaire')->nullable();
            $table->timestamp('changed_at');

            $table->foreign('tache_id')->references('id')->on('taches')->onDelete('cascade');
            $table->foreign('changed_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tache_historique_statuts');
        Schema::dropIfExists('tache_validations');
        Schema::dropIfExists('taches');
    }
};
