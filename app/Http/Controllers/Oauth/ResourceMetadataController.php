<?php

namespace App\Http\Controllers\Oauth;

use App\Http\Controllers\Controller;

/**
 * RFC 8414 / RFC 9728 discovery documents so MCP clients (Claude) can find
 * this app's OAuth endpoints and the resource they protect without any
 * manual configuration on the user's part.
 */
class ResourceMetadataController extends Controller
{
    public function authorizationServer(): array
    {
        $base = rtrim(config('app.url'), '/');

        return [
            'issuer' => $base,
            'authorization_endpoint' => $base.'/oauth/authorize',
            'token_endpoint' => $base.'/oauth/token',
            'registration_endpoint' => $base.'/oauth/register',
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported' => ['S256'],
            'token_endpoint_auth_methods_supported' => ['none', 'client_secret_post'],
        ];
    }

    public function protectedResource(): array
    {
        $base = rtrim(config('app.url'), '/');

        return [
            'resource' => $base.'/mcp',
            'authorization_servers' => [$base],
        ];
    }
}
