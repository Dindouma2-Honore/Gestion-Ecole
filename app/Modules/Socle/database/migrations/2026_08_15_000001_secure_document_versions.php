<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasIndex('document_versions', ['document_id', 'version_numero'], 'unique')) {
            Schema::table('document_versions', function (Blueprint $table): void {
                $table->unique(['document_id', 'version_numero']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('document_versions', ['document_id', 'version_numero'], 'unique')) {
            Schema::table('document_versions', function (Blueprint $table): void {
                $table->dropUnique(['document_id', 'version_numero']);
            });
        }
    }
};
