<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programme_chapitres', function (Blueprint $table): void {
            $table->json('parties')->nullable()->after('contenu');
        });
    }

    public function down(): void
    {
        Schema::table('programme_chapitres', function (Blueprint $table): void {
            $table->dropColumn('parties');
        });
    }
};
