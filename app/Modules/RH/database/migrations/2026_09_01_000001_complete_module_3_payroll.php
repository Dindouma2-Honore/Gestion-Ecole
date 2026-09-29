<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contrats', function (Blueprint $table): void {
            $table->enum('categorie_paie', ['fixe', 'horaire', 'mixte'])->default('fixe')->after('type');
            $table->decimal('taux_horaire', 10, 2)->nullable()->after('salaire_base');
        });

        Schema::table('bulletins_paie', function (Blueprint $table): void {
            $table->decimal('total_heures_supplementaires', 10, 2)->default(0)->after('total_primes');
            $table->foreignId('document_pdf_id')->nullable()->after('date_paiement')->constrained('documents')->nullOnDelete();
        });

        Schema::table('bulletin_lignes', function (Blueprint $table): void {
            $table->enum('type', ['gain', 'prime', 'heure_supp', 'retenue', 'cotisation', 'avance'])->change();
        });

        Schema::create('etats_virement', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('mois');
            $table->smallInteger('annee');
            $table->timestamp('date_generation');
            $table->foreignId('genere_par')->constrained('users')->restrictOnDelete();
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->timestamps();
            $table->unique(['mois', 'annee']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('etats_virement');

        Schema::table('bulletin_lignes', function (Blueprint $table): void {
            $table->enum('type', ['gain', 'prime', 'retenue', 'cotisation', 'avance'])->change();
        });

        Schema::table('bulletins_paie', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('document_pdf_id');
            $table->dropColumn('total_heures_supplementaires');
        });

        Schema::table('contrats', function (Blueprint $table): void {
            $table->dropColumn(['categorie_paie', 'taux_horaire']);
        });
    }
};
