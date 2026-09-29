<?php

declare(strict_types=1);

namespace App\Modules\Socle\Services;

use App\Models\User;
use App\Modules\Socle\Contracts\AuditServiceContract;
use App\Modules\Socle\Exceptions\FondateurDejaAttribueException;
use App\Modules\Socle\Exceptions\RoleNiveauRequisException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AssignationRoleService
{
    public function __construct(private readonly AuditServiceContract $audit) {}

    private const ROLES_AUTORISES = [
        'Fondateur',
        'Directeur',
        'Comptable',
        'SurveillantGeneral',
        'ChargeLogistique',
        'Enseignant',
        'Parent',
        'Eleve',
    ];

    /**
     * Assigne un rôle à un utilisateur, avec validation des règles de scope.
     *
     * @throws RoleNiveauRequisException
     */
    public function assignerRole(User $user, string $role, int|string|null $niveauId = null): void
    {
        $niveauId = is_numeric($niveauId) ? (int) $niveauId : null;
        if (! in_array($role, self::ROLES_AUTORISES, true)) {
            throw new InvalidArgumentException("Le rôle '{$role}' n'est pas autorisé dans le Socle.");
        }

        if ($role === 'Fondateur' && User::role('Fondateur')->where('statut', 'actif')->whereKeyNot($user->id)->exists()) {
            throw new FondateurDejaAttribueException;
        }

        $rolesNecessitantNiveau = ['Directeur', 'Enseignant'];

        if (in_array($role, $rolesNecessitantNiveau, true) && is_null($niveauId)) {
            throw new RoleNiveauRequisException($role);
        }

        $user->syncRoles([$role]);

        if (in_array($role, $rolesNecessitantNiveau, true)) {
            $user->update(['niveau_id' => $niveauId]);
        } else {
            $user->update(['niveau_id' => null]);
        }
    }

    public function transfererFondateur(User $fondateurActuel, User $nouveauFondateur, string $motif): void
    {
        if (trim($motif) === '') {
            throw new InvalidArgumentException('Le motif du transfert du rôle Fondateur est obligatoire.');
        }

        if (! $fondateurActuel->hasRole('Fondateur')) {
            throw new InvalidArgumentException("L’utilisateur cédant n’est pas le Fondateur actuel.");
        }

        DB::transaction(function () use ($fondateurActuel, $nouveauFondateur, $motif): void {
            $fondateurActuel->removeRole('Fondateur');
            $nouveauFondateur->syncRoles(['Fondateur']);
            $nouveauFondateur->update(['niveau_id' => null]);

            $this->audit->enregistrerAvecMotif(
                $nouveauFondateur,
                "Transfert du rôle Fondateur de {$fondateurActuel->email} vers {$nouveauFondateur->email}",
                trim($motif),
            );
        });
    }
}
