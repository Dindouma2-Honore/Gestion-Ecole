<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Détail de chaque note prise en compte dans la moyenne d'une matière du
     * bulletin — nécessaire pour imprimer un "vrai" bulletin scolaire où
     * chaque évaluation (interrogation, devoir, composition...) apparaît
     * individuellement sous la matière, et pas seulement sa moyenne.
     */
    public function up(): void
    {
        Schema::create('bulletin_matiere_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bulletin_matiere_id')->constrained('bulletin_matieres')->cascadeOnDelete();
            $table->string('titre_evaluation', 150);
            $table->date('date_evaluation')->nullable();
            $table->decimal('valeur', 5, 2);
            $table->decimal('bareme', 5, 2);
            $table->decimal('note_sur_20', 5, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulletin_matiere_notes');
    }
};
