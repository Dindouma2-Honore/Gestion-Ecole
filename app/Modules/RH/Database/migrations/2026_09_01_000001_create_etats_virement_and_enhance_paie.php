<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('etats_virement')) {
            Schema::create('etats_virement', function (Blueprint $table): void {
                $table->id();
                $table->unsignedTinyInteger('mois');
                $table->smallInteger('annee');
                $table->timestamp('date_generation');
                $table->foreignId('genere_par')->constrained('users')->cascadeOnDelete();
                $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('bulletins_paie') && ! Schema::hasColumn('bulletins_paie', 'total_heures_supplementaires')) {
            Schema::table('bulletins_paie', function (Blueprint $table): void {
                $table->decimal('total_heures_supplementaires', 10, 2)->default(0)->after('total_primes');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('bulletins_paie') && Schema::hasColumn('bulletins_paie', 'total_heures_supplementaires')) {
            Schema::table('bulletins_paie', function (Blueprint $table): void {
                $table->dropColumn('total_heures_supplementaires');
            });
        }

        Schema::dropIfExists('etats_virement');
    }
};
