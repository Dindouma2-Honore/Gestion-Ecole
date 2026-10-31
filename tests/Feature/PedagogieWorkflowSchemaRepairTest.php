<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PedagogieWorkflowSchemaRepairTest extends TestCase
{
    public function test_repair_restores_missing_columns_without_resetting_existing_data(): void
    {
        config(['database.connections.workflow_repair' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        $previous = DB::getDefaultConnection();
        DB::setDefaultConnection('workflow_repair');

        try {
            Schema::create('notes', function (Blueprint $table): void {
                $table->id();
                $table->decimal('valeur', 8, 2);
                $table->boolean('verrouillee');
            });
            Schema::create('emplois_du_temps', function (Blueprint $table): void {
                $table->id();
                $table->boolean('actif');
            });
            Schema::create('programme_chapitres', fn (Blueprint $table) => $table->id());
            Schema::create('presences', fn (Blueprint $table) => $table->id());
            DB::table('notes')->insert([
                ['id' => 1, 'valeur' => 12.5, 'verrouillee' => true],
                ['id' => 2, 'valeur' => 16, 'verrouillee' => false],
            ]);
            DB::table('emplois_du_temps')->insert(['actif' => true]);
            $migration = require app_path('Modules/Pedagogie/database/migrations/2026_09_28_000001_reparer_colonnes_workflows_pedagogie.php');
            $migration->up();

            $this->assertSame('verrouille', DB::table('notes')->find(1)->statut);
            $this->assertSame('brouillon', DB::table('notes')->find(2)->statut);
            $this->assertEquals(12.5, DB::table('notes')->find(1)->valeur);
            $this->assertSame('publie', DB::table('emplois_du_temps')->first()->statut);
            $this->assertTrue(Schema::hasColumns('notes', ['soumis_par', 'soumis_le', 'valide_par', 'valide_le']));
            $this->assertTrue(Schema::hasColumns('programme_chapitres', ['objectif', 'contenu', 'volume_horaire_prevu', 'ressources', 'statut']));
            $this->assertTrue(Schema::hasColumns('presences', ['statut_justification', 'justification_verifiee_par', 'justification_verifiee_le']));

            DB::table('notes')->where('id', 2)->update(['statut' => 'valide', 'valide_par' => 42]);
            $migration->up();
            $this->assertSame('valide', DB::table('notes')->find(2)->statut);
            $this->assertSame(42, DB::table('notes')->find(2)->valide_par);
            $this->assertSame(2, DB::table('notes')->count());
        } finally {
            DB::purge('workflow_repair');
            DB::setDefaultConnection($previous);
        }
    }
}
