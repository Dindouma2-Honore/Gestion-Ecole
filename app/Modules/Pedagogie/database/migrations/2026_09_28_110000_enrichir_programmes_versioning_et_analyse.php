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
        Schema::table('programmes', function (Blueprint $table) {
            if (! Schema::hasColumn('programmes', 'version')) {
                $table->unsignedInteger('version')->default(1)->after('ordre');
            }
            if (! Schema::hasColumn('programmes', 'programme_parent_id')) {
                $table->unsignedBigInteger('programme_parent_id')->nullable()->after('version');
            }
            if (! Schema::hasColumn('programmes', 'metadata_analyse')) {
                $table->json('metadata_analyse')->nullable()->after('motif_rejet');
            }
            if (! Schema::hasColumn('programmes', 'erreur_analyse')) {
                $table->text('erreur_analyse')->nullable()->after('metadata_analyse');
            }
            if (! Schema::hasColumn('programmes', 'date_validation')) {
                $table->timestamp('date_validation')->nullable()->after('valide_par');
            }
        });

        // Assouplir le statut pour supporter les nouveaux états du workflow asynchrone (MySQL uniquement)
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE programmes MODIFY COLUMN statut VARCHAR(50) NOT NULL DEFAULT 'analyse_en_attente'");
        }
    }

    public function down(): void
    {
        Schema::table('programmes', function (Blueprint $table) {
            $table->dropColumn(array_filter([
                Schema::hasColumn('programmes', 'version') ? 'version' : null,
                Schema::hasColumn('programmes', 'programme_parent_id') ? 'programme_parent_id' : null,
                Schema::hasColumn('programmes', 'metadata_analyse') ? 'metadata_analyse' : null,
                Schema::hasColumn('programmes', 'erreur_analyse') ? 'erreur_analyse' : null,
                Schema::hasColumn('programmes', 'date_validation') ? 'date_validation' : null,
            ]));
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE programmes MODIFY COLUMN statut ENUM('brouillon','soumis','valide','rejete','publie') NOT NULL DEFAULT 'valide'");
        }
    }
};
