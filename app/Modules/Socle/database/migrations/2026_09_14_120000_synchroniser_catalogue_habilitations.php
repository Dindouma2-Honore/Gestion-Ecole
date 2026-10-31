<?php

use App\Filament\Support\HabilitationCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (HabilitationCatalog::features() as $feature) {
            $existing = DB::table('catalogue_fonctionnalites')->where('code', $feature['code'])->first();
            if ($existing) {
                unset($feature['actif']); // Preserve administrative deactivations.
                DB::table('catalogue_fonctionnalites')->where('id', $existing->id)->update([...$feature, 'updated_at' => now()]);
            } else {
                DB::table('catalogue_fonctionnalites')->insert([...$feature, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        // Preserve catalogue identifiers and their role/user/group assignments.
    }
};
