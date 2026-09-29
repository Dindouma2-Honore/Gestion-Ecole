<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classe_assistants', function (Blueprint $table): void {
            $table->unsignedBigInteger('classe_id');
            $table->unsignedBigInteger('employe_id');
            $table->primary(['classe_id', 'employe_id']);
            $table->index('employe_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classe_assistants');
    }
};
