<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidatures', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->string('nom');
            $table->string('prenom');
            $table->string('email')->nullable();
            $table->string('telephone', 30)->nullable();
            $table->string('poste_souhaite');
            $table->string('cv_path')->nullable();
            $table->enum('statut', ['nouvelle', 'preselectionnee', 'entretien', 'retenue', 'rejetee', 'embauche'])->default('nouvelle');
            $table->dateTime('date_entretien')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('evaluee_par')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('employe_id')->nullable()->constrained('employes')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidatures');
    }
};
