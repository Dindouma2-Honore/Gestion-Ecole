<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paiements_scolarite', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inscription_id')->constrained('inscriptions');
            $table->decimal('montant', 10, 2);
            $table->enum('mode', ['especes', 'bancaire', 'mobile_money']);
            $table->string('reference_mobile_money', 100)->nullable();
            $table->string('numero_recu', 30)->unique();

            // Jamais de suppression physique — annulation via
            // paiement_annulations uniquement (voir PaiementService).
            $table->enum('statut', ['valide', 'annule'])->default('valide');

            $table->foreignId('encaisse_par')->constrained('users');

            // Archive HTML du reçu (DocumentServiceContract de Socle,
            // contrat désormais livré et vérifié) — voir
            // PaiementService::enregistrerPaiement().
            $table->foreignId('document_recu_id')->nullable()->constrained('documents')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paiements_scolarite');
    }
};
