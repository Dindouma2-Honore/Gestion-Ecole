<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('nom')->default('')->after('name');
            $table->string('prenom')->default('')->after('nom');
            $table->string('telephone', 20)->nullable()->after('email');
            $table->timestamp('derniere_connexion_at')->nullable()->after('statut');

            $table->foreign('niveau_id')
                ->references('id')
                ->on('niveaux')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['niveau_id']);
            $table->dropColumn(['nom', 'prenom', 'telephone', 'derniere_connexion_at']);
        });
    }
};
