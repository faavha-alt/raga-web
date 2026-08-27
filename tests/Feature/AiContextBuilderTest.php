<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Ai\AiContextBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AiContextBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_build_for_caches_the_context_per_user(): void
    {
        $user = User::factory()->create();

        $builder = app(AiContextBuilder::class);

        $first = $builder->buildFor($user);
        $second = $builder->buildFor($user);

        $this->assertSame($first, $second);
        $this->assertTrue(Cache::has('ai-context:user:'.$user->id));
    }

    public function test_build_for_isolates_cache_between_users(): void
    {
        $userA = User::factory()->create(['name' => 'Alice']);
        $userB = User::factory()->create(['name' => 'Bob']);

        $builder = app(AiContextBuilder::class);

        $this->assertSame('Alice', $builder->buildFor($userA)['user_name']);
        $this->assertSame('Bob', $builder->buildFor($userB)['user_name']);
    }
}
