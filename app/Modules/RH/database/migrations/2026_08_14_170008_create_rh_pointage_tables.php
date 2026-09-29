<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pointages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employe_id')->constrained('employes')->cascadeOnDelete();
            $table->date('date_pointage');
            $table->time('heure_arrivee')->nullable();
            $table->time('heure_depart')->nullable();
            $table->enum('mode_pointage', ['biometrie', 'badge', 'manuel']);
            $table->string('terminal_id', 50)->nullable();
            $table->boolean('correction_manuelle')->default(false);
            $table->foreignId('corrige_par')->nullable()->constrained('users')->nullOnDelete();
            $table->text('motif_correction')->nullable();
            $table->timestamps();
            $table->unique(['employe_id', 'date_pointage']);
        });

        Schema::create('rapports_assiduite', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employe_id')->constrained('employes')->cascadeOnDelete();
            $table->tinyInteger('mois');
            $table->smallInteger('annee');
            $table->unsignedInteger('jours_presents')->default(0);
            $table->unsignedInteger('jours_retard')->default(0);
            $table->unsignedInteger('jours_absence_justifiee')->default(0);
            $table->unsignedInteger('jours_absence_non_justifiee')->default(0);
            $table->decimal('heures_supplementaires', 5, 2)->default(0);
            $table->timestamps();
            $table->unique(['employe_id', 'mois', 'annee']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rapports_assiduite');
        Schema::dropIfExists('pointages');
    }
};
