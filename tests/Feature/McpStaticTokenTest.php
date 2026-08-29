<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class McpStaticTokenTest extends TestCase
{
    use RefreshDatabase;

    private function rpc(array $overrides = []): array
    {
        return array_merge(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'], $overrides);
    }

    public function test_mcp_endpoint_accepts_a_static_personal_access_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('cli')->plainTextToken;

        $response = $this->withToken($token)->postJson('/mcp', $this->rpc());

        $response->assertOk()
            ->assertJsonPath('result.tools.0.name', 'raga_overview');

        $names = collect($response->json('result.tools'))->pluck('name');
        $this->assertTrue($names->contains('raga_sync_garmin'));
    }

    public function test_mcp_endpoint_rejects_a_request_with_no_token(): void
    {
        $this->postJson('/mcp', $this->rpc())
            ->assertUnauthorized()
            ->assertHeader('WWW-Authenticate')
            ->assertJsonPath('error', 'invalid_token');
    }

    public function test_mcp_endpoint_rejects_a_bogus_token(): void
    {
        $this->withToken('not-a-real-token')
            ->postJson('/mcp', $this->rpc())
            ->assertUnauthorized()
            ->assertJsonPath('error', 'invalid_token');
    }

    public function test_mcp_endpoint_rejects_a_revoked_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('cli')->plainTextToken;
        $user->tokens()->delete();

        $this->withToken($token)->postJson('/mcp', $this->rpc())->assertUnauthorized();
    }

    public function test_static_token_call_resolves_the_owning_user(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('cli')->plainTextToken;

        $response = $this->withToken($token)->postJson('/mcp', $this->rpc([
            'method' => 'tools/call',
            'params' => ['name' => 'raga_overview', 'arguments' => []],
        ]));

        $response->assertOk();
        $this->assertStringContainsString($user->email, $response->json('result.content.0.text'));
    }
}
