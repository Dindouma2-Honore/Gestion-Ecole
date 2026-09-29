<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rendez_vous', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->constrained('parents_tuteurs');
            $table->foreignId('responsable_id')->constrained('users');
            $table->string('motif');
            $table->dateTime('date_heure_demandee');
            $table->dateTime('date_heure_confirmee')->nullable();
            $table->string('statut', 30)->default('demande');
            $table->text('compte_rendu')->nullable();
            $table->timestamps();
        });

        Schema::create('rendez_vous_historique_statuts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rendez_vous_id')->constrained('rendez_vous')->cascadeOnDelete();
            $table->string('statut', 30);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('commentaire')->nullable();
            $table->timestamp('changed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rendez_vous_historique_statuts');
        Schema::dropIfExists('rendez_vous');
    }
};
