<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groupes', function (Blueprint $table): void {
            $table->id();
            $table->string('nom', 150)->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('groupe_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('groupe_id')->constrained('groupes')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['groupe_id', 'user_id'], 'groupe_user_unique');
        });

        Schema::create('habilitations_groupes_fonctionnalites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('groupe_id')->constrained('groupes')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('fonctionnalite_id')->constrained('catalogue_fonctionnalites')->cascadeOnUpdate()->cascadeOnDelete();
            $table->boolean('actif')->default(true);
            $table->foreignId('modifie_par')->nullable()->constrained('users')->nullOnDelete();
            $table->text('dernier_motif')->nullable();
            $table->timestamp('modifie_le')->nullable();
            $table->timestamps();

            $table->unique(['groupe_id', 'fonctionnalite_id'], 'habilitation_groupe_fonctionnalite_unique');
            $table->index(['groupe_id', 'actif']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('habilitations_groupes_fonctionnalites');
        Schema::dropIfExists('groupe_user');
        Schema::dropIfExists('groupes');
    }
};
