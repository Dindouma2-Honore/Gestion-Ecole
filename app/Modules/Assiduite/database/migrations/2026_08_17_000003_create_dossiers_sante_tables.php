<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('dossiers_sante')) {
            Schema::create('dossiers_sante', function (Blueprint $table) {
                $table->id();
                $table->foreignId('eleve_id')->unique()->constrained('eleves')->cascadeOnDelete();
                $table->string('groupe_sanguin', 5)->nullable();
                $table->text('allergies')->nullable();
                $table->text('maladies_chroniques')->nullable();
                $table->text('medicaments_autorises')->nullable();
                $table->string('contact_urgence_nom');
                $table->string('contact_urgence_telephone', 20);
                $table->string('medecin_traitant')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('visites_infirmerie')) {
            Schema::create('visites_infirmerie', function (Blueprint $table) {
                $table->id();
                $table->foreignId('eleve_id')->constrained('eleves')->cascadeOnDelete();
                $table->timestamp('date_heure');
                $table->text('motif');
                $table->text('soins_prodigues')->nullable();
                $table->string('medicament_administre')->nullable();
                $table->enum('gravite', ['mineure', 'moderee', 'grave'])->default('mineure');
                $table->boolean('evacuation_necessaire')->default(false);
                $table->boolean('parent_notifie')->default(false);
                $table->foreignId('traite_par')->constrained('users')->cascadeOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('visites_infirmerie');
        Schema::dropIfExists('dossiers_sante');
    }
};
