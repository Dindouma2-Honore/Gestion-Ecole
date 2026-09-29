<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paiement_tranche_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('paiement_id')->constrained('paiements')->restrictOnDelete();
            $table->foreignId('tranche_frais_classe_id')->constrained('tranches_frais_classe')->restrictOnDelete();
            $table->decimal('montant', 12, 2);
            $table->timestamps();
            $table->unique(['paiement_id', 'tranche_frais_classe_id'], 'paiement_tranche_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paiement_tranche_allocations');
    }
};
