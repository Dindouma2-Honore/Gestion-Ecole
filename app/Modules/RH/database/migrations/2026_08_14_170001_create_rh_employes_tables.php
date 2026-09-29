<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('matricule', 30)->unique();
            $table->string('nom', 255);
            $table->string('prenom', 255);
            $table->date('date_naissance')->nullable();
            $table->enum('sexe', ['M', 'F'])->nullable();
            $table->string('telephone', 20)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('poste', 100);
            $table->string('departement', 100)->nullable();
            $table->date('date_embauche');
            $table->foreignId('niveau_id')->nullable()->constrained('niveaux')->nullOnDelete();
            $table->enum('statut', ['actif', 'suspendu', 'en_conge', 'demissionne', 'licencie'])->default('actif');
            $table->timestamps();
        });

        Schema::create('employe_historique_carriere', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employe_id')->constrained('employes')->cascadeOnDelete();
            $table->enum('evenement', ['embauche', 'mutation', 'promotion', 'changement_poste', 'demission', 'licenciement']);
            $table->string('ancien_poste', 100)->nullable();
            $table->string('nouveau_poste', 100)->nullable();
            $table->date('date_evenement');
            $table->text('commentaire')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employe_historique_carriere');
        Schema::dropIfExists('employes');
    }
};
