<?php

namespace App\Http\Controllers\Mcp;

use App\Http\Controllers\Api\McpController;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * The remote MCP endpoint (Streamable HTTP transport, JSON-RPC 2.0 over a
 * single POST route). Every tool call is delegated to the same
 * App\Http\Controllers\Api\McpController methods the token-authenticated
 * REST API already uses — this controller is just the JSON-RPC envelope
 * and the tool registry around them, resolved for whichever user the
 * OAuth access token (see EnsureMcpAuthenticated) identifies.
 */
class McpTransportController extends Controller
{
    private const PROTOCOL_VERSION = '2025-06-18';

    /** @var array<string, array{method: string, description: string}> */
    private const TOOLS = [
        'raga_overview' => [
            'method' => 'overview',
            'description' => "Today's snapshot for the current RAGA user: readiness, sleep, HRV, resting HR, body battery, stress, training load, plus auto-generated insights comparing this week to last. Good starting point for any plan-related question.",
        ],
        'raga_training' => [
            'method' => 'training',
            'description' => "Training load status (acute/chronic load, acute:chronic ratio, monotony, risk level) with 90-day history, this week's volume totals, 30-day consistency, top activity types, active training plans, recent workouts, and personal records.",
        ],
        'raga_recovery' => [
            'method' => 'recovery',
            'description' => 'Current recovery score and readiness score with their factor breakdowns (sleep, HRV, resting HR, stress, training load, body battery, recent activity), plus 30-day history of both. Use this to judge whether today should be a hard, easy, or rest day.',
        ],
        'raga_health' => [
            'method' => 'health',
            'description' => "Latest health vitals (resting/avg/max HR, HRV, stress, body battery, respiration, SpO2, steps, calories, recovery time, Garmin readiness) each compared against the user's personal baseline.",
        ],
        'raga_running' => [
            'method' => 'running',
            'description' => "Running-specific performance: this week's totals, calculated performance rating, 30-day consistency, latest VO2max, longest recent runs, and personal records.",
        ],
        'raga_trail' => [
            'method' => 'trail',
            'description' => '90-day trail running summary: distance/elevation totals, moving time, count of repeated routes, and the most recent trail runs.',
        ],
        'raga_full_context' => [
            'method' => null,
            'description' => 'Everything above (overview, training, recovery, health, running, trail) in one call. Use this when building or revising a full training/recovery plan, so you have the complete picture instead of guessing which slice you need.',
        ],
        'raga_save_training_plan' => [
            'method' => 'saveTrainingPlan',
            'readable' => false,
            'description' => "Save a structured multi-day training plan into RAGA so it shows up on the user's Training Calendar. Organize it as weeks, each with days, each with zero or more planned workouts. Call this once you and the user have agreed on a plan — don't call it speculatively.",
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string', 'description' => 'Short name for the plan, e.g. "Half Marathon Base Build".'],
                    'start_date' => ['type' => 'string', 'format' => 'date'],
                    'target_date' => ['type' => 'string', 'format' => 'date'],
                    'weeks' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'week_number' => ['type' => 'integer', 'minimum' => 1],
                                'start_date' => ['type' => 'string', 'format' => 'date'],
                                'end_date' => ['type' => 'string', 'format' => 'date'],
                                'days' => [
                                    'type' => 'array',
                                    'items' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'date' => ['type' => 'string', 'format' => 'date'],
                                            'workouts' => [
                                                'type' => 'array',
                                                'description' => 'Leave empty for a rest day.',
                                                'items' => [
                                                    'type' => 'object',
                                                    'properties' => [
                                                        'type' => ['type' => 'string', 'description' => 'e.g. running, trail_running, cycling, strength'],
                                                        'duration_minutes' => ['type' => 'number'],
                                                        'distance_meters' => ['type' => 'number'],
                                                        'target_pace_seconds_per_km' => ['type' => 'number'],
                                                        'target_heart_rate_zone' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 5],
                                                        'intensity' => ['type' => 'string', 'description' => 'e.g. easy, moderate, hard'],
                                                        'warm_up' => ['type' => 'string'],
                                                        'main_set' => ['type' => 'string'],
                                                        'cool_down' => ['type' => 'string'],
                                                        'notes' => ['type' => 'string'],
                                                    ],
                                                    'required' => ['type'],
                                                ],
                                            ],
                                        ],
                                        'required' => ['date'],
                                    ],
                                ],
                            ],
                            'required' => ['week_number', 'start_date', 'end_date', 'days'],
                        ],
                    ],
                ],
                'required' => ['name', 'start_date', 'target_date', 'weeks'],
            ],
        ],
        'raga_save_recommendation' => [
            'method' => 'saveRecommendation',
            'readable' => false,
            'description' => 'Save a short, dated recommendation into RAGA — shows up on the user\'s Dashboard. Use for a single piece of advice (e.g. "take it easy today"), not a multi-day plan (use raga_save_training_plan for that).',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'date' => ['type' => 'string', 'format' => 'date', 'description' => 'Date this recommendation applies to.'],
                    'category' => ['type' => 'string', 'description' => 'Short category label, e.g. "recovery", "training", "health".'],
                    'title' => ['type' => 'string'],
                    'message' => ['type' => 'string'],
                    'priority' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 10, 'description' => 'Higher = more important. Optional, defaults to 0.'],
                ],
                'required' => ['date', 'category', 'title', 'message'],
            ],
        ],
    ];

    public function handle(Request $request, McpController $mcp)
    {
        $payload = json_decode($request->getContent(), true);

        if (! is_array($payload) || ($payload['jsonrpc'] ?? null) !== '2.0' || ! isset($payload['method'])) {
            return response()->json([
                'jsonrpc' => '2.0',
                'id' => $payload['id'] ?? null,
                'error' => ['code' => -32600, 'message' => 'Invalid Request'],
            ], 200);
        }

        $id = $payload['id'] ?? null;
        $isNotification = ! array_key_exists('id', $payload);
        $params = $payload['params'] ?? [];

        try {
            $result = match ($payload['method']) {
                'initialize' => $this->initialize($params),
                'notifications/initialized', 'notifications/cancelled' => null,
                'ping' => (object) [],
                'tools/list' => $this->toolsList(),
                'tools/call' => $this->toolsCall($request, $mcp, $params),
                default => throw new \RuntimeException('Method not found', -32601),
            };
        } catch (\RuntimeException $e) {
            return $isNotification ? response('', 202) : response()->json([
                'jsonrpc' => '2.0',
                'id' => $id,
                'error' => ['code' => $e->getCode() ?: -32603, 'message' => $e->getMessage()],
            ]);
        }

        if ($isNotification) {
            return response('', 202);
        }

        return response()->json(['jsonrpc' => '2.0', 'id' => $id, 'result' => $result]);
    }

    private function initialize(array $params): array
    {
        return [
            'protocolVersion' => $params['protocolVersion'] ?? self::PROTOCOL_VERSION,
            'capabilities' => ['tools' => (object) []],
            'serverInfo' => ['name' => 'raga-web', 'version' => '1.0.0'],
        ];
    }

    private function toolsList(): array
    {
        $tools = [];

        foreach (self::TOOLS as $name => $tool) {
            $tools[] = [
                'name' => $name,
                'description' => $tool['description'],
                'inputSchema' => $tool['inputSchema'] ?? ['type' => 'object', 'properties' => (object) []],
            ];
        }

        return ['tools' => $tools];
    }

    private function toolsCall(Request $request, McpController $mcp, array $params): array
    {
        $name = $params['name'] ?? null;
        $tool = self::TOOLS[$name] ?? null;

        if (! $tool) {
            throw new \RuntimeException("Unknown tool: {$name}", -32602);
        }

        $request->merge($params['arguments'] ?? []);

        try {
            $data = $tool['method'] === null
                ? $this->fullContext($request, $mcp)
                : app()->call([$mcp, $tool['method']], ['request' => $request]);
        } catch (ValidationException $e) {
            $errors = collect($e->errors())->map(fn ($msgs, $field) => "{$field}: ".implode(' ', $msgs))->implode("\n");

            return ['content' => [['type' => 'text', 'text' => $errors]], 'isError' => true];
        } catch (Throwable $e) {
            Log::error('MCP tool call failed', [
                'tool' => $name,
                'user_id' => $request->user()?->id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ['content' => [['type' => 'text', 'text' => 'An unexpected error occurred while running this tool. Please try again.']], 'isError' => true];
        }

        return ['content' => [['type' => 'text', 'text' => json_encode($data, JSON_PRETTY_PRINT)]]];
    }

    private function fullContext(Request $request, McpController $mcp): array
    {
        $context = [];

        foreach (self::TOOLS as $name => $tool) {
            if ($tool['method'] !== null && ($tool['readable'] ?? true)) {
                $context[$name] = app()->call([$mcp, $tool['method']], ['request' => $request]);
            }
        }

        return $context;
    }
}
