<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Colonne nullable additive : trace quel bulletin a intégré une prime ponctuelle
        // et permet de la libérer si un bulletin « calculé » est supprimé.
        Schema::table('primes', function (Blueprint $table): void {
            $table->foreignId('bulletin_paie_id')->nullable()->after('statut')->constrained('bulletins_paie')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('primes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('bulletin_paie_id');
        });
    }
};
