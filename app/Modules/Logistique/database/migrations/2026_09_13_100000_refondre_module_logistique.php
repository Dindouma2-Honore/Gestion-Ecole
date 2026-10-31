<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salles', function (Blueprint $table): void {
            $table->string('code', 40)->nullable()->unique()->after('id');
            $table->string('batiment')->nullable()->after('nom');
            $table->string('zone')->nullable()->after('batiment');
            $table->unsignedBigInteger('responsable_id')->nullable()->after('niveau_id');
            $table->boolean('disponible')->default(true)->after('etat');
            $table->text('observations')->nullable()->after('disponible');
        });

        Schema::table('travaux_infrastructure', function (Blueprint $table): void {
            $table->string('responsable')->nullable()->after('description');
            $table->decimal('budget_prevu', 12, 2)->nullable()->after('date_fin_reelle');
            $table->decimal('cout_reel', 12, 2)->nullable()->after('budget_prevu');
            $table->unsignedTinyInteger('avancement')->default(0)->after('cout_reel');
            $table->text('pieces_justificatives')->nullable()->after('avancement');
        });

        Schema::table('equipements', function (Blueprint $table): void {
            $table->string('fournisseur')->nullable()->after('responsable_id');
            $table->string('numero_serie')->nullable()->after('fournisseur');
            $table->text('observations')->nullable()->after('garantie_fin');
        });

        Schema::table('equipement_historique_localisations', function (Blueprint $table): void {
            $table->unsignedBigInteger('responsable_id')->nullable()->after('nouvelle_salle_id');
            $table->unsignedBigInteger('effectue_par')->nullable()->after('responsable_id');
            $table->text('motif')->nullable()->after('effectue_par');
        });

        Schema::table('demandes_intervention', function (Blueprint $table): void {
            $table->string('type_panne')->nullable()->after('type');
            $table->enum('priorite', ['basse', 'normale', 'haute', 'urgente'])->default('normale')->after('type_panne');
            $table->text('resultat')->nullable()->after('pieces_utilisees');
            $table->timestamp('date_intervention')->nullable()->after('date_signalement');
            $table->timestamp('date_cloture')->nullable()->after('date_reparation');
        });

        Schema::table('plans_maintenance_preventive', function (Blueprint $table): void {
            $table->unsignedBigInteger('responsable_id')->nullable()->after('equipement_id');
            $table->text('checklist')->nullable()->after('frequence_jours');
            $table->boolean('actif')->default(true)->after('prochaine_echeance');
        });

        Schema::table('vehicules', function (Blueprint $table): void {
            $table->string('code', 40)->nullable()->unique()->after('id');
            $table->unsignedBigInteger('chauffeur_id')->nullable()->after('etat');
            $table->unsignedBigInteger('accompagnateur_id')->nullable()->after('chauffeur_id');
            $table->text('observations')->nullable()->after('accompagnateur_id');
        });

        Schema::table('circuits_transport', function (Blueprint $table): void {
            $table->string('code', 40)->nullable()->unique()->after('id');
            $table->enum('statut', ['actif', 'suspendu', 'archive'])->default('actif')->after('accompagnateur_id');
        });

        Schema::table('arrets_circuit', function (Blueprint $table): void {
            $table->string('lieu')->nullable()->after('nom');
        });

        Schema::table('presences_transport', function (Blueprint $table): void {
            $table->enum('statut_circuit', ['planifie', 'en_route', 'retard', 'termine'])->default('planifie')->after('present');
        });

        Schema::table('menus_cantine', function (Blueprint $table): void {
            $table->string('repas')->nullable()->after('date_menu');
            $table->text('ingredients')->nullable()->after('description');
            $table->text('observations')->nullable()->after('allergenes');
        });

        Schema::table('livres', function (Blueprint $table): void {
            $table->text('description')->nullable()->after('niveau_id');
            $table->string('couverture')->nullable()->after('description');
        });

        Schema::table('exemplaires_livres', function (Blueprint $table): void {
            $table->string('localisation')->nullable()->after('code_exemplaire');
            $table->enum('statut', ['disponible', 'emprunte', 'reserve', 'perdu', 'endommage'])->default('disponible')->after('etat');
        });

        DB::table('salles')->where('etat', 'hors_service')->update(['disponible' => false]);
        DB::table('travaux_infrastructure')->whereNotNull('cout')->update(['cout_reel' => DB::raw('cout')]);
        DB::table('exemplaires_livres')->where('disponible', false)->update(['statut' => 'emprunte']);
    }

    public function down(): void
    {
        Schema::table('exemplaires_livres', fn (Blueprint $table) => $table->dropColumn(['localisation', 'statut']));
        Schema::table('livres', fn (Blueprint $table) => $table->dropColumn(['description', 'couverture']));
        Schema::table('menus_cantine', fn (Blueprint $table) => $table->dropColumn(['repas', 'ingredients', 'observations']));
        Schema::table('presences_transport', fn (Blueprint $table) => $table->dropColumn('statut_circuit'));
        Schema::table('arrets_circuit', fn (Blueprint $table) => $table->dropColumn('lieu'));
        Schema::table('circuits_transport', fn (Blueprint $table) => $table->dropColumn(['code', 'statut']));
        Schema::table('vehicules', fn (Blueprint $table) => $table->dropColumn(['code', 'chauffeur_id', 'accompagnateur_id', 'observations']));
        Schema::table('plans_maintenance_preventive', fn (Blueprint $table) => $table->dropColumn(['responsable_id', 'checklist', 'actif']));
        Schema::table('demandes_intervention', fn (Blueprint $table) => $table->dropColumn(['type_panne', 'priorite', 'resultat', 'date_intervention', 'date_cloture']));
        Schema::table('equipement_historique_localisations', fn (Blueprint $table) => $table->dropColumn(['responsable_id', 'effectue_par', 'motif']));
        Schema::table('equipements', fn (Blueprint $table) => $table->dropColumn(['fournisseur', 'numero_serie', 'observations']));
        Schema::table('travaux_infrastructure', fn (Blueprint $table) => $table->dropColumn(['responsable', 'budget_prevu', 'cout_reel', 'avancement', 'pieces_justificatives']));
        Schema::table('salles', fn (Blueprint $table) => $table->dropColumn(['code', 'batiment', 'zone', 'responsable_id', 'disponible', 'observations']));
    }
};
