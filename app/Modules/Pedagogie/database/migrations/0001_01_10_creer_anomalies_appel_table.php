<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anomalies_appel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seance_id')->unique()->constrained('seances');
            $table->timestamp('detectee_le');
            $table->boolean('notifiee')->default(false);
            $table->boolean('resolue')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anomalies_appel');
    }
};
