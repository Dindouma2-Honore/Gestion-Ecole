<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presences', function (Blueprint $table): void {
            $table->string('statut', 30)->change();
        });
    }

    public function down(): void
    {
        Schema::table('presences', function (Blueprint $table): void {
            $table->enum('statut', ['present', 'absent', 'retard', 'depart_anticipe'])->change();
        });
    }
};
