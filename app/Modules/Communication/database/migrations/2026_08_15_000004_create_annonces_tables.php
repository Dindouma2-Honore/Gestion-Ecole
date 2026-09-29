<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('annonces', function (Blueprint $table) {
            $table->id();
            $table->string('titre');
            $table->text('contenu');
            $table->enum('cible_type', ['generale', 'classe', 'niveau', 'personnel']);
            $table->unsignedBigInteger('cible_id')->nullable();
            $table->date('date_publication');
            $table->date('date_expiration')->nullable();
            $table->unsignedBigInteger('document_id')->nullable();
            $table->foreignId('publie_par')->constrained('users');
            $table->timestamps();
        });

        Schema::create('annonce_accuses_lecture', function (Blueprint $table) {
            $table->id();
            $table->foreignId('annonce_id')->constrained('annonces')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('lu_le')->nullable();
            $table->timestamps();

            $table->unique(['annonce_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('annonce_accuses_lecture');
        Schema::dropIfExists('annonces');
    }
};
