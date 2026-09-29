<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notes', function (Blueprint $table): void {
            if (! Schema::hasColumn('notes', 'document_copie_id')) {
                // Scan/photo de la copie de l'élève, gérée comme les autres pièces
                // jointes du module via Socle\Contracts\DocumentServiceContract —
                // jamais de FK inter-module (voir règle d'or du projet).
                $table->unsignedBigInteger('document_copie_id')->nullable()->after('valeur');
            }
        });
    }

    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table): void {
            if (Schema::hasColumn('notes', 'document_copie_id')) {
                $table->dropColumn('document_copie_id');
            }
        });
    }
};
