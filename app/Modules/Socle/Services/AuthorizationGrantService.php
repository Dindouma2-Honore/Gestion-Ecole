<?php

declare(strict_types=1);

namespace App\Modules\Socle\Services;

use App\Models\User;
use App\Modules\RH\Contracts\PosteReadContract;
use App\Modules\Socle\Contracts\AuthorizationContract;
use App\Modules\Socle\Data\AuthorizationGrantData;
use App\Modules\Socle\Data\AuthorizationSubjectData;
use App\Modules\Socle\Enums\AuthorizationAction;
use App\Modules\Socle\Enums\AuthorizationSubjectType;
use App\Modules\Socle\Models\AuthorizationGrant;
use App\Modules\Socle\Models\Fonctionnalite;
use App\Modules\Socle\Models\Groupe;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Couche applicative d'administration des authorization_grants (Authorization V1).
 *
 * L'octroi est délégué à AuthorizationContract::grant(). Seules les opérations
 * que le contrat ne fournit pas (retrait d'une action, activation/désactivation
 * d'un grant existant, lecture de la liste) sont gérées ici. Aucune table legacy
 * d'habilitation ni aucune table Spatie n'est touchée.
 */
final class AuthorizationGrantService
{
    public function __construct(
        private readonly AuthorizationContract $authorization,
        private readonly PosteReadContract $postes,
    ) {}

    /** @return array<string, string> Types de sujets supportés par Authorization V1 (ROLE exclu) */
    public function typesSujets(): array
    {
        return [
            AuthorizationSubjectType::USER->value => 'Utilisateur',
            AuthorizationSubjectType::GROUP->value => 'Groupe',
            AuthorizationSubjectType::RH_POSITION->value => 'Poste RH',
        ];
    }

    /** @return array<int|string, string> */
    public function optionsSujets(string $type): array
    {
        return match ($type) {
            'user' => User::query()->where('statut', 'actif')->orderBy('name')->pluck('name', 'id')->all(),
            'group' => Groupe::query()->orderBy('nom')->pluck('nom', 'id')->all(),
            'rh_position' => $this->postes->readAll()
                ->mapWithKeys(fn (object $poste): array => [(int) $poste->id => (string) $poste->nom])
                ->sortBy(fn (string $label): string => $label)
                ->all(),
            default => throw ValidationException::withMessages([
                'type_sujet' => 'Type de sujet non supporté (seuls user, group et rh_position existent).',
            ]),
        };
    }

    /** @return array<string, string> Fonctionnalités actives du catalogue (aucun code libre) */
    public function optionsFonctionnalites(): array
    {
        return Fonctionnalite::query()
            ->where('actif', true)
            ->orderBy('code')
            ->pluck('nom', 'code')
            ->all();
    }

    /**
     * Crée ou met à jour un grant pour un sujet et une fonctionnalité du catalogue.
     *
     * @param  list<string>  $actions  Valeurs de AuthorizationAction ('read','write','execute','print')
     *
     * @throws ValidationException
     */
    public function enregistrer(string $type, int $sujetId, string $featureCode, array $actions): AuthorizationGrantData
    {
        $subjectType = AuthorizationSubjectType::tryFrom($type);
        if ($subjectType === null) {
            throw ValidationException::withMessages([
                'type_sujet' => 'Type de sujet non supporté (seuls user, group et rh_position existent).',
            ]);
        }

        $feature = Fonctionnalite::query()
            ->where('code', $featureCode)
            ->where('actif', true)
            ->first();
        if ($feature === null) {
            throw ValidationException::withMessages([
                'feature_code' => 'Fonctionnalité introuvable ou inactive dans le catalogue.',
            ]);
        }

        $this->verifierSujet($subjectType, $sujetId);

        $granted = [];
        foreach ($actions as $action) {
            $parsed = AuthorizationAction::tryFrom((string) $action);
            if ($parsed === null) {
                throw ValidationException::withMessages([
                    'actions' => "Action inconnue : {$action}.",
                ]);
            }
            $granted[$parsed->value] = $parsed;
        }

        if ($granted === []) {
            throw ValidationException::withMessages([
                'actions' => 'Au moins une action est requise.',
            ]);
        }

        $data = $this->authorization->grant(
            new AuthorizationSubjectData($subjectType, $sujetId),
            $feature->code,
            ...array_values($granted),
        );

        // AuthorizationContract::grant() est additif (il ne retire jamais une action) :
        // on aligne ensuite les actions explicitement non cochées sur false.
        $record = AuthorizationGrant::query()
            ->where('subject_type', $subjectType->value)
            ->where('subject_id', $sujetId)
            ->where('feature_id', $feature->id)
            ->firstOrFail();

        foreach (AuthorizationAction::cases() as $action) {
            $record->{AuthorizationGrant::columnFor($action)} = isset($granted[$action->value]);
        }
        $record->is_active = true;
        $record->save();

        return $data;
    }

    /** Désactive un grant sans supprimer sa ligne (conservation de l'historique). */
    public function desactiver(int $grantId): void
    {
        $grant = AuthorizationGrant::query()->findOrFail($grantId);
        $grant->is_active = false;
        $grant->save();
    }

    /** Réactive un grant préalablement désactivé. */
    public function reactiver(int $grantId): void
    {
        $grant = AuthorizationGrant::query()->findOrFail($grantId);
        $grant->is_active = true;
        $grant->save();
    }

    /** @return Collection<int, AuthorizationGrant> */
    public function lister(): Collection
    {
        return AuthorizationGrant::query()->with('feature')->orderByDesc('id')->get();
    }

    public function trouver(int $grantId): AuthorizationGrant
    {
        return AuthorizationGrant::query()->with('feature')->findOrFail($grantId);
    }

    private function verifierSujet(AuthorizationSubjectType $type, int $sujetId): void
    {
        $existe = match ($type) {
            AuthorizationSubjectType::USER => User::query()->whereKey($sujetId)->exists(),
            AuthorizationSubjectType::GROUP => Groupe::query()->whereKey($sujetId)->exists(),
            AuthorizationSubjectType::RH_POSITION => $this->postes->readAll()
                ->contains(fn (object $poste): bool => (int) $poste->id === $sujetId),
        };

        if (! $existe) {
            throw ValidationException::withMessages([
                'sujet_id' => "Sujet introuvable ({$type->value} #{$sujetId}).",
            ]);
        }
    }
}
