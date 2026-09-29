<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('postes_administratifs', function (Blueprint $table): void {
            $table->id();
            $table->string('nom')->unique();
            $table->text('description')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });
        Schema::table('employes', function (Blueprint $table): void {
            $table->string('photo')->nullable()->after('email');
            $table->foreignId('role_id')->nullable()->after('photo')->constrained('roles')->nullOnDelete();
            $table->foreignId('poste_administratif_id')->nullable()->after('role_id')->constrained('postes_administratifs')->nullOnDelete();
            $table->foreignId('contrat_id')->nullable()->after('poste_administratif_id')->constrained('contrats')->nullOnDelete();
        });
        Schema::create('personnel_postes_historique', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employe_id')->constrained('employes')->cascadeOnDelete();
            $table->foreignId('poste_administratif_id')->constrained('postes_administratifs')->restrictOnDelete();
            $table->date('date_debut');
            $table->date('date_fin')->nullable();
            $table->text('motif');
            $table->timestamps();
        });
        Schema::create('categories_personnel', function (Blueprint $table): void {
            $table->id();
            $table->string('nom')->unique();
            $table->text('description')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });
        Schema::create('personnel_categorie', function (Blueprint $table): void {
            $table->foreignId('employe_id')->constrained('employes')->cascadeOnDelete();
            $table->foreignId('categorie_personnel_id')->constrained('categories_personnel')->cascadeOnDelete();
            $table->primary(['employe_id', 'categorie_personnel_id']);
        });
        Schema::table('types_primes', function (Blueprint $table): void {
            $table->boolean('actif')->default(true);
        });
        Schema::create('personnel_primes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employe_id')->constrained('employes')->cascadeOnDelete();
            $table->foreignId('type_prime_id')->constrained('types_primes')->restrictOnDelete();
            $table->decimal('valeur_override', 10, 2)->nullable();
            $table->date('date_attribution');
            $table->date('date_fin')->nullable();
            $table->boolean('actif')->default(true);
            $table->text('motif');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('absences_personnel', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employe_id')->constrained('employes')->cascadeOnDelete();
            $table->date('date_debut');
            $table->date('date_fin');
            $table->enum('type', ['absence_justifiee', 'absence_non_justifiee', 'conge', 'permission']);
            $table->text('motif');
            $table->foreignId('piece_justificative_document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->enum('statut', ['en_attente', 'validee', 'rejetee'])->default('en_attente');
            $table->foreignId('valide_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('date_validation')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absences_personnel');
        Schema::dropIfExists('personnel_primes');
        Schema::table('types_primes', fn (Blueprint $table) => $table->dropColumn('actif'));
        Schema::dropIfExists('personnel_categorie');
        Schema::dropIfExists('categories_personnel');
        Schema::dropIfExists('personnel_postes_historique');
        Schema::table('employes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('contrat_id');
            $table->dropConstrainedForeignId('poste_administratif_id');
            $table->dropConstrainedForeignId('role_id');
            $table->dropColumn('photo');
        });
        Schema::dropIfExists('postes_administratifs');
    }
};
