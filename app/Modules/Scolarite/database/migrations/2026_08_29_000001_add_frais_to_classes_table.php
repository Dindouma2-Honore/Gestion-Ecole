<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            // Montant des frais de scolarité de la classe, renseigné à la
            // création. Utilisé pour afficher le montant à l'inscription et
            // pour générer la facture (voir FactureService).
            $table->decimal('frais', 10, 2)->default(0)->after('capacite_max');
        });
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropColumn('frais');
        });
    }
};
