<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('factures_preinscription') && ! Schema::hasColumn('factures_preinscription', 'parent_telephone')) {
            Schema::table('factures_preinscription', function (Blueprint $table): void {
                $table->string('parent_telephone', 50)->nullable()->after('parent_email');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('factures_preinscription') && Schema::hasColumn('factures_preinscription', 'parent_telephone')) {
            Schema::table('factures_preinscription', function (Blueprint $table): void {
                $table->dropColumn('parent_telephone');
            });
        }
    }
};
