<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('offres_pedagogiques', 'classe_id')) {
            Schema::table('offres_pedagogiques', fn (Blueprint $t) => $t->unsignedBigInteger('classe_id')->nullable()->index());
        }
        if (! Schema::hasColumn('programmes', 'offre_pedagogique_id')) {
            Schema::table('programmes', fn (Blueprint $t) => $t->foreignId('offre_pedagogique_id')->nullable()->constrained('offres_pedagogiques')->nullOnDelete());
        }
        if (! Schema::hasColumn('programme_chapitres', 'parties')) {
            Schema::table('programme_chapitres', fn (Blueprint $t) => $t->json('parties')->nullable());
        }
        // Level defaults and class overrides coexist; do not copy/delete legacy offers.
        if (Schema::hasIndex('offres_pedagogiques', 'offre_pedagogique_unique')) {
            // MySQL needs a replacement index for the subject foreign key.
            if (! Schema::hasIndex('offres_pedagogiques', 'offres_matiere_lookup_index')) {
                Schema::table('offres_pedagogiques', fn (Blueprint $t) => $t->index('matiere_id', 'offres_matiere_lookup_index'));
            }
            Schema::table('offres_pedagogiques', fn (Blueprint $t) => $t->dropUnique('offre_pedagogique_unique'));
        }
    }

    public function down(): void
    { /* Additive schema reconciliation: preserve historical data. */
    }
};
