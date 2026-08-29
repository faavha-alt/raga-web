<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTokenSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_requires_authentication(): void
    {
        $this->get('/settings/api-tokens')->assertRedirect('/login');
    }

    public function test_user_can_create_a_token_and_see_the_plain_text_once(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/settings/api-tokens', ['name' => 'claude-desktop']);

        $response->assertRedirect();
        $response->assertSessionHas('plain_text_token');
        $this->assertSame(1, $user->tokens()->where('name', 'claude-desktop')->count());
    }

    public function test_token_name_is_required(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/settings/api-tokens', [])->assertSessionHasErrors('name');
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_user_can_revoke_their_own_token(): void
    {
        $user = User::factory()->create();
        $id = $user->createToken('cli')->accessToken->id;

        $this->actingAs($user)->delete("/settings/api-tokens/{$id}")->assertRedirect();

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_user_cannot_revoke_another_users_token(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $id = $owner->createToken('cli')->accessToken->id;

        $this->actingAs($other)->delete("/settings/api-tokens/{$id}")->assertRedirect();

        $this->assertSame(1, $owner->tokens()->count());
    }
}
