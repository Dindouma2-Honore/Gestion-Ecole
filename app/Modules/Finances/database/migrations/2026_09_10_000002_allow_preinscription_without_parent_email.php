<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('factures_preinscription', function (Blueprint $table): void {
            $table->string('parent_email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('factures_preinscription', function (Blueprint $table): void {
            $table->string('parent_email')->nullable(false)->change();
        });
    }
};
