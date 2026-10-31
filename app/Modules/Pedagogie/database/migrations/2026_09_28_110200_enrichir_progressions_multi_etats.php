<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('progressions', function (Blueprint $table) {
            if (! Schema::hasColumn('progressions', 'statut_element')) {
                $table->string('statut_element', 50)->default('terminee')->after('chapitre_termine');
            }
            if (! Schema::hasColumn('progressions', 'duree_minutes')) {
                $table->unsignedSmallInteger('duree_minutes')->nullable()->after('statut_element');
            }
            if (! Schema::hasColumn('progressions', 'date_progression')) {
                $table->date('date_progression')->nullable()->after('duree_minutes');
            }
            if (! Schema::hasColumn('progressions', 'observation')) {
                $table->text('observation')->nullable()->after('devoirs_donnes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('progressions', function (Blueprint $table) {
            $table->dropColumn(array_filter([
                Schema::hasColumn('progressions', 'statut_element') ? 'statut_element' : null,
                Schema::hasColumn('progressions', 'duree_minutes') ? 'duree_minutes' : null,
                Schema::hasColumn('progressions', 'date_progression') ? 'date_progression' : null,
                Schema::hasColumn('progressions', 'observation') ? 'observation' : null,
            ]));
        });
    }
};
