<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates /mcp requests against the Passport ("api") guard and, on
 * failure, replies with the WWW-Authenticate header MCP clients rely on
 * (RFC 9728) to discover where to send the user to log in — rather than
 * the framework's default bare 401.
 */
class EnsureMcpAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        Auth::shouldUse('api');

        if (! $request->user()) {
            return response()->json([
                'error' => 'invalid_token',
                'error_description' => 'Unauthenticated.',
            ], 401)->header(
                'WWW-Authenticate',
                'Bearer resource_metadata="'.url('/.well-known/oauth-protected-resource').'"'
            );
        }

        return $next($request);
    }
}
