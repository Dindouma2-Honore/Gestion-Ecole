<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sanctions_personnel', function (Blueprint $table): void {
            if (! Schema::hasColumn('sanctions_personnel', 'observations')) {
                $table->text('observations')->nullable()->after('motif');
            }
        });

        Schema::table('diplomes_personnel', function (Blueprint $table): void {
            if (! Schema::hasColumn('diplomes_personnel', 'etablissement')) {
                $table->string('etablissement')->nullable()->after('type');
            }
            if (! Schema::hasColumn('diplomes_personnel', 'document_id')) {
                $table->foreignId('document_id')->nullable()->after('annee_obtention')->constrained('documents')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('diplomes_personnel', function (Blueprint $table): void {
            if (Schema::hasColumn('diplomes_personnel', 'document_id')) {
                $table->dropConstrainedForeignId('document_id');
            }
            if (Schema::hasColumn('diplomes_personnel', 'etablissement')) {
                $table->dropColumn('etablissement');
            }
        });

        Schema::table('sanctions_personnel', function (Blueprint $table): void {
            if (Schema::hasColumn('sanctions_personnel', 'observations')) {
                $table->dropColumn('observations');
            }
        });
    }
};
