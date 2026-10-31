<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('postes_administratifs', function (Blueprint $table): void {
            if (! Schema::hasColumn('postes_administratifs', 'parent_id')) {
                $table->foreignId('parent_id')
                    ->nullable()
                    ->after('description')
                    ->constrained('postes_administratifs')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('postes_administratifs', 'departement')) {
                $table->string('departement')->nullable()->after('parent_id');
            }

            if (! Schema::hasColumn('postes_administratifs', 'niveau_hierarchique')) {
                $table->unsignedInteger('niveau_hierarchique')->default(1)->after('departement');
            }

            if (! Schema::hasColumn('postes_administratifs', 'ordre')) {
                $table->unsignedInteger('ordre')->default(0)->after('niveau_hierarchique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('postes_administratifs', function (Blueprint $table): void {
            if (Schema::hasColumn('postes_administratifs', 'parent_id')) {
                $table->dropForeign(['parent_id']);
                $table->dropColumn('parent_id');
            }

            if (Schema::hasColumn('postes_administratifs', 'departement')) {
                $table->dropColumn('departement');
            }

            if (Schema::hasColumn('postes_administratifs', 'niveau_hierarchique')) {
                $table->dropColumn('niveau_hierarchique');
            }

            if (Schema::hasColumn('postes_administratifs', 'ordre')) {
                $table->dropColumn('ordre');
            }
        });
    }
};
