<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluations', function (Blueprint $table): void {
            if (! Schema::hasColumn('evaluations', 'mode_evaluation')) {
                $table->string('mode_evaluation', 30)->default('note')->after('type_evaluation');
            }
            if (! Schema::hasColumn('evaluations', 'verrouillee')) {
                $table->boolean('verrouillee')->default(false)->after('bareme');
            }
        });

        Schema::table('notes', function (Blueprint $table): void {
            if (! Schema::hasColumn('notes', 'statut_presence')) {
                $table->string('statut_presence', 30)->default('present')->after('absent');
            }
            if (! Schema::hasColumn('notes', 'competence_niveau')) {
                $table->string('competence_niveau', 50)->nullable()->after('valeur');
            }
            if (! Schema::hasColumn('notes', 'appreciation')) {
                $table->text('appreciation')->nullable()->after('competence_niveau');
            }
        });
    }

    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table): void {
            $table->dropColumn(['statut_presence', 'competence_niveau', 'appreciation']);
        });

        Schema::table('evaluations', function (Blueprint $table): void {
            $table->dropColumn(['mode_evaluation', 'verrouillee']);
        });
    }
};
