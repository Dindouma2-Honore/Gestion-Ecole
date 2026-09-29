<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents_transport', function (Blueprint $table) {
            $table->id();
            $table->foreignId('circuit_id')->constrained('circuits_transport');
            $table->text('description');
            $table->timestamp('date_incident')->nullable();
            $table->enum('gravite', ['mineur', 'majeur']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents_transport');
    }
};
