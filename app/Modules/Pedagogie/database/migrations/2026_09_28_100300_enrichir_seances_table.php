<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seances', function (Blueprint $table): void {
            if (! Schema::hasColumn('seances', 'enseignant_prevu_id')) {
                $table->unsignedBigInteger('enseignant_prevu_id')->nullable()->after('enseignant_id');
            }
            if (! Schema::hasColumn('seances', 'enseignant_reel_id')) {
                $table->unsignedBigInteger('enseignant_reel_id')->nullable()->after('enseignant_prevu_id');
            }
            if (! Schema::hasColumn('seances', 'salle_id')) {
                $table->unsignedBigInteger('salle_id')->nullable()->after('enseignant_reel_id');
            }
            if (! Schema::hasColumn('seances', 'programme_lecon_id')) {
                $table->unsignedBigInteger('programme_lecon_id')->nullable()->after('salle_id');
            }
            if (! Schema::hasColumn('seances', 'contenu_enseigne')) {
                $table->text('contenu_enseigne')->nullable()->after('programme_lecon_id');
            }
            if (! Schema::hasColumn('seances', 'devoirs_donnes')) {
                $table->text('devoirs_donnes')->nullable()->after('contenu_enseigne');
            }
            if (! Schema::hasColumn('seances', 'motif_remplacement')) {
                $table->string('motif_remplacement')->nullable()->after('devoirs_donnes');
            }
            if (! Schema::hasColumn('seances', 'motif_annulation')) {
                $table->string('motif_annulation')->nullable()->after('motif_remplacement');
            }
            if (! Schema::hasColumn('seances', 'duree_minutes')) {
                $table->integer('duree_minutes')->nullable()->after('motif_annulation');
            }
            if (! Schema::hasColumn('seances', 'appel_verrouille')) {
                $table->boolean('appel_verrouille')->default(false)->after('progression_renseignee');
            }
        });
    }

    public function down(): void
    {
        Schema::table('seances', function (Blueprint $table): void {
            $table->dropColumn([
                'enseignant_prevu_id',
                'enseignant_reel_id',
                'salle_id',
                'programme_lecon_id',
                'contenu_enseigne',
                'devoirs_donnes',
                'motif_remplacement',
                'motif_annulation',
                'duree_minutes',
                'appel_verrouille',
            ]);
        });
    }
};
