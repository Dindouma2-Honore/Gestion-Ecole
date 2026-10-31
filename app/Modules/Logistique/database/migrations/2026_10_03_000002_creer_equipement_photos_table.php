<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipement_photos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('equipement_id');
            $table->unsignedBigInteger('document_id'); // module Socle (A.5), pas de FK inter-module
            $table->string('type', 50)->default('principale');
            $table->text('description')->nullable();
            $table->boolean('principal')->default(false);
            $table->timestamps();

            $table->index('equipement_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipement_photos');
    }
};
