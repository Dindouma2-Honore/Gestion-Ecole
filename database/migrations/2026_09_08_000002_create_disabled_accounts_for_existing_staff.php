<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('employes')
            ->whereNull('user_id')
            ->orderBy('id')
            ->each(function (object $employee): void {
                $email = filter_var($employee->email, FILTER_VALIDATE_EMAIL) ? strtolower($employee->email) : null;
                if ($email !== null && DB::table('users')->where('email', $email)->exists()) {
                    $email = null;
                }
                $email ??= "personnel.{$employee->id}@ambassadors.local";

                $userId = DB::table('users')->insertGetId([
                    'name' => trim($employee->nom.' '.$employee->prenom),
                    'nom' => $employee->nom,
                    'prenom' => $employee->prenom,
                    'email' => $email,
                    'telephone' => $employee->telephone,
                    'password' => Hash::make(Str::random(48)),
                    'statut' => 'desactive',
                    'must_change_password' => true,
                    'facilg_legacy_id' => "personnel-access:{$employee->id}",
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('employes')->where('id', $employee->id)->update([
                    'user_id' => $userId,
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        $userIds = DB::table('users')
            ->where('facilg_legacy_id', 'like', 'personnel-access:%')
            ->pluck('id');

        DB::table('employes')->whereIn('user_id', $userIds)->update(['user_id' => null]);
        DB::table('users')->whereIn('id', $userIds)->delete();
    }
};
