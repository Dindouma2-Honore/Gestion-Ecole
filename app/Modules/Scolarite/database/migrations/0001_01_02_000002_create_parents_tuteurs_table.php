<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parents_tuteurs', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 100);
            $table->string('prenom', 100);
            $table->string('profession', 150)->nullable();
            $table->string('telephone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->boolean('portail_actif')->default(true);
            $table->timestamps();
        });

        Schema::create('eleve_parent', function (Blueprint $table) {
            $table->foreignId('eleve_id')->constrained('eleves')->cascadeOnDelete();
            $table->foreignId('parent_id')->constrained('parents_tuteurs')->cascadeOnDelete();
            $table->string('lien', 50)->nullable();
            $table->boolean('responsable_legal')->default(false);
            $table->boolean('responsable_paiement')->default(false);
            $table->boolean('autorise_recuperation')->default(false);
            $table->timestamps();
            $table->primary(['eleve_id', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eleve_parent');
        Schema::dropIfExists('parents_tuteurs');
    }
};
