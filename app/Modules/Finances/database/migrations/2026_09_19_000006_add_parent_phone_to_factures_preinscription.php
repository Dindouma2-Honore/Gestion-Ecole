<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('factures_preinscription', function (Blueprint $table): void {
            $table->string('parent_telephone', 30)->nullable()->after('parent_email');
        });
    }

    public function down(): void
    {
        Schema::table('factures_preinscription', function (Blueprint $table): void {
            $table->dropColumn('parent_telephone');
        });
    }
};
