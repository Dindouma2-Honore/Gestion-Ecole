<?php

declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories_matieres', function (Blueprint $table): void {
            $table->id();
            $table->string('nom', 100);
            $table->string('code', 30)->unique();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('ordre_affichage')->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });
        Schema::table('matieres', function (Blueprint $table): void {
            $table->foreignId('categorie_matiere_id')->nullable()->after('id')->constrained('categories_matieres')->restrictOnDelete();
        });
        $categorieHistoriqueId = DB::table('categories_matieres')->insertGetId(['nom' => 'Non classée', 'code' => 'NON_CLASSEE', 'description' => 'Catégorie de reprise des matières historiques', 'ordre_affichage' => 999, 'actif' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('matieres')->whereNull('categorie_matiere_id')->update(['categorie_matiere_id' => $categorieHistoriqueId]);
        Schema::table('matieres', function (Blueprint $table): void {
            $table->unsignedBigInteger('categorie_matiere_id')->nullable(false)->change();
        });
        Schema::create('offres_pedagogiques', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('matiere_id')->constrained('matieres')->restrictOnDelete();
            $table->unsignedBigInteger('niveau_id');
            $table->unsignedBigInteger('filiere_id')->nullable();
            $table->unsignedBigInteger('annee_scolaire_id');
            $table->foreignId('programme_id')->nullable()->constrained('programmes')->nullOnDelete();
            $table->decimal('coefficient_matiere', 5, 2)->default(1);
            $table->decimal('volume_horaire', 7, 2)->nullable();
            $table->decimal('heures_hebdomadaires', 5, 2)->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->unique(['matiere_id', 'niveau_id', 'filiere_id', 'annee_scolaire_id'], 'offre_pedagogique_unique');
        });
        Schema::create('affectations_pedagogiques', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('enseignant_id');
            $table->foreignId('matiere_id')->constrained('matieres')->restrictOnDelete();
            $table->unsignedBigInteger('classe_id');
            $table->unsignedBigInteger('annee_scolaire_id');
            $table->foreignId('offre_pedagogique_id')->nullable()->constrained('offres_pedagogiques')->nullOnDelete();
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->unique(['enseignant_id', 'matiere_id', 'classe_id', 'annee_scolaire_id'], 'affectation_pedagogique_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affectations_pedagogiques');
        Schema::dropIfExists('offres_pedagogiques');
        Schema::table('matieres', fn (Blueprint $table) => $table->dropConstrainedForeignId('categorie_matiere_id'));
        Schema::dropIfExists('categories_matieres');
    }
};
