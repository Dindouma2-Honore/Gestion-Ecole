<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sorties_eleves', function (Blueprint $table): void {
            $table->text('motif')->nullable()->after('type');
            $table->timestamp('heure_prevue')->nullable()->after('motif');
            $table->string('statut', 30)->default('effectuee')->after('heure_sortie');
            $table->unsignedBigInteger('autorise_par')->nullable()->after('remis_par');
            $table->timestamp('autorise_le')->nullable()->after('autorise_par');
            $table->timestamp('cloturee_le')->nullable()->after('autorise_le');
            $table->text('commentaire')->nullable()->after('cloturee_le');
        });

        Schema::table('visiteurs', function (Blueprint $table): void {
            $table->string('prenom')->nullable()->after('nom');
            $table->string('type_piece')->nullable()->after('photo_path');
            $table->string('numero_piece')->nullable()->after('type_piece');
            $table->string('service_concerne')->nullable()->after('personne_visitee_id');
            $table->boolean('badge_rendu')->default(false)->after('badge_numero');
            $table->text('observations')->nullable()->after('incident_signale');
            $table->text('observation_sortie')->nullable()->after('observations');
        });

        Schema::table('dossiers_sante', function (Blueprint $table): void {
            $table->text('antecedents_medicaux')->nullable()->after('maladies_chroniques');
            $table->text('traitements_connus')->nullable()->after('antecedents_medicaux');
            $table->text('restrictions')->nullable()->after('medicaments_autorises');
            $table->text('informations_importantes')->nullable()->after('restrictions');
            $table->text('observations')->nullable()->after('medecin_traitant');
        });

        Schema::table('visite_infirmeries', function (Blueprint $table): void {
            $table->text('symptomes')->nullable()->after('motif');
            $table->string('dose_administree')->nullable()->after('medicament_administre');
            $table->string('decision', 40)->nullable()->after('gravite');
            $table->text('observations')->nullable()->after('decision');
            $table->string('canal_notification_parent', 30)->nullable()->after('parent_notifie');
            $table->timestamp('parent_notifie_le')->nullable()->after('canal_notification_parent');
            $table->text('commentaire_notification')->nullable()->after('parent_notifie_le');
        });

        Schema::table('reclamations', function (Blueprint $table): void {
            $table->string('reference', 50)->nullable()->unique()->after('id');
            $table->string('canal', 30)->nullable()->after('signale_par_id');
            $table->string('sujet')->nullable()->after('canal');
        });

        Schema::create('incidents_vie_scolaire', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 50)->unique();
            $table->string('categorie', 50);
            $table->dateTime('date_incident');
            $table->string('lieu')->nullable();
            $table->string('personne_concernee_type')->nullable();
            $table->unsignedBigInteger('personne_concernee_id')->nullable();
            $table->text('description');
            $table->enum('priorite', ['basse', 'normale', 'haute', 'urgente'])->default('normale');
            $table->unsignedBigInteger('responsable_id')->nullable();
            $table->string('statut', 30)->default('signale');
            $table->text('resolution')->nullable();
            $table->unsignedBigInteger('cree_par')->nullable();
            $table->timestamps();
        });

        Schema::create('incident_vie_scolaire_historiques', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('incident_id')->constrained('incidents_vie_scolaire')->cascadeOnDelete();
            $table->string('ancien_statut', 30)->nullable();
            $table->string('nouveau_statut', 30);
            $table->unsignedBigInteger('modifie_par')->nullable();
            $table->text('motif')->nullable();
            $table->timestamp('modifie_le');
        });

        Schema::table('jours_feries', function (Blueprint $table): void {
            $table->string('type', 40)->default('ferie_national')->after('libelle');
            $table->text('description')->nullable()->after('date_fin');
            $table->boolean('affecte_cours')->default(true)->after('description');
            $table->boolean('affecte_personnel')->default(true)->after('affecte_cours');
            $table->boolean('etablissement_ferme')->default(true)->after('affecte_personnel');
            $table->boolean('notifier')->default(false)->after('etablissement_ferme');
            $table->string('statut', 20)->default('actif')->after('notifier');
        });

        Schema::table('evenements', function (Blueprint $table): void {
            $table->string('reference', 50)->nullable()->unique()->after('id');
            $table->string('type', 50)->default('autre')->after('titre');
            $table->foreignId('salle_id')->nullable()->after('lieu')->constrained('salles')->nullOnDelete();
            $table->foreignId('vehicule_id')->nullable()->after('necessite_transport')->constrained('vehicules')->nullOnDelete();
            $table->text('materiel_requis')->nullable()->after('vehicule_id');
        });
    }

    public function down(): void
    {
        Schema::table('evenements', fn (Blueprint $table) => $table->dropConstrainedForeignId('vehicule_id'));
        Schema::table('evenements', fn (Blueprint $table) => $table->dropConstrainedForeignId('salle_id'));
        Schema::table('evenements', fn (Blueprint $table) => $table->dropColumn(['reference', 'type', 'materiel_requis']));
        Schema::table('jours_feries', fn (Blueprint $table) => $table->dropColumn(['type', 'description', 'affecte_cours', 'affecte_personnel', 'etablissement_ferme', 'notifier', 'statut']));
        Schema::dropIfExists('incident_vie_scolaire_historiques');
        Schema::dropIfExists('incidents_vie_scolaire');
        Schema::table('reclamations', fn (Blueprint $table) => $table->dropColumn(['reference', 'canal', 'sujet']));
        Schema::table('visite_infirmeries', fn (Blueprint $table) => $table->dropColumn(['symptomes', 'dose_administree', 'decision', 'observations', 'canal_notification_parent', 'parent_notifie_le', 'commentaire_notification']));
        Schema::table('dossiers_sante', fn (Blueprint $table) => $table->dropColumn(['antecedents_medicaux', 'traitements_connus', 'restrictions', 'informations_importantes', 'observations']));
        Schema::table('visiteurs', fn (Blueprint $table) => $table->dropColumn(['prenom', 'type_piece', 'numero_piece', 'service_concerne', 'badge_rendu', 'observations', 'observation_sortie']));
        Schema::table('sorties_eleves', fn (Blueprint $table) => $table->dropColumn(['motif', 'heure_prevue', 'statut', 'autorise_par', 'autorise_le', 'cloturee_le', 'commentaire']));
    }
};
