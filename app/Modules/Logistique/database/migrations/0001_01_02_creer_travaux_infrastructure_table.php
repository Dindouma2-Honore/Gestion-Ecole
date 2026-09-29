<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('travaux_infrastructure', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salle_id')->constrained('salles');
            $table->text('description');
            $table->date('date_debut');
            $table->date('date_fin_prevue')->nullable();
            $table->date('date_fin_reelle')->nullable();
            $table->decimal('cout', 10, 2)->nullable();
            $table->enum('statut', ['planifie', 'en_cours', 'termine'])->default('planifie');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travaux_infrastructure');
    }
};
