<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presences_cantine', function (Blueprint $table) {
            $table->id();
            $table->foreignId('abonnement_id')->constrained('abonnements_cantine');
            $table->date('date_repas');
            $table->boolean('present');

            $table->unique(['abonnement_id', 'date_repas']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presences_cantine');
    }
};
