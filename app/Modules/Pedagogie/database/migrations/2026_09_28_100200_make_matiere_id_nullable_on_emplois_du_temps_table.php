<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emplois_du_temps', function (Blueprint $table): void {
            $table->foreignId('matiere_id')->nullable()->change();
            if (! Schema::hasColumn('emplois_du_temps', 'libelle_activite')) {
                $table->string('libelle_activite')->nullable()->after('matiere_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('emplois_du_temps', function (Blueprint $table): void {
            if (Schema::hasColumn('emplois_du_temps', 'libelle_activite')) {
                $table->dropColumn('libelle_activite');
            }
            $table->foreignId('matiere_id')->nullable(false)->change();
        });
    }
};
