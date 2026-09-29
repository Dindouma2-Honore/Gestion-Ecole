<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications_envoyees', function (Blueprint $table) {
            $table->id();
            $table->string('canal', 30);
            $table->string('code_template', 100);
            $table->string('destinataire_type');
            $table->unsignedBigInteger('destinataire_id');
            $table->string('destinataire_contact');
            $table->text('contenu_final');
            $table->string('statut', 30)->default('en_attente');
            $table->unsignedInteger('tentatives')->default(0);
            $table->boolean('accuse_reception')->default(false);
            $table->text('erreur_message')->nullable();
            $table->timestamp('envoyee_le')->nullable();
            $table->timestamps();

            $table->index(['destinataire_type', 'destinataire_id']);
            $table->index('statut');
        });

        Schema::create('file_attente_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')->constrained('notifications_envoyees')->cascadeOnDelete();
            $table->unsignedInteger('priorite')->default(5);
            $table->timestamp('prochaine_tentative')->nullable();
            $table->timestamps();

            $table->index('prochaine_tentative');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_attente_notifications');
        Schema::dropIfExists('notifications_envoyees');
    }
};
