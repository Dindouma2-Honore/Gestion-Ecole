<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eleves', function (Blueprint $table) {
            $table->id();
            $table->string('matricule_permanent', 30)->unique()->nullable();
            $table->string('nom', 100);
            $table->string('prenom', 100);
            $table->date('date_naissance')->nullable();
            $table->enum('sexe', ['M', 'F'])->nullable();
            $table->string('photo')->nullable();
            $table->enum('statut', [
                'prospect', 'candidat', 'admis', 'inscrit', 'actif',
                'suspendu', 'retire', 'diplome', 'archive',
            ])->default('prospect');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('eleve_contacts_urgence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eleve_id')->constrained('eleves')->cascadeOnDelete();
            $table->string('nom', 150)->nullable();
            $table->string('telephone', 30)->nullable();
            $table->string('lien', 50)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eleve_contacts_urgence');
        Schema::dropIfExists('eleves');
    }
};
