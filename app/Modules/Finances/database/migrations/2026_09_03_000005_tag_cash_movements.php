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
        Schema::table('mouvements_caisse', function (Blueprint $table): void {
            $table->string('module_origine', 40)->nullable()->after('rubrique');
            $table->string('sous_module', 80)->nullable()->after('module_origine');
            $table->string('reference_type')->nullable()->after('sous_module');
            $table->unsignedBigInteger('reference_id')->nullable()->after('reference_type');
            $table->index(['module_origine', 'sous_module', 'created_at'], 'mouvements_caisse_origine_index');
            $table->index(['reference_type', 'reference_id'], 'mouvements_caisse_reference_index');
        });

        DB::table('mouvements_caisse')->whereNotNull('source_type')->update([
            'reference_type' => DB::raw('source_type'),
            'reference_id' => DB::raw('source_id'),
        ]);
    }

    public function down(): void
    {
        Schema::table('mouvements_caisse', function (Blueprint $table): void {
            $table->dropIndex('mouvements_caisse_origine_index');
            $table->dropIndex('mouvements_caisse_reference_index');
            $table->dropColumn(['module_origine', 'sous_module', 'reference_type', 'reference_id']);
        });
    }
};
