<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programme_chapitres', function (Blueprint $table) {
            if (! Schema::hasColumn('programme_chapitres', 'type_structure')) {
                $table->string('type_structure', 50)->default('chapitre')->after('titre');
            }
        });

        Schema::table('programme_lecons', function (Blueprint $table) {
            if (! Schema::hasColumn('programme_lecons', 'type_element')) {
                $table->string('type_element', 50)->default('lecon')->after('titre');
            }
        });
    }

    public function down(): void
    {
        Schema::table('programme_chapitres', function (Blueprint $table) {
            if (Schema::hasColumn('programme_chapitres', 'type_structure')) {
                $table->dropColumn('type_structure');
            }
        });

        Schema::table('programme_lecons', function (Blueprint $table) {
            if (Schema::hasColumn('programme_lecons', 'type_element')) {
                $table->dropColumn('type_element');
            }
        });
    }
};
