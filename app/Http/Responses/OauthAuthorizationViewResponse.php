<?php

namespace App\Http\Responses;

use Laravel\Passport\Contracts\AuthorizationViewResponse;

class OauthAuthorizationViewResponse implements AuthorizationViewResponse
{
    /** @var array<string, mixed> */
    private array $parameters = [];

    public function withParameters(array $parameters = []): static
    {
        $this->parameters = $parameters;

        return $this;
    }

    public function toResponse($request)
    {
        return response()->view('oauth.authorize', $this->parameters);
    }
}
