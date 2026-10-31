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
        Schema::table('offres_pedagogiques', function (Blueprint $table): void {
            $table->unsignedBigInteger('classe_id')->nullable()->after('matiere_id');
            $table->index('classe_id');
        });

        DB::transaction(function (): void {
            DB::table('offres_pedagogiques')->orderBy('id')->get()->each(function (object $offre): void {
                $classeIds = DB::table('affectations_pedagogiques')
                    ->where('offre_pedagogique_id', $offre->id)
                    ->pluck('classe_id')
                    ->unique()
                    ->values();

                if ($classeIds->isEmpty()) {
                    $classeId = DB::table('classes')
                        ->where('niveau_id', $offre->niveau_id)
                        ->where('annee_scolaire_id', $offre->annee_scolaire_id)
                        ->when($offre->filiere_id, fn ($query) => $query->where('filiere_id', $offre->filiere_id))
                        ->value('id');

                    if ($classeId) {
                        $classeIds->push((int) $classeId);
                    }
                }

                if ($classeIds->isEmpty()) {
                    return;
                }

                $premiereClasseId = (int) $classeIds->shift();
                DB::table('offres_pedagogiques')->where('id', $offre->id)->update(['classe_id' => $premiereClasseId]);

                foreach ($classeIds as $classeId) {
                    $copie = (array) $offre;
                    unset($copie['id']);
                    $copie['classe_id'] = (int) $classeId;
                    $nouvelleOffreId = DB::table('offres_pedagogiques')->insertGetId($copie);

                    DB::table('affectations_pedagogiques')
                        ->where('offre_pedagogique_id', $offre->id)
                        ->where('classe_id', $classeId)
                        ->update(['offre_pedagogique_id' => $nouvelleOffreId]);
                }
            });
        });

        Schema::table('offres_pedagogiques', function (Blueprint $table): void {
            $table->unique(['matiere_id', 'classe_id', 'annee_scolaire_id'], 'offre_pedagogique_classe_unique');
        });
    }

    public function down(): void
    {
        Schema::table('offres_pedagogiques', function (Blueprint $table): void {
            $table->dropUnique('offre_pedagogique_classe_unique');
            $table->dropIndex(['classe_id']);
            $table->dropColumn('classe_id');
        });
    }
};
