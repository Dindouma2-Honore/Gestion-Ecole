<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technical_measurements', function (Blueprint $table) {
            $table->id();
            $table->string('component', 100);
            $table->string('status', 32);
            $table->json('metrics')->nullable();
            $table->timestamp('measured_at');
            $table->timestamps();
            $table->index(['component', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technical_measurements');
    }
};
