// The whole server, tested over its real surface: a child process speaking
// newline-delimited JSON-RPC on stdio, calling a local HTTP stub that stands
// in for PNLCS and records exactly what arrived on the wire. Every tool is
// exercised - the read set and the write set - plus the handshake, the
// write-gate, and both failure shapes.

import { test, before, after } from 'node:test';
import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import { createServer } from 'node:http';
import { once } from 'node:events';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

import { toolset } from '../lib/tools.js';

const serverPath = join(dirname(fileURLToPath(import.meta.url)), '..', 'server.js');

// ---- PNLCS stub -----------------------------------------------------------

let stub;
let stubUrl;
const seen = [];
let nextResponse = null; // {status, body} override for one request

before(async () => {
  stub = createServer(async (req, res) => {
    const url = new URL(req.url, 'http://localhost');
    let body = '';
    for await (const chunk of req) body += chunk;
    seen.push({
      method: req.method,
      path: url.pathname,
      query: Object.fromEntries(url.searchParams),
      json: body ? JSON.parse(body) : null,
    });
    const answer = nextResponse ?? { status: 200, body: { result: 'success', echo: true } };
    nextResponse = null;
    res.writeHead(answer.status, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify(answer.body));
  });
  stub.listen(0, '127.0.0.1');
  await once(stub, 'listening');
  stubUrl = `http://127.0.0.1:${stub.address().port}`;
});

after(() => stub?.close());

// ---- MCP client over stdio ------------------------------------------------

function startMcp(extraEnv = {}) {
  const child = spawn(process.execPath, [serverPath], {
    env: {
      ...process.env,
      PNLCS_URL: stubUrl,
      PNLCS_IDENTIFIER: 'stub_id',
      PNLCS_SECRET: 'stub_secret',
      ...extraEnv,
    },
    stdio: ['pipe', 'pipe', 'pipe'],
  });

  let buffer = '';
  const waiting = new Map();
  child.stdout.on('data', (chunk) => {
    buffer += chunk;
    let idx;
    while ((idx = buffer.indexOf('\n')) >= 0) {
      const line = buffer.slice(0, idx);
      buffer = buffer.slice(idx + 1);
      if (!line.trim()) continue;
      const msg = JSON.parse(line);
      waiting.get(msg.id)?.(msg);
      waiting.delete(msg.id);
    }
  });

  let nextId = 0;
  const rpc = (method, params) => new Promise((resolve) => {
    const id = ++nextId;
    waiting.set(id, resolve);
    child.stdin.write(JSON.stringify({ jsonrpc: '2.0', id, method, params }) + '\n');
  });

  return { child, rpc, stop: () => child.kill() };
}

// Arguments that satisfy every tool's schema for the wire test.
const SAMPLE_ARGS = {
  list_clients: { search: 'ada', limitnum: 5, limitstart: 10 },
  get_client: { clientid: 7 },
  list_client_services: { clientid: 7 },
  list_client_domains: { clientid: 7 },
  list_invoices: { status: 'overdue', userid: 7 },
  get_invoice: { invoiceid: 3 },
  list_orders: { status: 'fraud' },
  list_tickets: { status: 'open' },
  get_ticket: { ticketid: 4 },
  list_transactions: { clientid: 7 },
  get_activity_log: { limitnum: 10 },
  add_client: { firstname: 'Ada', lastname: 'Lovelace', email: 'ada@example.com', password2: 'Str0ngPass!' },
  create_invoice: { userid: 7, items: [{ description: 'Hosting', amount: 9.9 }] },
  add_invoice_payment: { invoiceid: 3, transid: 'TX-1', amount: 9.9 },
  open_ticket: { deptid: 1, subject: 'Help', message: 'It broke', email: 'ada@example.com' },
  add_ticket_reply: { ticketid: 4, message: 'On it' },
  suspend_service: { serviceid: 5, reason: 'unpaid' },
  unsuspend_service: { serviceid: 5 },
};

// ---- Tests ----------------------------------------------------------------

test('handshake: initialize, ping, tools/list', async () => {
  const mcp = startMcp({ PNLCS_ALLOW_WRITES: '1' });
  try {
    const init = await mcp.rpc('initialize', { protocolVersion: '2025-06-18', capabilities: {} });
    assert.equal(init.result.serverInfo.name, 'pnlcs-mcp');
    assert.equal(init.result.protocolVersion, '2025-06-18');
    assert.ok(init.result.capabilities.tools);

    const ping = await mcp.rpc('ping');
    assert.deepEqual(ping.result, {});

    const list = await mcp.rpc('tools/list');
    assert.equal(list.result.tools.length, toolset(true).length);
    for (const tool of list.result.tools) {
      assert.ok(tool.description.length > 10, `${tool.name} has a description`);
      assert.equal(tool.inputSchema.type, 'object');
    }
  } finally {
    mcp.stop();
  }
});

test('write tools are invisible and unusable without PNLCS_ALLOW_WRITES', async () => {
  const mcp = startMcp();
  try {
    const list = await mcp.rpc('tools/list');
    const names = list.result.tools.map((t) => t.name);
    assert.equal(names.length, toolset(false).length);
    assert.ok(!names.includes('add_client'));
    assert.ok(!names.includes('suspend_service'));

    // Not merely hidden: calling one anyway is refused before any HTTP happens.
    const seenBefore = seen.length;
    const call = await mcp.rpc('tools/call', { name: 'add_client', arguments: SAMPLE_ARGS.add_client });
    assert.match(call.error.message, /Unknown tool/);
    assert.equal(seen.length, seenBefore);
  } finally {
    mcp.stop();
  }
});

test('every tool reaches its PNLCS action with the right method, auth and params', async () => {
  const mcp = startMcp({ PNLCS_ALLOW_WRITES: '1' });
  try {
    for (const tool of toolset(true)) {
      const args = SAMPLE_ARGS[tool.name] ?? {};
      seen.length = 0;

      const answer = await mcp.rpc('tools/call', { name: tool.name, arguments: args });
      assert.equal(seen.length, 1, `${tool.name} made exactly one request`);
      const req = seen[0];

      assert.equal(req.path, `/api/v1/${tool.action}`, tool.name);
      assert.equal(req.method, tool.method ?? 'GET', tool.name);

      const carried = req.method === 'GET' ? req.query : req.json;
      assert.equal(carried.identifier, 'stub_id', `${tool.name} carries the identifier`);
      assert.equal(carried.secret, 'stub_secret', `${tool.name} carries the secret`);
      for (const [k, v] of Object.entries(args)) {
        const sent = carried[k];
        if (req.method === 'GET') {
          assert.equal(sent, String(v), `${tool.name} sends ${k}`);
        } else {
          assert.deepEqual(sent, v, `${tool.name} sends ${k}`);
        }
      }

      assert.ok(!answer.result.isError, `${tool.name} succeeded`);
      assert.match(answer.result.content[0].text, /"result": "success"/);
    }
  } finally {
    mcp.stop();
  }
});

test('a PNLCS error comes back as a readable tool result, not a crash', async () => {
  const mcp = startMcp();
  try {
    nextResponse = { status: 404, body: { result: 'error', message: 'Client Not Found' } };
    const answer = await mcp.rpc('tools/call', { name: 'get_client', arguments: { clientid: 999999 } });
    assert.equal(answer.result.isError, true);
    assert.match(answer.result.content[0].text, /Client Not Found/);

    // And the server is still alive afterwards.
    const ping = await mcp.rpc('ping');
    assert.deepEqual(ping.result, {});
  } finally {
    mcp.stop();
  }
});

test('an unknown method gets a JSON-RPC error', async () => {
  const mcp = startMcp();
  try {
    const answer = await mcp.rpc('resources/list');
    assert.equal(answer.error.code, -32601);
  } finally {
    mcp.stop();
  }
});

test('a one-shot pipe still gets every answer before the server exits', async () => {
  const child = spawn(process.execPath, [serverPath], {
    env: { ...process.env, PNLCS_URL: stubUrl, PNLCS_IDENTIFIER: 'stub_id', PNLCS_SECRET: 'stub_secret' },
    stdio: ['pipe', 'pipe', 'pipe'],
  });
  child.stdin.end(
    JSON.stringify({ jsonrpc: '2.0', id: 1, method: 'initialize', params: {} }) + '\n'
    + JSON.stringify({ jsonrpc: '2.0', id: 2, method: 'tools/call', params: { name: 'get_stats', arguments: {} } }) + '\n'
  );
  let out = '';
  child.stdout.on('data', (c) => { out += c; });
  const [code] = await once(child, 'exit');
  assert.equal(code, 0);
  const answers = out.trim().split('\n').map((l) => JSON.parse(l));
  assert.equal(answers.length, 2);
  assert.match(answers[1].result.content[0].text, /"result": "success"/);
});
