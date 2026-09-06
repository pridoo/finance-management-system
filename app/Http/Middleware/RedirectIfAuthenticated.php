<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                // Not RouteServiceProvider::HOME — that points at /home, which
                // this application has no route for, so an already-signed-in
                // user hitting /login (or / , which redirects there) landed on
                // a 404. Each user has their own landing page: the admin has no
                // MSWDO modules, and a restricted user may not hold the
                // dashboard.
                return redirect(Auth::guard($guard)->user()->homeRoute());
            }
        }

        return $next($request);
    }
}
