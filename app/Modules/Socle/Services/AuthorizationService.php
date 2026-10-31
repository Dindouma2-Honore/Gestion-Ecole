<?php

declare(strict_types=1);

namespace App\Modules\Socle\Services;

use App\Models\User;
use App\Modules\Socle\Contracts\AuthorizationContract;
use App\Modules\Socle\Contracts\AuthorizationPositionResolverContract;
use App\Modules\Socle\Data\AuthorizationDecisionData;
use App\Modules\Socle\Data\AuthorizationGrantData;
use App\Modules\Socle\Data\AuthorizationSubjectData;
use App\Modules\Socle\Enums\AuthorizationAction;
use App\Modules\Socle\Enums\AuthorizationSubjectType;
use App\Modules\Socle\Models\AuthorizationGrant;
use App\Modules\Socle\Models\Fonctionnalite;
use Illuminate\Support\Facades\DB;

final class AuthorizationService implements AuthorizationContract
{
    public function __construct(private readonly AuthorizationPositionResolverContract $positions) {}

    public function decide(int $userId, string $featureCode, AuthorizationAction $action): AuthorizationDecisionData
    {
        $feature = Fonctionnalite::query()
            ->where('code', $featureCode)
            ->where('actif', true)
            ->first();

        if ($feature === null) {
            return new AuthorizationDecisionData(false, $featureCode, $action);
        }

        $subjects = $this->subjectsForUser($userId);
        $column = AuthorizationGrant::columnFor($action);
        $grantedBy = AuthorizationGrant::query()
            ->where('feature_id', $feature->id)
            ->where('is_active', true)
            ->where($column, true)
            ->where(function ($query) use ($subjects): void {
                foreach ($subjects as $subject) {
                    $query->orWhere(function ($subjectQuery) use ($subject): void {
                        $subjectQuery
                            ->where('subject_type', $subject->type->value)
                            ->where('subject_id', $subject->id);
                    });
                }
            })
            ->get(['subject_type', 'subject_id'])
            ->map(fn (AuthorizationGrant $grant): AuthorizationSubjectData => new AuthorizationSubjectData(
                $grant->subject_type,
                $grant->subject_id,
            ))
            ->all();

        return new AuthorizationDecisionData($grantedBy !== [], $featureCode, $action, $grantedBy);
    }

    public function canRead(int $userId, string $featureCode): bool
    {
        return $this->decide($userId, $featureCode, AuthorizationAction::READ)->allowed;
    }

    public function canWrite(int $userId, string $featureCode): bool
    {
        return $this->decide($userId, $featureCode, AuthorizationAction::WRITE)->allowed;
    }

    public function canExecute(int $userId, string $featureCode): bool
    {
        return $this->decide($userId, $featureCode, AuthorizationAction::EXECUTE)->allowed;
    }

    public function canPrint(int $userId, string $featureCode): bool
    {
        return $this->decide($userId, $featureCode, AuthorizationAction::PRINT)->allowed;
    }

    public function grant(AuthorizationSubjectData $subject, string $featureCode, AuthorizationAction ...$actions): AuthorizationGrantData
    {
        $feature = Fonctionnalite::query()->where('code', $featureCode)->firstOrFail();
        $attributes = array_fill_keys(['can_read', 'can_write', 'can_execute', 'can_print'], false);
        foreach ($actions as $action) {
            $attributes[AuthorizationGrant::columnFor($action)] = true;
        }

        $grant = DB::transaction(function () use ($subject, $feature, $attributes): AuthorizationGrant {
            $grant = AuthorizationGrant::query()->firstOrCreate([
                'subject_type' => $subject->type->value,
                'subject_id' => $subject->id,
                'feature_id' => $feature->id,
            ]);

            foreach ($attributes as $column => $enabled) {
                if ($enabled) {
                    $grant->{$column} = true;
                }
            }
            $grant->is_active = true;
            $grant->save();

            return $grant;
        });

        return new AuthorizationGrantData($subject, $feature->code, $grant->grantedActions());
    }

    /** @return list<AuthorizationGrantData> */
    public function grantsFor(AuthorizationSubjectData $subject): array
    {
        return AuthorizationGrant::query()
            ->with('feature:id,code')
            ->where('subject_type', $subject->type->value)
            ->where('subject_id', $subject->id)
            ->where('is_active', true)
            ->get()
            ->map(fn (AuthorizationGrant $grant): AuthorizationGrantData => new AuthorizationGrantData(
                $subject,
                $grant->feature->code,
                $grant->grantedActions(),
            ))
            ->all();
    }

    /** @return list<AuthorizationSubjectData> */
    private function subjectsForUser(int $userId): array
    {
        $groupIds = User::query()
            ->whereKey($userId)
            ->first()?->groupes()
            ->pluck('groupes.id')
            ->map(fn (int|string $id): int => (int) $id)
            ->all() ?? [];
        $positionIds = array_values(array_unique(array_map('intval', $this->positions->positionIdsForUser($userId))));

        return [
            new AuthorizationSubjectData(AuthorizationSubjectType::USER, $userId),
            ...array_map(fn (int $id): AuthorizationSubjectData => new AuthorizationSubjectData(AuthorizationSubjectType::GROUP, $id), $groupIds),
            ...array_map(fn (int $id): AuthorizationSubjectData => new AuthorizationSubjectData(AuthorizationSubjectType::RH_POSITION, $id), $positionIds),
        ];
    }
}
