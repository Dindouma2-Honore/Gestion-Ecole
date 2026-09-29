<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('periodes', function (Blueprint $table): void {
            $table->string('libelle', 50)->nullable()->after('annee_scolaire_id');
        });

        DB::table('periodes')->whereNull('libelle')->update(['libelle' => DB::raw('nom')]);
    }

    public function down(): void
    {
        Schema::table('periodes', fn (Blueprint $table) => $table->dropColumn('libelle'));
    }
};
