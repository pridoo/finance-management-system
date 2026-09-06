<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the user-management area.
 *
 * The admin and the MSWDO officer both reach it — the officer issues and
 * withdraws staff logins as part of running the office. The two are not
 * equivalent inside: the officer may not touch administrator accounts or hand
 * out the admin role, which User::canManage() and User::canAssignRole()
 * enforce per action in the controllers.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            $request->user()?->canAdministerAccounts(),
            403,
            'Account administration is limited to administrators and MSWDO officers.'
        );

        return $next($request);
    }
}
