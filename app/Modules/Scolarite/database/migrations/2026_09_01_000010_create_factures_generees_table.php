<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remplace l'ancienne table `factures` (facture unique, montant = frais de
 * classe) : une inscription porte désormais deux factures dans son cycle de
 * vie — une provisoire (émise à la création, avant tout versement) puis une
 * définitive (émise une fois le versement confirmé), voir
 * InscriptionService::inscrire() / activerApresVersement().
 *
 * La numérotation est déléguée à Socle (ParametrageServiceContract, voir
 * 2026_09_01_000013_seed_formats_numerotation_scolarite).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('factures_generees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inscription_id')->constrained('inscriptions');
            $table->enum('type', ['provisoire', 'definitive']);
            $table->string('numero', 30)->unique();
            $table->decimal('montant', 10, 2);
            $table->date('date_emission');

            // Destinataire figé au moment de la génération — même logique
            // que l'ancienne table `factures`, pour ne jamais dépendre d'un
            // parent qui aurait changé depuis.
            $table->foreignId('parent_id')->nullable()->constrained('parents_tuteurs');
            $table->string('nom_destinataire', 200)->nullable();
            $table->string('telephone_destinataire', 30)->nullable();
            $table->string('email_destinataire', 150)->nullable();

            // Archive HTML de la facture (DocumentServiceContract de Socle,
            // contrat désormais livré et vérifié) — voir
            // FactureService::creerFacture(). Reste imprimable/exportable à
            // la volée via ImprimerFactureController, indépendamment de
            // cette archive.
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();

            // Suivi de l'envoi au parent (repris tel quel de l'ancien
            // FactureServiceInterface — l'intégration WhatsApp elle-même
            // reste hors périmètre de ce module).
            $table->enum('statut_envoi', ['en_attente', 'envoyee', 'echec'])->default('en_attente');
            $table->string('canal_envoi', 30)->nullable();
            $table->timestamp('envoyee_le')->nullable();
            $table->string('echec_raison')->nullable();

            $table->timestamps();

            // Une seule facture provisoire et une seule facture définitive
            // par inscription.
            $table->unique(['inscription_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('factures_generees');
    }
};
