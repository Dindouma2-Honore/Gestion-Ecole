<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('devoirs')) {
            Schema::create('devoirs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('seance_id')->constrained('seances')->cascadeOnDelete();
                $table->string('titre', 200);
                $table->text('description')->nullable();
                $table->string('type_devoir', 50)->default('exercice');
                $table->date('date_limite')->nullable();
                $table->unsignedBigInteger('document_joint_id')->nullable();
                $table->unsignedBigInteger('donne_par')->nullable();
                $table->timestamps();

                $table->index(['seance_id', 'date_limite']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('devoirs');
    }
};
