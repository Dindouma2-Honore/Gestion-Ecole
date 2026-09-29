<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bascule de la facturation vers le circuit interne décrit par le Module 5
 * ("l'inscription ne va plus jusqu'au module Finances") :
 *
 * - `inscriptions.statut` perd l'état `en_cours` (préinscription en attente
 *   de parent payeur, qui dépendait du port InscriptionFacturationPort côté
 *   Finances) et devient exactement l'énumération du Module 5 : toute
 *   inscription démarre desormais `en_attente_versement`, jusqu'à
 *   confirmation du versement (`active`), ou `annulee`.
 * - `classes.frais` est supprimée : le montant de la scolarité n'est plus
 *   un unique montant par classe mais résolu frais par frais via la
 *   nouvelle grille tarifaire (voir grille_tarifaire / FraisService).
 * - L'ancienne table `factures` (une facture unique par inscription) est
 *   remplacée par `factures_generees` (provisoire puis définitive).
 * - Le compteur local `compteurs_factures` est supprimé : la numérotation
 *   passe désormais par `formats_numerotation` (Socle), voir
 *   2026_09_01_000013_seed_formats_numerotation_scolarite — le contrat
 *   ParametrageServiceContract étant maintenant livré et vérifié.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            // On élargit d'abord l'ENUM pour qu'il accepte à la fois les
            // anciennes et les nouvelles valeurs pendant la conversion.
            DB::statement("ALTER TABLE inscriptions MODIFY statut ENUM('en_cours', 'en_attente_versement', 'validee', 'annulee', 'active') NOT NULL DEFAULT 'en_cours'");

            DB::table('inscriptions')->where('statut', 'en_cours')->update(['statut' => 'en_attente_versement']);
            DB::table('inscriptions')->where('statut', 'validee')->update(['statut' => 'active']);

            // Puis on restreint l'ENUM aux seules valeurs finales.
            DB::statement("ALTER TABLE inscriptions MODIFY statut ENUM('en_attente_versement', 'active', 'annulee') NOT NULL DEFAULT 'en_attente_versement'");
        }
    }

    public function down(): void
    {
        Schema::create('compteurs_factures', function (Blueprint $table) {
            $table->id();
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
            $table->unsignedBigInteger('annee_scolaire_id');
            $table->decimal('montant', 10, 2);
            $table->date('date_emission');
            $table->foreignId('parent_id')->nullable()->constrained('parents_tuteurs');
            $table->string('nom_destinataire', 200)->nullable();
            $table->string('telephone_destinataire', 30)->nullable();
            $table->enum('statut_envoi', ['en_attente', 'envoyee', 'echec'])->default('en_attente');
            $table->string('canal_envoi', 30)->nullable();
            $table->timestamp('envoyee_le')->nullable();
            $table->string('echec_raison')->nullable();
            $table->timestamps();
        });

        Schema::table('classes', function (Blueprint $table) {
            $table->decimal('frais', 10, 2)->default(0)->after('capacite_max');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::table('inscriptions')->where('statut', 'active')->update(['statut' => 'validee']);
            DB::statement("ALTER TABLE inscriptions MODIFY statut ENUM('en_cours', 'en_attente_versement', 'validee', 'annulee') NOT NULL DEFAULT 'en_cours'");
        }
    }
};
