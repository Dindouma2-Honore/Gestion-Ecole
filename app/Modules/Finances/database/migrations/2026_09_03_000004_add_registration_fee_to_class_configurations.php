<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configurations_frais_classe', function (Blueprint $table): void {
            $table->decimal('frais_inscription', 12, 2)->default(0)->after('annee_scolaire_id');
        });
    }

    public function down(): void
    {
        Schema::table('configurations_frais_classe', fn (Blueprint $table) => $table->dropColumn('frais_inscription'));
    }
};
