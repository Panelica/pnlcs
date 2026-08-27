#!/usr/bin/env node
// Live proof against a real PNLCS install: spawns the actual MCP server,
// speaks the actual protocol, and exercises EVERY tool - the read set on the
// install's real data, the write set on a disposable client created for the
// run. Point it at a demo install, not at production books:
//
//   PNLCS_URL=... PNLCS_IDENTIFIER=... PNLCS_SECRET=... node test/live.mjs
//
// It prints one PASS/FAIL line per tool and exits non-zero if anything fails.
// The disposable data it leaves (client, invoice, ticket) is named
// mcp-live-<stamp>@example.com so it can be cleaned up afterwards.

import { spawn } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { callAction, config } from '../lib/api.js';

const serverPath = join(dirname(fileURLToPath(import.meta.url)), '..', 'server.js');
const stamp = Date.now().toString(36);
const email = `mcp-live-${stamp}@example.com`;

const child = spawn(process.execPath, [serverPath], {
  env: { ...process.env, PNLCS_ALLOW_WRITES: '1' },
  stdio: ['pipe', 'pipe', 'inherit'],
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
const rpc = (method, params) => new Promise((resolve, reject) => {
  const id = ++nextId;
  waiting.set(id, resolve);
  child.stdin.write(JSON.stringify({ jsonrpc: '2.0', id, method, params }) + '\n');
  setTimeout(() => {
    if (waiting.delete(id)) reject(new Error(`${method} timed out`));
  }, 30000);
});

const results = [];
async function tool(name, args = {}, expectError = null) {
  try {
    const answer = await rpc('tools/call', { name, arguments: args });
    const text = answer.result?.content?.[0]?.text ?? '';
    if (expectError) {
      const ok = answer.result?.isError && text.includes(expectError);
      results.push([name, ok, ok ? `error surfaced: ${expectError}` : `expected "${expectError}", got: ${text.slice(0, 80)}`]);
      return null;
    }
    const ok = !answer.result?.isError && text.includes('"result": "success"');
    results.push([name, ok, ok ? '' : text.slice(0, 120)]);
    return ok ? JSON.parse(text) : null;
  } catch (e) {
    results.push([name, false, e.message]);
    return null;
  }
}

// Handshake first - a client that cannot initialize has no tools at all.
const init = await rpc('initialize', { protocolVersion: '2025-06-18' });
if (init.result?.serverInfo?.name !== 'pnlcs-mcp') {
  console.error('initialize failed');
  process.exit(1);
}
const list = await rpc('tools/list');
console.log(`connected: ${init.result.serverInfo.name} ${init.result.serverInfo.version}, ${list.result.tools.length} tools\n`);

// ---- reads on real data ----
await tool('get_stats');
await tool('get_health');
await tool('list_clients', { limitnum: 3 });
await tool('list_invoices', { limitnum: 3 });
await tool('list_invoices', { status: 'overdue', limitnum: 3 });
await tool('list_orders', { limitnum: 3 });
await tool('list_tickets', { limitnum: 3 });
await tool('get_ticket_counts');
await tool('list_transactions', { limitnum: 3 });
await tool('list_products');
await tool('get_activity_log', { limitnum: 5 });

// ---- writes on a disposable client ----
const created = await tool('add_client', {
  firstname: 'Mcp', lastname: 'Live', email, password2: 'McpLive!2026x',
});
const clientid = created?.clientid;

await tool('get_client', { email });
await tool('get_client', { clientid });
await tool('list_client_services', { clientid });
await tool('list_client_domains', { clientid });

const invoice = await tool('create_invoice', {
  userid: clientid,
  items: [{ description: 'MCP live check', amount: 1.5 }],
});
const invoiceid = invoice?.invoiceid ?? invoice?.id;
await tool('get_invoice', { invoiceid });
await tool('add_invoice_payment', { invoiceid, transid: `MCP-${stamp}`, amount: 1.5, gateway: 'banktransfer' });

// A department to open the ticket in, read straight off the API. The harness
// must report a failure here, not die of it.
let deptid;
try {
  const depts = await callAction(config(), 'getsupportdepartments');
  deptid = depts.departments?.[0]?.id;
} catch (e) {
  results.push(['(setup) getsupportdepartments', false, e.message]);
}
const ticket = await tool('open_ticket', {
  deptid, subject: `MCP live ${stamp}`, message: 'Opened by the pnlcs-mcp live check.', email,
});
const ticketid = ticket?.ticketid ?? ticket?.id;
await tool('get_ticket', { ticketid });
await tool('add_ticket_reply', { ticketid, message: 'And replied to by it.' });

// Suspension needs a provisioned hosting account; a live check must not touch
// one. The wire is proven by the error path: a definite, readable refusal.
await tool('suspend_service', { serviceid: 99999999, reason: 'mcp live check' }, 'Service Not Found');
await tool('unsuspend_service', { serviceid: 99999999 }, 'Service Not Found');

// ---- report ----
console.log('');
let failed = 0;
for (const [name, ok, note] of results) {
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}${note ? '  - ' + note : ''}`);
  if (!ok) failed++;
}
console.log(`\n${results.length - failed}/${results.length} tools passed`);
console.log(`disposable data: client ${clientid} (${email}), invoice ${invoiceid}, ticket ${ticketid}`);
child.kill();
process.exit(failed ? 1 : 0);
