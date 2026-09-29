<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paiement_annulations_scolarite', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paiement_id')->constrained('paiements_scolarite');
            $table->text('motif');
            $table->foreignId('annule_par')->constrained('users');
            $table->timestamp('annule_le');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paiement_annulations_scolarite');
    }
};
