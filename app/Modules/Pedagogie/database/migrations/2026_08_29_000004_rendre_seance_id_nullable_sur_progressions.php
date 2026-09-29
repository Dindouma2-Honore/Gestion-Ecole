<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Permet à l'enseignant de déclarer un chapitre du programme comme déjà
     * couvert sans passer par une séance précise (rattrapage de progression
     * antérieure au logiciel, déclaration groupée en début d'année, etc.) —
     * voir ProgressionService::declarerChapitreCouvert(). Le flux habituel
     * (cahier de texte lié à une séance dispensée) reste inchangé.
     *
     * Écrit en SQL brut pour ne pas dépendre de doctrine/dbal (nécessaire à
     * Schema::table(...)->change() et non garanti présent dans le projet).
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        match ($driver) {
            'pgsql' => DB::statement('ALTER TABLE progressions ALTER COLUMN seance_id DROP NOT NULL'),
            'sqlite' => null, // SQLite n'impose pas ce changement au niveau schéma ; pas d'action nécessaire.
            default => DB::statement('ALTER TABLE progressions MODIFY seance_id BIGINT UNSIGNED NULL'),
        };
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        match ($driver) {
            'pgsql' => DB::statement('ALTER TABLE progressions ALTER COLUMN seance_id SET NOT NULL'),
            'sqlite' => null,
            default => DB::statement('ALTER TABLE progressions MODIFY seance_id BIGINT UNSIGNED NOT NULL'),
        };
    }
};
