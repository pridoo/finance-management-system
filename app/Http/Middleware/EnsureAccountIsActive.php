<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends the session of an account disabled while its owner was still signed in.
 *
 * Blocking at login alone is not enough: a user already holding a valid session
 * would keep working until they happened to log out, which defeats the point of
 * disabling them.
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! $request->user()->is_active) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'This account has been disabled. Please contact your administrator.',
            ]);
        }

        return $next($request);
    }
}
