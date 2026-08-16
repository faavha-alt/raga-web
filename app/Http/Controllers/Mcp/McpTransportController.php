<?php

namespace App\Http\Controllers\Mcp;

use App\Http\Controllers\Api\McpController;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
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
                'inputSchema' => ['type' => 'object', 'properties' => (object) []],
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

        try {
            $data = $tool['method'] === null
                ? $this->fullContext($request, $mcp)
                : app()->call([$mcp, $tool['method']], ['request' => $request]);
        } catch (Throwable $e) {
            return ['content' => [['type' => 'text', 'text' => $e->getMessage()]], 'isError' => true];
        }

        return ['content' => [['type' => 'text', 'text' => json_encode($data, JSON_PRETTY_PRINT)]]];
    }

    private function fullContext(Request $request, McpController $mcp): array
    {
        $context = [];

        foreach (self::TOOLS as $name => $tool) {
            if ($tool['method'] !== null) {
                $context[$name] = app()->call([$mcp, $tool['method']], ['request' => $request]);
            }
        }

        return $context;
    }
}
