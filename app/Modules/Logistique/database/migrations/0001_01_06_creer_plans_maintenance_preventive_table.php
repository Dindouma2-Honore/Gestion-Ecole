<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans_maintenance_preventive', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipement_id')->constrained('equipements');
            $table->unsignedInteger('frequence_jours');
            $table->date('derniere_execution')->nullable();
            $table->date('prochaine_echeance');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans_maintenance_preventive');
    }
};
