<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('reponses_parents');
        Schema::dropIfExists('message_destinataires');
        Schema::dropIfExists('messages_parents');

        Schema::create('messages_parents', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['individuel', 'collectif']);
            $table->foreignId('expediteur_id')->constrained('users');
            $table->string('sujet');
            $table->text('contenu');
            $table->string('cible_type', 50)->nullable();
            $table->unsignedBigInteger('cible_id')->nullable();
            $table->timestamps();
        });

        Schema::create('message_destinataires', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('messages_parents')->cascadeOnDelete();
            $table->foreignId('parent_id')->constrained('parents_tuteurs');
            $table->boolean('lu')->default(false);
            $table->foreignId('notification_id')->nullable()->constrained('notifications_envoyees')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('reponses_parents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('messages_parents')->cascadeOnDelete();
            $table->foreignId('parent_id')->constrained('parents_tuteurs');
            $table->text('contenu');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reponses_parents');
        Schema::dropIfExists('message_destinataires');
        Schema::dropIfExists('messages_parents');
    }
};
