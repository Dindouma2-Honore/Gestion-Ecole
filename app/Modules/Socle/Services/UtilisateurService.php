<?php

declare(strict_types=1);

namespace App\Modules\Socle\Services;

use App\Models\User;
use App\Modules\Socle\Contracts\UtilisateurServiceInterface;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Implémentation de référence. Suppose que le modèle User (celui livré
 * par défaut par Laravel, dans app/Models/User.php) utilise le trait
 * Spatie\Permission\Traits\HasRoles — voir composer.json (spatie/laravel-permission).
 */
class UtilisateurService implements UtilisateurServiceInterface
{
    public function existe(int $utilisateurId): bool
    {
        return User::query()->whereKey($utilisateurId)->exists();
    }

    public function roles(int $utilisateurId): array
    {
        $user = User::query()->findOrFail($utilisateurId);

        return $user->getRoleNames()->all();
    }

    public function possedePermission(int $utilisateurId, string $permission): bool
    {
        $user = User::query()->findOrFail($utilisateurId);

        return $user->can($permission);
    }

    public function creerCompte(array $donnees): int
    {
        $email = mb_strtolower(trim((string) ($donnees['email'] ?? '')));

        if ($email === '') {
            throw new \DomainException('Veuillez renseigner une adresse e-mail valide avant d’activer l’accès système.');
        }

        if (User::query()->where('email', $email)->exists()) {
            throw new \DomainException('Un compte utilisateur utilise déjà cette adresse e-mail.');
        }

        $password = $donnees['password'] ?? Str::password(12);
        $nom = $donnees['nom'] ?? null;
        $prenom = $donnees['prenom'] ?? null;
        $name = $donnees['name'] ?? trim(($nom ?? '').' '.($prenom ?? ''));

        $user = User::query()->create([
            'name' => $name,
            'nom' => $nom,
            'prenom' => $prenom,
            'email' => $email,
            'telephone' => $donnees['telephone'] ?? null,
            'password' => $password,
            'niveau_id' => $donnees['niveau_id'] ?? null,
            'statut' => $donnees['statut'] ?? 'actif',
            'must_change_password' => $donnees['must_change_password'] ?? true,
        ]);

        return (int) $user->id;
    }

    public function reactiverCompte(int $utilisateurId, ?string $email = null): void
    {
        $user = User::query()->findOrFail($utilisateurId);

        $payload = ['statut' => 'actif'];

        if ($email !== null) {
            $cleanedEmail = mb_strtolower(trim($email));
            if ($cleanedEmail === '') {
                throw new \DomainException('Veuillez renseigner une adresse e-mail valide avant d’activer l’accès système.');
            }

            if (User::query()->where('email', $cleanedEmail)->whereKeyNot($user->id)->exists()) {
                throw new \DomainException('Cette adresse e-mail est déjà utilisée par un autre compte utilisateur.');
            }

            $payload['email'] = $cleanedEmail;
        }

        $user->update($payload);
    }

    public function mettreAJourIdentite(int $utilisateurId, array $donnees): void
    {
        $user = User::query()->findOrFail($utilisateurId);

        $payload = [];

        if (array_key_exists('email', $donnees) && $donnees['email'] !== null) {
            $email = mb_strtolower(trim((string) $donnees['email']));
            if ($email === '') {
                throw new \DomainException('Veuillez renseigner une adresse e-mail valide.');
            }

            if (User::query()->where('email', $email)->whereKeyNot($user->id)->exists()) {
                throw new \DomainException('Cette adresse e-mail est déjà utilisée par un autre compte utilisateur.');
            }

            $payload['email'] = $email;
        }

        if (array_key_exists('nom', $donnees)) {
            $payload['nom'] = $donnees['nom'];
        }

        if (array_key_exists('prenom', $donnees)) {
            $payload['prenom'] = $donnees['prenom'];
        }

        if (array_key_exists('name', $donnees)) {
            $payload['name'] = $donnees['name'];
        } elseif (array_key_exists('nom', $donnees) || array_key_exists('prenom', $donnees)) {
            $nom = $donnees['nom'] ?? $user->nom;
            $prenom = $donnees['prenom'] ?? $user->prenom;
            $payload['name'] = trim(($nom ?? '').' '.($prenom ?? ''));
        }

        if (array_key_exists('telephone', $donnees)) {
            $payload['telephone'] = $donnees['telephone'];
        }

        if (array_key_exists('niveau_id', $donnees)) {
            $payload['niveau_id'] = $donnees['niveau_id'];
        }

        if (array_key_exists('statut', $donnees) && $donnees['statut'] !== null) {
            $payload['statut'] = $donnees['statut'];
        }

        if (! empty($payload)) {
            $user->update($payload);
        }
    }

    public function synchroniserRoles(int $utilisateurId, string|int|array $roles): void
    {
        $user = User::query()->findOrFail($utilisateurId);

        $rolesList = is_array($roles) ? $roles : [$roles];
        if (empty($rolesList)) {
            $user->syncRoles([]);

            return;
        }

        $resolvedRoles = [];
        foreach ($rolesList as $r) {
            if (is_int($r) || (is_string($r) && ctype_digit((string) $r))) {
                $roleModel = Role::query()->find((int) $r);
                if (! $roleModel) {
                    throw new \DomainException("Le rôle applicatif #{$r} est introuvable.");
                }
                $resolvedRoles[] = $roleModel->name;
            } elseif (is_string($r) && trim($r) !== '') {
                $roleName = trim($r);
                $roleModel = Role::query()->where('name', $roleName)->first();
                if (! $roleModel) {
                    throw new \DomainException("Le rôle applicatif '{$roleName}' est introuvable.");
                }
                $resolvedRoles[] = $roleModel->name;
            } else {
                throw new \InvalidArgumentException('Identifiant ou nom de rôle invalide.');
            }
        }

        $user->syncRoles(array_values(array_unique($resolvedRoles)));
    }
}
