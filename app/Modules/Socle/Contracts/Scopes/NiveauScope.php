<?php

declare(strict_types=1);

namespace App\Modules\Socle\Contracts\Scopes;

use App\Models\User;
use App\Modules\Socle\Contracts\ScopedByNiveau;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class NiveauScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user) {
            return;
        }

        if (! $user->hasAnyRole(['Directeur', 'Enseignant'])) {
            return;
        }

        // Fail-safe : un rôle scopé mal configuré ne voit aucune donnée.
        if (is_null($user->niveau_id) || ! $model instanceof ScopedByNiveau) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $scopePath = $model->getNiveauScopeColumn();

        if (! str_contains($scopePath, '.')) {
            $builder->where($model->qualifyColumn($scopePath), $user->niveau_id);

            return;
        }

        $segments = explode('.', $scopePath);
        $column = array_pop($segments);
        $relation = implode('.', $segments);

        $builder->whereHas(
            $relation,
            fn (Builder $query): Builder => $query->where($column, $user->niveau_id)
        );
    }
}
