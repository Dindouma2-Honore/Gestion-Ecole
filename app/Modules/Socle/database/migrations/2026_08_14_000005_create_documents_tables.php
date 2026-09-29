<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('categorie', 50);
            $table->string('fichier_path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('taille'); // en octets
            $table->morphs('documentable');
            $table->date('date_expiration')->nullable();
            $table->enum('niveau_confidentialite', ['public', 'interne', 'restreint'])->default('interne');
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users');
        });

        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('document_id');
            $table->string('fichier_path');
            $table->unsignedInteger('version_numero');
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->foreign('document_id')->references('id')->on('documents')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users');
            $table->unique(['document_id', 'version_numero']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_versions');
        Schema::dropIfExists('documents');
    }
};
