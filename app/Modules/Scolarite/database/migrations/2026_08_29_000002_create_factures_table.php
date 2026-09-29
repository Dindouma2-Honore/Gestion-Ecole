<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compteurs_factures', function (Blueprint $table) {
            $table->id();

            // annee_scolaire_id (Socle) : pas de FK inter-module — même
            // convention que compteurs_matricules.
            $table->unsignedBigInteger('annee_scolaire_id')->unique();
            $table->unsignedInteger('dernier_numero')->default(0);
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('factures', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 30)->unique();

            $table->foreignId('inscription_id')->unique()->constrained('inscriptions');
            $table->foreignId('eleve_id')->constrained('eleves');
            $table->foreignId('classe_id')->constrained('classes');

            // annee_scolaire_id (Socle) : pas de FK inter-module.
            $table->unsignedBigInteger('annee_scolaire_id');

            $table->decimal('montant', 10, 2);
            $table->date('date_emission');

            // Destinataire de la facture (parent payeur), en dur au moment
            // de la génération : même logique que l'"identite" figée dans
            // InscriptionFacturationPort::creerFactureProvisoire(), pour ne
            // jamais dépendre d'un parent qui aurait changé depuis.
            $table->foreignId('parent_id')->nullable()->constrained('parents_tuteurs');
            $table->string('nom_destinataire', 200)->nullable();
            $table->string('telephone_destinataire', 30)->nullable();

            // L'envoi WhatsApp sera branché par un autre développeur : ces
            // colonnes lui suffisent pour rendre compte du résultat via
            // FactureServiceInterface::marquerEnvoyee()/marquerEchecEnvoi(),
            // sans jamais écrire directement dans cette table.
            $table->enum('statut_envoi', ['en_attente', 'envoyee', 'echec'])->default('en_attente');
            $table->string('canal_envoi', 30)->nullable();
            $table->timestamp('envoyee_le')->nullable();
            $table->string('echec_raison')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('factures');
        Schema::dropIfExists('compteurs_factures');
    }
};
