<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('types_frais_recurrents', 'facilg_legacy_id')) {
            Schema::table('types_frais_recurrents', fn (Blueprint $table) => $table->string('facilg_legacy_id', 191)->nullable()->index());
        }
        if (Schema::hasColumn('classes', 'professeur_adjoints_id') && ! Schema::hasColumn('classes', 'professeur_adjoint_id')) {
            Schema::table('classes', fn (Blueprint $table) => $table->renameColumn('professeur_adjoints_id', 'professeur_adjoint_id'));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('classes', 'professeur_adjoint_id') && ! Schema::hasColumn('classes', 'professeur_adjoints_id')) {
            Schema::table('classes', fn (Blueprint $table) => $table->renameColumn('professeur_adjoint_id', 'professeur_adjoints_id'));
        }
        if (Schema::hasColumn('types_frais_recurrents', 'facilg_legacy_id')) {
            Schema::table('types_frais_recurrents', fn (Blueprint $table) => $table->dropColumn('facilg_legacy_id'));
        }
    }
};
