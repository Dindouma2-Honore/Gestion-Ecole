<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipement_historique_localisations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipement_id')->constrained('equipements');
            $table->foreignId('ancienne_salle_id')->nullable()->constrained('salles');
            $table->foreignId('nouvelle_salle_id')->constrained('salles');
            $table->date('date_deplacement');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipement_historique_localisations');
    }
};
