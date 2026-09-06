<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces the per-user module permissions the admin assigns. Applied as
 * `module:budget` on a route group, it blocks users whose permission list
 * does not include that module.
 */
class EnsureModuleAccess
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $user = $request->user();

        // The admin has no MSWDO modules at all, so send them somewhere they
        // can actually use rather than showing a bare 403.
        if ($user?->isAdmin()) {
            return redirect()->route('admin.users.index');
        }

        // The dashboard is the fallback landing page, so it must never redirect
        // or a user with no permissions at all would loop. It renders an
        // empty-state instead.
        abort_unless(
            $module === 'dashboard' || $user?->canAccess($module),
            403,
            'You do not have access to this section.'
        );

        return $next($request);
    }
}
