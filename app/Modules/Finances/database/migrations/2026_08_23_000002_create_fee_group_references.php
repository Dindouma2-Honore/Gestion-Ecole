<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groupes_frais', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('nom', 100);
            $table->timestamps();
        });

        DB::table('groupes_frais')->insert([
            ['code' => 'scolarite', 'nom' => 'Frais de scolarité', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'autres', 'nom' => 'Autres frais', 'created_at' => now(), 'updated_at' => now()],
        ]);

        Schema::create('types_frais_recurrents', function (Blueprint $table): void {
            $table->id();
            $table->string('nom', 150);
            $table->foreignId('groupe_frais_id')->constrained('groupes_frais')->restrictOnDelete();
            $table->unsignedBigInteger('niveau_id')->nullable();
            $table->unsignedBigInteger('annee_scolaire_id');
            $table->decimal('montant', 12, 2);
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->foreign('niveau_id')->references('id')->on('niveaux')->nullOnDelete();
            $table->foreign('annee_scolaire_id')->references('id')->on('annees_scolaires')->cascadeOnDelete();
            $table->unique(['nom', 'niveau_id', 'annee_scolaire_id'], 'types_frais_recurrents_unique');
        });

        Schema::create('catalogue_frais_divers', function (Blueprint $table): void {
            $table->id();
            $table->string('nom', 150);
            $table->enum('categorie', ['examen', 'tenue', 'transport', 'fourniture', 'autre']);
            $table->foreignId('groupe_frais_id')->constrained('groupes_frais')->restrictOnDelete();
            $table->decimal('montant_defaut', 12, 2)->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::create('frais_divers_eleves', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('catalogue_frais_divers_id')->constrained('catalogue_frais_divers')->restrictOnDelete();
            // L'identité de l'élève traverse le contrat Scolarité ; aucune
            // relation Eloquent inter-module n'est introduite ici.
            $table->unsignedBigInteger('eleve_id');
            $table->unsignedBigInteger('annee_scolaire_id');
            $table->decimal('montant', 12, 2);
            $table->enum('statut', ['actif', 'annule'])->default('actif');
            $table->timestamps();
            $table->foreign('annee_scolaire_id')->references('id')->on('annees_scolaires')->cascadeOnDelete();
        });

        Schema::table('facture_preinscription_lignes', function (Blueprint $table): void {
            $table->unsignedBigInteger('groupe_frais_id')->nullable()->after('type_frais');
            $table->foreign('groupe_frais_id')->references('id')->on('groupes_frais')->restrictOnDelete();
        });

        $groupesFacture = DB::table('groupes_frais')->pluck('id', 'code');
        DB::table('facture_preinscription_lignes')
            ->whereIn('type_frais', ['inscription', 'scolarite'])
            ->update(['groupe_frais_id' => $groupesFacture['scolarite']]);
        DB::table('facture_preinscription_lignes')
            ->whereNotIn('type_frais', ['inscription', 'scolarite'])
            ->update(['groupe_frais_id' => $groupesFacture['autres']]);

        Schema::table('grilles_frais', function (Blueprint $table): void {
            $table->unsignedBigInteger('type_frais_recurrent_id')->nullable()->after('id');
        });

        $groupes = DB::table('groupes_frais')->pluck('id', 'code');
        foreach (DB::table('grilles_frais')->orderBy('id')->get() as $grille) {
            $type = (string) $grille->type_frais;
            $typeId = DB::table('types_frais_recurrents')->insertGetId([
                'nom' => Str::headline($type),
                'groupe_frais_id' => in_array($type, ['inscription', 'scolarite'], true) ? $groupes['scolarite'] : $groupes['autres'],
                'niveau_id' => $grille->niveau_id,
                'annee_scolaire_id' => $grille->annee_scolaire_id,
                'montant' => $grille->montant,
                'actif' => true,
                'created_at' => $grille->created_at ?? now(),
                'updated_at' => $grille->updated_at ?? now(),
            ]);
            DB::table('grilles_frais')->where('id', $grille->id)->update(['type_frais_recurrent_id' => $typeId]);
        }

        Schema::table('grilles_frais', function (Blueprint $table): void {
            $table->foreign('type_frais_recurrent_id')->references('id')->on('types_frais_recurrents')->restrictOnDelete();
            // MySQL réutilise parfois l'ancien index unique pour supporter
            // les clés étrangères existantes. Des index dédiés doivent donc
            // exister avant sa suppression.
            $table->index('niveau_id', 'grilles_frais_niveau_id_index');
            $table->index('annee_scolaire_id', 'grilles_frais_annee_scolaire_id_index');
            $table->dropUnique(['niveau_id', 'annee_scolaire_id', 'type_frais']);
            $table->dropColumn('type_frais');
        });
    }

    public function down(): void
    {
        Schema::table('facture_preinscription_lignes', function (Blueprint $table): void {
            $table->dropForeign(['groupe_frais_id']);
            $table->dropColumn('groupe_frais_id');
        });
        Schema::table('grilles_frais', function (Blueprint $table): void {
            $table->enum('type_frais', ['inscription', 'scolarite', 'examen', 'transport', 'cantine'])
                ->default('scolarite')
                ->after('annee_scolaire_id');
        });
        foreach (DB::table('grilles_frais')->get() as $grille) {
            $nom = Str::slug((string) DB::table('types_frais_recurrents')->where('id', $grille->type_frais_recurrent_id)->value('nom'));
            DB::table('grilles_frais')->where('id', $grille->id)->update([
                'type_frais' => in_array($nom, ['inscription', 'scolarite', 'examen', 'transport', 'cantine'], true)
                    ? $nom
                    : 'scolarite',
            ]);
        }
        Schema::table('grilles_frais', function (Blueprint $table): void {
            $table->dropForeign(['type_frais_recurrent_id']);
            $table->dropColumn('type_frais_recurrent_id');
            $table->unique(['niveau_id', 'annee_scolaire_id', 'type_frais']);
        });
        Schema::dropIfExists('frais_divers_eleves');
        Schema::dropIfExists('catalogue_frais_divers');
        Schema::dropIfExists('types_frais_recurrents');
        Schema::dropIfExists('groupes_frais');
    }
};
