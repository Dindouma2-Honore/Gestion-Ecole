<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emplois_du_temps', function (Blueprint $table) {
            $table->id();

            // classe_id (Scolarité), enseignant_id et salle_id (RH),
            // annee_scolaire_id (Socle) : appartiennent à d'autres modules.
            // Jamais de contrainte de clé étrangère inter-module (voir
            // deptrac.yaml) — uniquement l'identifiant, validé via le
            // Contract du module propriétaire côté Service.
            $table->unsignedBigInteger('classe_id');
            $table->unsignedBigInteger('enseignant_id');
            $table->unsignedBigInteger('salle_id');
            $table->unsignedBigInteger('annee_scolaire_id');

            // matiere_id : même module (Pédagogie) -> vraie contrainte FK.
            $table->foreignId('matiere_id')->constrained('matieres');
            $table->foreignId('creneau_id')->constrained('creneaux_horaires');
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->index(['classe_id', 'annee_scolaire_id', 'actif']);
            $table->index(['enseignant_id', 'annee_scolaire_id', 'actif']);
            $table->index(['salle_id', 'annee_scolaire_id', 'actif']);

            // Un même triplet (créneau, année scolaire, actif) ne doit pas se répéter
            // pour la même classe/enseignant/salle -> géré en code (detecterConflits),
            // pas en contrainte SQL, car "actif" doit rester filtrable côté historique.
            $table->index(['creneau_id', 'annee_scolaire_id', 'actif']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emplois_du_temps');
    }
};
