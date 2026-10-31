<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eleves', function (Blueprint $table): void {
            $table->string('derniere_classe_frequente', 100)->nullable()->after('ancien_etablissement');
        });
    }

    public function down(): void
    {
        Schema::table('eleves', function (Blueprint $table): void {
            $table->dropColumn('derniere_classe_frequente');
        });
    }
};
