#!/usr/bin/env node
import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import { z } from 'zod';

const BASE_URL = (process.env.RAGA_API_BASE || 'https://raga.mipa.uns.ac.id/api').replace(/\/$/, '');
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

async function fetchJson(path) {
  const res = await fetch(BASE_URL + path, {
    headers: {
      Authorization: `Bearer ${TOKEN}`,
      Accept: 'application/json',
    },
  });

  const body = await res.text();

  if (!res.ok) {
    throw new Error(`RAGA API ${path} returned ${res.status}: ${body}`);
  }

  return JSON.parse(body);
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

const transport = new StdioServerTransport();
await server.connect(transport);
