<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'niveau_id')) {
                $table->unsignedBigInteger('niveau_id')->nullable()->after('id');
            }
            if (! Schema::hasColumn('users', 'statut')) {
                $table->enum('statut', ['actif', 'suspendu', 'desactive'])->default('actif')->after('password');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['niveau_id', 'statut']);
        });
    }
};
