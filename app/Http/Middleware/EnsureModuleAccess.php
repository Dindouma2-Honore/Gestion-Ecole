<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Filament\Support\ModuleAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $action = $request->route()?->getActionName() ?? '';

        abort_unless(ModuleAccess::canAccessComponent($request->user(), $action), 403);

        return $next($request);
    }
}
