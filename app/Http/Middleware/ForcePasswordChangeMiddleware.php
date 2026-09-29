<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForcePasswordChangeMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->must_change_password) {
            // Allow access to password change page and logout route
            if (! $request->routeIs('filament.admin.pages.changer-mot-de-passe') &&
                ! $request->routeIs('filament.admin.auth.logout')) {
                return redirect()->route('filament.admin.pages.changer-mot-de-passe');
            }
        }

        return $next($request);
    }
}
