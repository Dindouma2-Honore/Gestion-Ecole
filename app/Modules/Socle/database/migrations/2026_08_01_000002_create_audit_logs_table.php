<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Migration historique neutralisée : A4 utilise exclusivement la
        // table `activity_log` du package spatie/laravel-activitylog.
        // Une éventuelle ancienne table locale n'est pas supprimée ici afin
        // de ne jamais effacer des journaux sans validation explicite.
    }

    public function down(): void {}
};
