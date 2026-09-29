<?php

declare(strict_types=1);

namespace App\Modules\Socle\Http\Middleware;

use App\Modules\Socle\Exceptions\CompteInactifException;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && isset($user->statut) && $user->statut !== 'actif') {
            Auth::logout();
            throw new CompteInactifException($user->statut);
        }

        return $next($request);
    }
}
