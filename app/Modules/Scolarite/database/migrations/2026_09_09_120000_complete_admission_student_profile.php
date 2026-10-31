<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eleves', function (Blueprint $table): void {
            $table->string('ancien_etablissement')->nullable();
            $table->string('email')->nullable();
            $table->string('nom_tuteur')->nullable();
            $table->string('telephone_tuteur', 30)->nullable();
            $table->string('nom_pere')->nullable();
            $table->string('nom_mere')->nullable();
            $table->boolean('redoublant')->default(false);
            $table->boolean('transfere')->default(false);
            $table->string('numero_decision')->nullable();
            $table->boolean('exonere_frais_examen')->default(false);
            $table->boolean('demissionne')->default(false);
            $table->boolean('exclu_scolaire')->default(false);
            $table->boolean('info_sms')->default(false);
            $table->decimal('moyenne_legacy', 5, 2)->nullable();
            $table->boolean('exclu_inscription')->default(false);
            $table->integer('bonus_scolaire')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('eleves', fn (Blueprint $table) => $table->dropColumn([
            'ancien_etablissement', 'email', 'nom_tuteur', 'telephone_tuteur', 'nom_pere', 'nom_mere',
            'redoublant', 'transfere', 'numero_decision', 'exonere_frais_examen', 'demissionne',
            'exclu_scolaire', 'info_sms', 'moyenne_legacy', 'exclu_inscription', 'bonus_scolaire',
        ]));
    }
};
