<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reunions', function (Blueprint $table) {
            $table->id();
            $table->string('titre');
            $table->enum('type', ['conseil_classe', 'pedagogique', 'administrative', 'parents', 'discipline']);
            $table->dateTime('date_heure');
            $table->string('lieu')->nullable();
            $table->unsignedBigInteger('niveau_id')->nullable();
            $table->enum('statut', ['planifiee', 'en_cours', 'terminee', 'annulee'])->default('planifiee');
            $table->text('compte_rendu')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->foreign('niveau_id')->references('id')->on('niveaux')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users');
        });

        Schema::create('reunion_participants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reunion_id');
            $table->unsignedBigInteger('user_id');
            $table->boolean('present')->nullable();

            $table->foreign('reunion_id')->references('id')->on('reunions')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users');
        });

        Schema::create('reunion_ordre_du_jour', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reunion_id');
            $table->string('point');
            $table->unsignedTinyInteger('ordre')->default(1);

            $table->foreign('reunion_id')->references('id')->on('reunions')->onDelete('cascade');
        });

        Schema::create('decisions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reunion_id');
            $table->text('description');
            $table->unsignedBigInteger('responsable_id');
            $table->date('echeance');
            $table->unsignedBigInteger('tache_id')->nullable();
            $table->timestamps();

            $table->foreign('reunion_id')->references('id')->on('reunions')->onDelete('cascade');
            $table->foreign('responsable_id')->references('id')->on('users');
            $table->foreign('tache_id')->references('id')->on('taches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('decisions');
        Schema::dropIfExists('reunion_ordre_du_jour');
        Schema::dropIfExists('reunion_participants');
        Schema::dropIfExists('reunions');
    }
};
