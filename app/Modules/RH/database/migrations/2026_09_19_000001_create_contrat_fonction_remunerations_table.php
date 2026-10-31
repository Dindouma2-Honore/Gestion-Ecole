<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contrat_fonction_remunerations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contrat_id')->constrained('contrats')->cascadeOnDelete();
            $table->foreignId('poste_administratif_id')->constrained('postes_administratifs')->restrictOnDelete();
            $table->enum('mode_remuneration', ['salaire_base', 'tarif_horaire']);
            $table->decimal('montant', 10, 2);
            $table->date('date_effet');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['contrat_id', 'poste_administratif_id'], 'contrat_fonction_rem_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contrat_fonction_remunerations');
    }
};
