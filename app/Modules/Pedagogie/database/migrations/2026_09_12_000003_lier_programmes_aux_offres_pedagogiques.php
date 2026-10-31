<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programmes', function (Blueprint $table): void {
            $table->foreignId('offre_pedagogique_id')
                ->nullable()
                ->after('matiere_id')
                ->constrained('offres_pedagogiques')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('programmes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('offre_pedagogique_id');
        });
    }
};
