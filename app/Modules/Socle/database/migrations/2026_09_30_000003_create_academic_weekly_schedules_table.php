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
        if (! Schema::hasTable('academic_weekly_schedules')) {
            Schema::create('academic_weekly_schedules', function (Blueprint $table): void {
                $table->id();
                $table->unsignedTinyInteger('day_of_week')->unique();
                $table->boolean('is_school_open')->default(false);
                $table->boolean('is_teaching_day')->default(false);
                $table->time('starts_at')->nullable();
                $table->time('ends_at')->nullable();
                $table->timestamps();
            });
        }

        if (DB::table('academic_weekly_schedules')->exists()) {
            return;
        }

        $workingDays = Schema::hasColumn('config_etablissement', 'jours_ouvrables')
            ? DB::table('config_etablissement')->value('jours_ouvrables')
            : null;
        $workingDays = is_array($workingDays) ? $workingDays : json_decode((string) $workingDays, true);
        $workingDays = $workingDays ?: [1, 2, 3, 4, 5, 6];
        $now = now();
        foreach (range(1, 7) as $dayOfWeek) {
            $open = in_array($dayOfWeek, array_map('intval', $workingDays), true);
            DB::table('academic_weekly_schedules')->insert([
                'day_of_week' => $dayOfWeek,
                'is_school_open' => $open,
                'is_teaching_day' => $open,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Migration additive volontairement irréversible : le calendrier
        // hebdomadaire devient la référence institutionnelle.
    }
};
