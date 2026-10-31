<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eleves', function (Blueprint $table): void {
            $table->string('telephone_secondaire', 30)->nullable()->after('telephone_tuteur');
            $table->string('contact_urgence_nom', 150)->nullable()->after('telephone_secondaire');
            $table->string('contact_urgence_telephone', 30)->nullable()->after('contact_urgence_nom');
            $table->string('contact_urgence_lien', 50)->nullable()->after('contact_urgence_telephone');
        });
    }

    public function down(): void
    {
        Schema::table('eleves', function (Blueprint $table): void {
            $table->dropColumn([
                'telephone_secondaire',
                'contact_urgence_nom',
                'contact_urgence_telephone',
                'contact_urgence_lien',
            ]);
        });
    }
};
