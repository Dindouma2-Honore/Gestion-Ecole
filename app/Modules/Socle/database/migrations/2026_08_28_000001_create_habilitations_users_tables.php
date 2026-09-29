<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('habilitations_users_fonctionnalites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('fonctionnalite_id')->constrained('catalogue_fonctionnalites')->cascadeOnUpdate()->cascadeOnDelete();
            $table->boolean('actif')->default(true);
            $table->foreignId('modifie_par')->nullable()->constrained('users')->nullOnDelete();
            $table->text('dernier_motif')->nullable();
            $table->timestamp('modifie_le')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'fonctionnalite_id'], 'habilitation_user_fonctionnalite_unique');
            $table->index(['user_id', 'actif']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('habilitations_users_fonctionnalites');
    }
};
