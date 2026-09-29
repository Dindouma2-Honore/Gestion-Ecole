<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evenement_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evenement_id')->constrained('evenements');
            $table->unsignedBigInteger('document_id'); // module Socle (A.5), pas de FK inter-module
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evenement_photos');
    }
};
