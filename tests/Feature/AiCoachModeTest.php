<?php

namespace Tests\Feature;

use App\Models\AiConversation;
use App\Models\User;
use App\Services\Ai\AiCoachService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiCoachModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_page_shows_mode_selector(): void
    {
        $user = User::factory()->create();
        $user->aiSetting()->create(['provider' => 'gemini', 'api_key' => 'AIza-test', 'mode' => 'standard']);

        $this->actingAs($user)->get('/settings/ai')
            ->assertOk()
            ->assertSee('Mode Analisis')
            ->assertSee('Standard')
            ->assertSee('Pro');
    }

    public function test_settings_update_persists_pro_mode(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/settings/ai', [
            'provider' => 'gemini',
            'api_key' => 'AIza-test',
            'model' => '',
            'mode' => 'pro',
        ])->assertRedirect(route('settings.ai.show'));

        $this->assertDatabaseHas('ai_settings', ['user_id' => $user->id, 'provider' => 'gemini', 'mode' => 'pro']);
        $this->assertSame('pro', $user->aiSetting()->first()->mode);
    }

    public function test_pro_mode_uses_pro_model_and_larger_token_budget_on_gemini(): void
    {
        $user = User::factory()->create();
        $user->aiSetting()->create(['provider' => 'gemini', 'api_key' => 'AIza-test', 'mode' => 'pro', 'model' => null]);
        $conversation = $user->aiConversations()->create(['title' => 'test']);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'Pro response']]]]],
            ], 200),
        ]);

        $reply = app(AiCoachService::class)->reply($user, $conversation, 'Analyze my recovery');

        $this->assertSame('Pro response', $reply);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'gemini-2.5-pro')
                && ($request['generationConfig']['maxOutputTokens'] ?? null) === 8192
                && str_contains($request['system_instruction']['parts']['text'], 'RAGA Pro Coach');
        });
    }

    public function test_standard_mode_uses_flash_model_and_default_token_budget(): void
    {
        $user = User::factory()->create();
        $user->aiSetting()->create(['provider' => 'gemini', 'api_key' => 'AIza-test', 'mode' => 'standard', 'model' => null]);
        $conversation = $user->aiConversations()->create(['title' => 'test']);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'Standard response']]]]],
            ], 200),
        ]);

        $reply = app(AiCoachService::class)->reply($user, $conversation, 'Hello');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'gemini-2.5-flash')
                && ($request['generationConfig']['maxOutputTokens'] ?? null) === 4096
                && str_contains($request['system_instruction']['parts']['text'], 'RAGA Coach')
                && ! str_contains($request['system_instruction']['parts']['text'], 'RAGA Pro Coach');
        });
    }
}
