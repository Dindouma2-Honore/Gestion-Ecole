<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bulletin_lignes', function (Blueprint $table): void {
            $table->date('date_reference')->nullable()->after('montant');
        });
    }

    public function down(): void
    {
        Schema::table('bulletin_lignes', function (Blueprint $table): void {
            $table->dropColumn('date_reference');
        });
    }
};
