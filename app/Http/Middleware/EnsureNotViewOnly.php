<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks writes for accounts the admin marked view-only.
 *
 * Keying off the HTTP method rather than a route list means any route added
 * later is covered automatically: reads (GET/HEAD) pass through, anything that
 * changes state does not. Hiding the buttons in Blade is presentation only —
 * this is what actually stops a hand-crafted POST.
 */
class EnsureNotViewOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        $isWrite = ! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true);

        if ($isWrite && $request->user() && ! $request->user()->canModify()) {
            abort(403, 'Your account has view-only access.');
        }

        return $next($request);
    }
}
