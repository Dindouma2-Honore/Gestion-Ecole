<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories_materiel', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('nom', 120);
            $table->enum('nature', ['durable', 'consommable']);
            $table->boolean('actif')->default(true);
            $table->unsignedInteger('ordre')->default(0);
            $table->timestamps();
        });

        // equipements.categorie (texte libre) est volontairement conservée :
        // elle reste la donnée legacy tant que la correspondance des valeurs
        // historiques n'a pas été normalisée de façon fiable (voir LOT 2).
        Schema::table('equipements', function (Blueprint $table): void {
            $table->unsignedBigInteger('categorie_id')->nullable()->after('categorie')->index('equipements_categorie_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('equipements', function (Blueprint $table): void {
            $table->dropIndex('equipements_categorie_id_index');
            $table->dropColumn('categorie_id');
        });

        Schema::dropIfExists('categories_materiel');
    }
};
