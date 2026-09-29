<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('types_frais_recurrents')) {
            DB::table('types_frais_recurrents')
                ->where('nature', 'inscription')
                ->update(['montant' => 15_000]);
        }

        if (Schema::hasTable('configurations_frais_classe')) {
            DB::table('configurations_frais_classe')->update([
                'frais_inscription' => 15_000,
                'montant_minimum_inscription' => 15_000,
            ]);
        }
    }

    public function down(): void
    {
        // Une migration inverse ne peut pas reconstituer les anciens tarifs.
    }
};
