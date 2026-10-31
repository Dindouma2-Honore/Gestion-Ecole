<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('facilg_legacy_id', 'like', 'utilisateur:%')
            ->orderBy('id')
            ->each(function (object $user): void {
                $sourceId = str((string) $user->facilg_legacy_id)->after('utilisateur:')->toString();
                $employe = DB::table('employes')->where('facilg_legacy_id', "employe:{$sourceId}")->first();

                if (! $employe) {
                    return;
                }

                $nomComplet = trim($employe->nom.' '.$employe->prenom);
                DB::table('users')->where('id', $user->id)->update([
                    'name' => $nomComplet !== '' ? $nomComplet : $user->name,
                    'nom' => $employe->nom,
                    'prenom' => $employe->prenom,
                    'telephone' => $employe->telephone,
                    'updated_at' => now(),
                ]);
                DB::table('employes')->where('id', $employe->id)->update(['user_id' => $user->id]);
            });
    }

    public function down(): void
    {
        DB::table('employes')
            ->whereIn('user_id', DB::table('users')->where('facilg_legacy_id', 'like', 'utilisateur:%')->select('id'))
            ->update(['user_id' => null]);
    }
};
