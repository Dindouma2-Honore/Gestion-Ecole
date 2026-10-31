<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('periodes', 'parent_id')) {
            Schema::table('periodes', function (Blueprint $table): void {
                $table->foreignId('parent_id')
                    ->nullable()
                    ->after('annee_scolaire_id')
                    ->constrained('periodes')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('periodes', 'status')) {
            Schema::table('periodes', function (Blueprint $table): void {
                $table->string('status', 10)->default('open')->after('cloturee');
            });
        }

        DB::table('periodes')
            ->where('cloturee', true)
            ->update(['status' => 'closed']);

        Schema::table('periodes', function (Blueprint $table): void {
            $table->index(['annee_scolaire_id', 'parent_id', 'ordre'], 'periodes_hierarchy_order_index');
        });
    }

    public function down(): void
    {
        // Migration additive volontairement irréversible afin de préserver
        // l'état explicite des périodes historiques.
    }
};
