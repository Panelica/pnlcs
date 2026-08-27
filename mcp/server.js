#!/usr/bin/env node
// pnlcs-mcp - Model Context Protocol server for PNLCS.
//
// Speaks MCP's stdio transport directly: newline-delimited JSON-RPC 2.0,
// answering initialize, ping, tools/list and tools/call. No dependencies -
// the protocol surface an assistant needs is small enough to write down,
// and a billing integration should not pull a dependency tree it cannot read.

import { createInterface } from 'node:readline';
import { config, callAction } from './lib/api.js';
import { descriptors, findTool } from './lib/tools.js';

const VERSION = '1.0.0';

let cfg;
try {
  cfg = config();
} catch (e) {
  console.error(`pnlcs-mcp: ${e.message}`);
  process.exit(1);
}

function reply(id, result) {
  process.stdout.write(JSON.stringify({ jsonrpc: '2.0', id, result }) + '\n');
}

function replyError(id, code, message) {
  process.stdout.write(JSON.stringify({ jsonrpc: '2.0', id, error: { code, message } }) + '\n');
}

async function handle(msg) {
  const { id, method, params } = msg;

  // Notifications (no id) need no answer; the only ones a client sends here
  // are notifications/initialized and cancellations.
  if (id === undefined || id === null) return;

  switch (method) {
    case 'initialize':
      return reply(id, {
        protocolVersion: params?.protocolVersion || '2025-06-18',
        capabilities: { tools: { listChanged: false } },
        serverInfo: { name: 'pnlcs-mcp', version: VERSION },
      });

    case 'ping':
      return reply(id, {});

    case 'tools/list':
      return reply(id, { tools: descriptors(cfg.allowWrites) });

    case 'tools/call': {
      const tool = findTool(cfg.allowWrites, params?.name);
      if (!tool) {
        return replyError(id, -32602, `Unknown tool: ${params?.name}`);
      }
      try {
        const body = await callAction(cfg, tool.action, params?.arguments ?? {}, tool.method ?? 'GET');
        return reply(id, { content: [{ type: 'text', text: JSON.stringify(body, null, 2) }] });
      } catch (e) {
        // Tool-level failures are results, not protocol errors - the model
        // should read them and correct course.
        return reply(id, { content: [{ type: 'text', text: `PNLCS error: ${e.message}` }], isError: true });
      }
    }

    default:
      return replyError(id, -32601, `Method not found: ${method}`);
  }
}

const rl = createInterface({ input: process.stdin, terminal: false });
rl.on('line', (line) => {
  if (!line.trim()) return;
  let msg;
  try {
    msg = JSON.parse(line);
  } catch {
    return replyError(null, -32700, 'Parse error');
  }
  handle(msg).catch((e) => {
    if (msg.id !== undefined && msg.id !== null) replyError(msg.id, -32603, e.message);
  });
});
rl.on('close', () => process.exit(0));
