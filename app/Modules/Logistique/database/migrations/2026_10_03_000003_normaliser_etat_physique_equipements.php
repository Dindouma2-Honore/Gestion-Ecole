<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('equipements')) {
            Schema::table('equipements', function (Blueprint $table): void {
                $table->string('etat', 50)->default('bon')->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('equipements')) {
            Schema::table('equipements', function (Blueprint $table): void {
                $table->enum('etat', ['bon', 'a_reparer', 'hors_service', 'mis_au_rebut'])->default('bon')->change();
            });
        }
    }
};
