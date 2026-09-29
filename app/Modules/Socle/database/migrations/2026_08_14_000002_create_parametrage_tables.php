<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('config_etablissement', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('logo_path')->nullable();
            $table->string('adresse')->nullable();
            $table->string('telephone')->nullable();
            $table->string('email')->nullable();
            $table->string('devise', 10)->default('XAF');
            $table->string('site_web')->nullable();
            $table->timestamps();
        });

        Schema::create('niveaux', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('code', 50)->unique();
            $table->integer('ordre')->default(0);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('formats_numerotation', function (Blueprint $table) {
            $table->id();
            $table->string('type_document', 50)->unique();
            $table->string('format');
            $table->unsignedInteger('prochain_numero')->default(1);
            $table->timestamps();
        });

        Schema::create('templates_documents', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('nom');
            $table->string('type', 50)->default('pdf');
            $table->longText('contenu_html')->nullable();
            $table->json('variables_disponibles')->nullable();
            $table->timestamps();
        });

        Schema::create('templates_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50);
            $table->enum('canal', ['email', 'sms', 'whatsapp', 'push']);
            $table->string('sujet')->nullable();
            $table->text('corps');
            $table->json('variables_disponibles')->nullable();
            $table->timestamps();
            $table->unique(['code', 'canal']);
        });

        Schema::create('jours_feries', function (Blueprint $table) {
            $table->id();
            $table->string('libelle');
            $table->date('date_debut');
            $table->date('date_fin');
            $table->unsignedBigInteger('niveau_id')->nullable();
            $table->boolean('recurrent')->default(false);
            $table->timestamps();

            $table->foreign('niveau_id')->references('id')->on('niveaux')->onDelete('cascade');
        });

        Schema::create('seuils_validation', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('categorie_depense_id');
            $table->decimal('montant_min', 12, 2)->default(0);
            $table->decimal('montant_max', 12, 2)->nullable();
            $table->string('role_validateur_requis');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seuils_validation');
        Schema::dropIfExists('jours_feries');
        Schema::dropIfExists('templates_notifications');
        Schema::dropIfExists('templates_documents');
        Schema::dropIfExists('formats_numerotation');
        Schema::dropIfExists('niveaux');
        Schema::dropIfExists('config_etablissement');
    }
};
