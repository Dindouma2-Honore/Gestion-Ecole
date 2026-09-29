<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visite_infirmeries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('eleve_id');
            $table->timestamp('date_heure');
            $table->text('motif');
            $table->text('soins_prodigues')->nullable();
            $table->string('medicament_administre')->nullable();
            $table->enum('gravite', ['mineure', 'moderee', 'grave']);
            $table->boolean('evacuation_necessaire')->default(false);
            $table->boolean('parent_notifie')->default(false);
            $table->unsignedBigInteger('traite_par'); // users.id
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visites_infirmerie');
    }
};
