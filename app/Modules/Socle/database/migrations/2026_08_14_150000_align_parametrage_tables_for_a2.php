<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('config_etablissement', function (Blueprint $table): void {
            $table->string('rccm')->nullable()->after('site_web');
            $table->string('niu')->nullable()->after('rccm');
        });

        Schema::table('templates_documents', function (Blueprint $table): void {
            $table->string('fichier_template')->nullable()->after('type');
            $table->boolean('actif')->default(true)->after('fichier_template');
            $table->unsignedInteger('version')->default(1)->after('actif');
        });

        Schema::table('templates_notifications', function (Blueprint $table): void {
            $table->text('contenu')->nullable()->after('sujet');
            $table->boolean('actif')->default(true)->after('contenu');
        });
        DB::table('templates_notifications')->whereNull('contenu')->update(['contenu' => DB::raw('corps')]);

        if (DB::getDriverName() === 'mysql') {
            // Une chaîne contrôlée par validation applicative permet d'ajouter
            // `in_app` sans convertir ni perdre d'anciens canaux déjà stockés.
            DB::statement('ALTER TABLE templates_notifications MODIFY canal VARCHAR(20) NOT NULL');
        }

        Schema::table('jours_feries', function (Blueprint $table): void {
            $table->date('date')->nullable()->after('libelle');
        });
        DB::table('jours_feries')->whereNull('date')->update(['date' => DB::raw('date_debut')]);

        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->string('group');
            $table->string('name');
            $table->boolean('locked')->default(false);
            $table->json('payload');
            $table->timestamps();
            $table->unique(['group', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::table('jours_feries', fn (Blueprint $table) => $table->dropColumn('date'));
        Schema::table('templates_notifications', fn (Blueprint $table) => $table->dropColumn(['contenu', 'actif']));
        Schema::table('templates_documents', fn (Blueprint $table) => $table->dropColumn(['fichier_template', 'actif', 'version']));
        Schema::table('config_etablissement', fn (Blueprint $table) => $table->dropColumn(['rccm', 'niu']));
    }
};
