<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private array $legacyTables = [
        'annees_scolaires', 'users', 'niveaux', 'classes', 'eleves', 'parents_tuteurs',
        'inscriptions', 'employes', 'contrats', 'postes_administratifs', 'personnel_primes',
        'configurations_frais_classe', 'tranches_frais_classe', 'catalogue_frais_divers', 'types_frais_recurrents',
        'frais_divers_eleves', 'paiements', 'rubriques_depenses', 'depenses', 'matieres',
        'affectations_pedagogiques', 'evaluations', 'notes',
    ];

    public function up(): void
    {
        foreach ($this->legacyTables as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'facilg_legacy_id')) {
                continue;
            }
            Schema::table($tableName, function (Blueprint $table): void {
                $table->string('facilg_legacy_id', 191)->nullable()->index();
            });
        }

        Schema::table('eleves', function (Blueprint $table): void {
            $table->string('lieu_naissance')->nullable()->after('date_naissance');
            $table->string('nationalite')->nullable()->after('sexe');
            $table->text('observation')->nullable()->after('nationalite');
        });
        Schema::table('classes', function (Blueprint $table): void {
            $table->unsignedBigInteger('professeur_adjoints_id')->nullable()->after('professeur_principal_id');
        });

        Schema::create('diplomes_personnel', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('personnel_id')->constrained('employes')->cascadeOnDelete();
            $table->string('nom');
            $table->enum('type', ['academique', 'professionnel']);
            $table->unsignedSmallInteger('annee_obtention')->nullable();
            $table->string('facilg_legacy_id', 191)->nullable()->index();
            $table->timestamps();
        });

        Schema::create('facilg_clotures_caisse', function (Blueprint $table): void {
            $table->id();
            $table->string('facilg_legacy_id', 191)->unique();
            $table->unsignedBigInteger('annee_scolaire_id')->nullable();
            $table->dateTime('cloturee_le');
            $table->string('nature')->nullable();
            $table->decimal('total_decompte', 14, 2)->nullable();
            $table->json('detail_coupures')->nullable();
            $table->decimal('total_recalcule', 14, 2)->nullable();
            $table->decimal('ecart', 14, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('facilg_import_audits', function (Blueprint $table): void {
            $table->id();
            $table->string('facilg_legacy_id', 191)->unique();
            $table->string('source_table');
            $table->string('source_type');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('action');
            $table->text('motif')->nullable();
            $table->json('donnees')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facilg_import_audits');
        Schema::dropIfExists('facilg_clotures_caisse');
        Schema::dropIfExists('diplomes_personnel');
        Schema::table('classes', fn (Blueprint $table) => $table->dropColumn('professeur_adjoints_id'));
        Schema::table('eleves', fn (Blueprint $table) => $table->dropColumn(['lieu_naissance', 'nationalite', 'observation']));
        foreach (array_reverse($this->legacyTables) as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'facilg_legacy_id')) {
                Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn('facilg_legacy_id'));
            }
        }
    }
};
