<?php

declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('types_documents_eleve', function (Blueprint $t): void {
            $t->id();
            $t->string('code', 50)->unique();
            $t->string('nom');
            $t->boolean('obligatoire')->default(false);
            $t->boolean('actif')->default(true);
            $t->timestamps();
        });
        Schema::create('documents_eleve', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('eleve_id')->constrained('eleves')->cascadeOnDelete();
            $t->foreignId('type_document_eleve_id')->constrained('types_documents_eleve')->restrictOnDelete();
            $t->string('fichier');
            $t->date('date_ajout');
            $t->foreignId('ajoute_par')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['eleve_id', 'type_document_eleve_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents_eleve');
        Schema::dropIfExists('types_documents_eleve');
    }
};
