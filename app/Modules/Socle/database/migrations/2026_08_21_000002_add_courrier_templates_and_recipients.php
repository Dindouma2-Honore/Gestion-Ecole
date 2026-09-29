<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courrier_modeles', function (Blueprint $table): void {
            $table->id();
            $table->string('nom');
            $table->string('objet');
            $table->text('contenu');
            $table->json('variables')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::table('courriers', function (Blueprint $table): void {
            $table->unsignedBigInteger('modele_id')->nullable()->after('objet');
            $table->longText('contenu')->nullable()->after('destinataire');
            $table->string('cible_type', 40)->nullable()->after('contenu');
            $table->json('cible_config')->nullable()->after('cible_type');
            $table->string('canal', 20)->default('email')->after('cible_config');
            $table->timestamp('envoye_le')->nullable()->after('canal');
        });

        Schema::create('courrier_destinataires', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('courrier_id')->constrained('courriers')->cascadeOnDelete();
            $table->string('destinataire_type', 30);
            $table->unsignedBigInteger('destinataire_id');
            $table->string('nom');
            $table->string('email')->nullable();
            $table->string('telephone')->nullable();
            $table->string('statut', 20)->default('prepare');
            $table->unsignedBigInteger('notification_id')->nullable();
            $table->timestamps();
            $table->unique(['courrier_id', 'destinataire_type', 'destinataire_id'], 'courrier_dest_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courrier_destinataires');
        Schema::table('courriers', function (Blueprint $table): void {
            $table->dropColumn(['modele_id', 'contenu', 'cible_type', 'cible_config', 'canal', 'envoye_le']);
        });
        Schema::dropIfExists('courrier_modeles');
    }
};
