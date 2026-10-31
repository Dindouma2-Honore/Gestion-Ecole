<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('catalogue_fonctionnalites', 'parent_id')) {
            Schema::table('catalogue_fonctionnalites', function (Blueprint $table): void {
                $table->foreignId('parent_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('catalogue_fonctionnalites')
                    ->nullOnDelete();
                $table->index(['module_technique', 'actif'], 'features_module_active_index');
            });
        }
    }

    public function down(): void
    {
        // Migration additive volontairement irréversible afin de préserver
        // la hiérarchie des fonctionnalités existantes.
    }
};
