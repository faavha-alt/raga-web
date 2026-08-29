<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates /mcp requests. Two ways in:
 *
 *  1. A Sanctum personal access token as a static `Authorization: Bearer`
 *     header — for clients that can't run the browser OAuth flow (a CLI, a
 *     script). Generate one at Settings > API Tokens.
 *  2. A Passport (OAuth) access token from the full authorization-code flow.
 *
 * On failure it replies with the WWW-Authenticate header MCP clients rely on
 * (RFC 9728) to discover where to send the user to log in — rather than the
 * framework's default bare 401.
 */
class EnsureMcpAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        // Try the static personal access token first: it's unambiguous and
        // cheap, and a Passport JWT simply won't resolve here (no match in
        // personal_access_tokens), so OAuth clients fall through untouched.
        $user = Auth::guard('sanctum')->user();

        if ($user) {
            Auth::shouldUse('sanctum');
            $request->setUserResolver(fn () => $user);
        } else {
            Auth::shouldUse('api');

            try {
                $user = $request->user();
            } catch (\Throwable $e) {
                // A malformed bearer token, or OAuth keys not present — answer
                // 401 rather than 500. report() logs it without rethrowing.
                report($e);
                $user = null;
            }
        }

        if (! $user) {
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
