<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluations', function (Blueprint $table): void {
            $table->unsignedBigInteger('annee_scolaire_id')->nullable()->after('classe_id');
            $table->unsignedBigInteger('periode_id')->nullable()->after('annee_scolaire_id');
            $table->unsignedBigInteger('enseignant_id')->nullable()->after('periode_id');
            $table->string('type_evaluation', 50)->default('devoir')->after('enseignant_id');
            $table->decimal('coefficient_evaluation', 5, 2)->default(1)->after('bareme');
            $table->index(['annee_scolaire_id', 'periode_id']);
        });
    }

    public function down(): void
    {
        Schema::table('evaluations', function (Blueprint $table): void {
            $table->dropIndex(['annee_scolaire_id', 'periode_id']);
            $table->dropColumn(['annee_scolaire_id', 'periode_id', 'enseignant_id', 'type_evaluation', 'coefficient_evaluation']);
        });
    }
};
