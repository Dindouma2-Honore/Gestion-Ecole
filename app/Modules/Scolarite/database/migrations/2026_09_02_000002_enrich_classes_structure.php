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
        Schema::table('classes', function (Blueprint $table): void {
            $table->string('code', 50)->nullable()->after('nom');
            $table->unsignedBigInteger('filiere_id')->nullable()->after('niveau_id');
            $table->unsignedBigInteger('section_id')->nullable()->after('filiere_id');
            $table->unsignedBigInteger('salle_principale_id')->nullable()->after('section_id');
            $table->enum('statut', ['active', 'inactive', 'archivee'])->default('active')->after('capacite_max');
            $table->index(['filiere_id', 'section_id']);
        });
        DB::table('classes')->orderBy('id')->get()->each(fn (object $classe) => DB::table('classes')->where('id', $classe->id)->update(['code' => 'CL-'.str_pad((string) $classe->id, 5, '0', STR_PAD_LEFT)]));
        Schema::table('classes', function (Blueprint $table): void {
            $table->string('code', 50)->nullable(false)->change();
            $table->unique(['code', 'annee_scolaire_id'], 'classe_code_annee_unique');
        });
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table): void {
            $table->dropUnique('classe_code_annee_unique');
            $table->dropIndex(['filiere_id', 'section_id']);
            $table->dropColumn(['code', 'filiere_id', 'section_id', 'salle_principale_id', 'statut']);
        });
    }
};
