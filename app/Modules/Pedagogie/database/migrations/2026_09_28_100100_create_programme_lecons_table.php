<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('programme_lecons')) {
            Schema::create('programme_lecons', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('programme_chapitre_id')
                    ->constrained('programme_chapitres')
                    ->cascadeOnDelete();
                $table->string('titre', 200);
                $table->text('objectifs')->nullable();
                $table->unsignedSmallInteger('ordre')->default(1);
                $table->decimal('volume_horaire_prevu', 4, 2)->default(1.0);
                $table->string('statut', 30)->default('a_faire');
                $table->timestamps();

                $table->index(['programme_chapitre_id', 'ordre'], 'lecon_chapitre_ordre_idx');
            });
        }

        if (Schema::hasTable('progressions') && ! Schema::hasColumn('progressions', 'lecon_id')) {
            Schema::table('progressions', function (Blueprint $table): void {
                $table->unsignedBigInteger('lecon_id')->nullable()->after('chapitre_id')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('progressions') && Schema::hasColumn('progressions', 'lecon_id')) {
            Schema::table('progressions', function (Blueprint $table): void {
                $table->dropIndex(['lecon_id']);
                $table->dropColumn('lecon_id');
            });
        }

        Schema::dropIfExists('programme_lecons');
    }
};
