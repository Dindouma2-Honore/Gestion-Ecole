<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courriers', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['entrant', 'sortant'])->default('entrant');
            $table->string('numero', 50)->unique();
            $table->string('objet');
            $table->string('expediteur');
            $table->string('destinataire');
            $table->unsignedBigInteger('service_affecte_id')->nullable();
            $table->string('statut', 30)->default('recu');
            $table->date('date_limite_reponse')->nullable();
            $table->timestamps();
        });

        Schema::create('courrier_historique_statuts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('courrier_id');
            $table->string('statut', 30);
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->text('commentaire')->nullable();
            $table->timestamp('changed_at');

            $table->foreign('courrier_id')->references('id')->on('courriers')->onDelete('cascade');
            $table->foreign('changed_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('courrier_pieces_jointes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('courrier_id');
            $table->unsignedBigInteger('document_id');

            $table->foreign('courrier_id')->references('id')->on('courriers')->onDelete('cascade');
            $table->foreign('document_id')->references('id')->on('documents')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courrier_pieces_jointes');
        Schema::dropIfExists('courrier_historique_statuts');
        Schema::dropIfExists('courriers');
    }
};
