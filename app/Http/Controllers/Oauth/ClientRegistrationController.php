<?php

namespace App\Http\Controllers\Oauth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Laravel\Passport\ClientRepository;

/**
 * Minimal RFC 7591 Dynamic Client Registration, so an MCP client (Claude)
 * can register itself the first time someone adds this server's URL,
 * instead of a client_id being configured by hand beforehand. Clients
 * registered here are public (no secret) — they authenticate the user via
 * the authorization-code + PKCE flow, not via a client secret.
 */
class ClientRegistrationController extends Controller
{
    public function __construct(private ClientRepository $clients) {}

    public function store(Request $request)
    {
        $data = $request->validate([
            'client_name' => ['sometimes', 'string', 'max:255'],
            'redirect_uris' => ['required', 'array', 'min:1'],
            'redirect_uris.*' => ['string', 'url'],
        ]);

        $client = $this->clients->createAuthorizationCodeGrantClient(
            name: $data['client_name'] ?? 'MCP Client',
            redirectUris: $data['redirect_uris'],
            confidential: false,
        );

        return response()->json([
            'client_id' => $client->id,
            'client_name' => $client->name,
            'redirect_uris' => $client->redirect_uris,
            'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code'],
            'token_endpoint_auth_method' => 'none',
        ], 201);
    }
}
