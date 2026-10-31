<?php

namespace App\Modules\Socle\Services;

use App\Models\User;
use App\Modules\Socle\Contracts\Events\UserAccountCreated;
use App\Modules\Socle\Contracts\Events\UserAccountReactivated;
use App\Modules\Socle\Contracts\StaffPersonnelContract;
use App\Modules\Socle\Filament\Resources\UserResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StaffAccountService
{
    public function __construct(private readonly StaffPersonnelContract $personnel) {}

    public function create(array $data): User
    {
        Gate::authorize('create', User::class);

        return DB::transaction(function () use ($data): User {
            $data['email'] = mb_strtolower(trim((string) ($data['email'] ?? '')));
            $employee = $this->personnel->activeForAccount((int) ($data['employe_id'] ?? 0));
            if (! $employee) {
                throw ValidationException::withMessages(['employe_id' => 'Choisissez un membre du personnel actif sans compte.']);
            }

            $existingUser = $employee->userId ? User::query()->lockForUpdate()->find($employee->userId) : null;
            $isPlaceholder = $existingUser && (
                str_ends_with((string) $existingUser->email, '@ambassadors.local')
                || str_ends_with((string) $existingUser->email, '@facilg.import.local')
                || str_starts_with((string) $existingUser->facilg_legacy_id, 'personnel-access:')
                || $existingUser->statut === 'desactive'
            );

            if ($existingUser && ! $isPlaceholder) {
                throw ValidationException::withMessages(['employe_id' => 'Choisissez un membre du personnel actif sans compte.']);
            }

            $roleName = $data['role'] ?? $employee->role;
            $niveauId = array_key_exists('niveau_id', $data) ? $data['niveau_id'] : $employee->niveauId;

            validator(array_merge($data, array_filter(['role' => $roleName, 'niveau_id' => $niveauId])), [
                'email' => [
                    'required',
                    'email',
                    'max:255',
                    Rule::unique('users', 'email')->ignore($isPlaceholder && $existingUser ? $existingUser->id : null),
                ],
                'role' => ['nullable', Rule::in(UserResource::rolesAutorises())],
                'niveau_id' => 'nullable|exists:niveaux,id',
                'password' => 'nullable|string|min:6',
            ])->validate();
            if ($roleName === 'Fondateur' && User::role('Fondateur')->where('statut', 'actif')->exists()) {
                throw ValidationException::withMessages(['role' => 'Le rôle Fondateur est déjà attribué.']);
            }
            $hasManualPassword = filled($data['password'] ?? null);
            $password = $hasManualPassword ? (string) $data['password'] : Str::password(32);
            $mustChangePassword = ! $hasManualPassword;

            if ($isPlaceholder && $existingUser) {
                $user = $existingUser;
                $user->forceFill([
                    'name' => $employee->nomComplet(),
                    'nom' => $employee->nom,
                    'prenom' => $employee->prenom,
                    'email' => mb_strtolower(trim($data['email'])),
                    'telephone' => $employee->telephone,
                    'password' => $password,
                    'statut' => 'actif',
                    'must_change_password' => $mustChangePassword,
                    'facilg_legacy_id' => null,
                    'created_at' => now(),
                ]);
                $user->saveQuietly();
                $this->personnel->linkUser($employee->id, $user->id);
            } else {
                $user = new User([
                    'name' => $employee->nomComplet(),
                    'nom' => $employee->nom,
                    'prenom' => $employee->prenom,
                    'email' => mb_strtolower(trim($data['email'])),
                    'telephone' => $employee->telephone,
                    'password' => $password,
                    'statut' => 'actif',
                    'must_change_password' => $mustChangePassword,
                ]);
                $user->saveQuietly();
                $this->personnel->linkUser($employee->id, $user->id);
            }

            if (! empty($roleName)) {
                app(AssignationRoleService::class)->assignerRole($user, $roleName, $niveauId);
            }
            event($isPlaceholder && $existingUser
                ? new UserAccountReactivated($user->id)
                : new UserAccountCreated($user->id));

            return $user;
        });
    }
}
