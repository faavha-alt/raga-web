#!/usr/bin/env node
import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import { z } from 'zod';

const BASE_URL = (process.env.RAGA_API_BASE || 'https://raga.favha.cloud/api').replace(/\/$/, '');
const TOKEN = process.env.RAGA_API_TOKEN;

if (!TOKEN) {
  console.error(
    'Missing RAGA_API_TOKEN. Get one with:\n' +
    '  curl -X POST ' + BASE_URL + '/auth/token -H "Content-Type: application/json" ' +
    '-d \'{"email":"you@example.com","password":"...","device_name":"claude"}\'\n' +
    'then set RAGA_API_TOKEN to the returned token.'
  );
  process.exit(1);
}

async function fetchJson(path, options = {}) {
  const res = await fetch(BASE_URL + path, {
    ...options,
    headers: {
      Authorization: `Bearer ${TOKEN}`,
      Accept: 'application/json',
      ...(options.body ? { 'Content-Type': 'application/json' } : {}),
      ...options.headers,
    },
  });

  const body = await res.text();

  if (!res.ok) {
    throw new Error(`RAGA API ${path} returned ${res.status}: ${body}`);
  }

  return JSON.parse(body);
}

function postJson(path, data) {
  return fetchJson(path, { method: 'POST', body: JSON.stringify(data) });
}

function jsonContent(data) {
  return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
}

const server = new McpServer({ name: 'raga-web', version: '1.0.0' });

const endpoints = {
  raga_overview: {
    path: '/mcp/overview',
    description: "Today's snapshot for the current RAGA user: readiness, sleep, HRV, resting HR, body battery, stress, training load, plus auto-generated insights comparing this week to last. Good starting point for any plan-related question.",
  },
  raga_training: {
    path: '/mcp/training',
    description: 'Training load status (acute/chronic load, acute:chronic ratio, monotony, risk level) with 90-day history, this week\'s volume totals, 30-day consistency, top activity types, active training plans, recent workouts, and personal records.',
  },
  raga_recovery: {
    path: '/mcp/recovery',
    description: "Current recovery score and readiness score with their factor breakdowns (sleep, HRV, resting HR, stress, training load, body battery, recent activity), plus 30-day history of both. Use this to judge whether today should be a hard, easy, or rest day.",
  },
  raga_health: {
    path: '/mcp/health',
    description: 'Latest health vitals (resting/avg/max HR, HRV, stress, body battery, respiration, SpO2, steps, calories, recovery time, Garmin readiness) each compared against the user\'s personal baseline.',
  },
  raga_running: {
    path: '/mcp/running',
    description: 'Running-specific performance: this week\'s totals, calculated performance rating, 30-day consistency, latest VO2max, longest recent runs, and personal records (fastest 1K/5K/10K/half/marathon, longest run).',
  },
  raga_trail: {
    path: '/mcp/trail',
    description: '90-day trail running summary: distance/elevation totals, moving time, count of repeated routes, and the most recent trail runs.',
  },
};

for (const [name, { path, description }] of Object.entries(endpoints)) {
  server.registerTool(name, { description, inputSchema: {} }, async () => jsonContent(await fetchJson(path)));
}

server.registerTool(
  'raga_full_context',
  {
    description: 'Everything above (overview, training, recovery, health, running, trail) in one call. Use this when building or revising a full training/recovery plan, so you have the complete picture instead of guessing which slice you need.',
    inputSchema: {},
  },
  async () => {
    const entries = await Promise.all(
      Object.entries(endpoints).map(async ([name, { path }]) => [name, await fetchJson(path)])
    );

    return jsonContent(Object.fromEntries(entries));
  }
);

const plannedWorkoutSchema = z.object({
  type: z.string().describe('e.g. running, trail_running, cycling, strength'),
  duration_minutes: z.number().optional(),
  distance_meters: z.number().optional(),
  target_pace_seconds_per_km: z.number().optional(),
  target_heart_rate_zone: z.number().int().min(1).max(5).optional(),
  intensity: z.string().optional().describe('e.g. easy, moderate, hard'),
  warm_up: z.string().optional(),
  main_set: z.string().optional(),
  cool_down: z.string().optional(),
  notes: z.string().optional(),
});

server.registerTool(
  'raga_save_training_plan',
  {
    description: "Save a structured multi-day training plan into RAGA so it shows up on the user's Training Calendar. Organize it as weeks, each with days, each with zero or more planned workouts. Call this once you and the user have agreed on a plan — don't call it speculatively.",
    inputSchema: {
      name: z.string().describe('Short name for the plan, e.g. "Half Marathon Base Build".'),
      start_date: z.string().describe('YYYY-MM-DD'),
      target_date: z.string().describe('YYYY-MM-DD'),
      weeks: z.array(z.object({
        week_number: z.number().int().min(1),
        start_date: z.string(),
        end_date: z.string(),
        days: z.array(z.object({
          date: z.string(),
          workouts: z.array(plannedWorkoutSchema).optional().describe('Leave empty for a rest day.'),
        })),
      })),
    },
  },
  async (args) => jsonContent(await postJson('/mcp/training-plan', args))
);

server.registerTool(
  'raga_save_recommendation',
  {
    description: 'Save a short, dated recommendation into RAGA — shows up on the user\'s Dashboard. Use for a single piece of advice (e.g. "take it easy today"), not a multi-day plan (use raga_save_training_plan for that).',
    inputSchema: {
      date: z.string().describe('YYYY-MM-DD, the date this recommendation applies to.'),
      category: z.string().describe('Short category label, e.g. "recovery", "training", "health".'),
      title: z.string(),
      message: z.string(),
      priority: z.number().int().min(0).max(10).optional().describe('Higher = more important. Optional, defaults to 0.'),
    },
  },
  async (args) => jsonContent(await postJson('/mcp/recommendation', args))
);

const transport = new StdioServerTransport();
await server.connect(transport);
