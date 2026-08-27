// The thin wire to a PNLCS install. Every tool goes through callAction: read
// actions travel as GET query strings, write actions as JSON POSTs - the same
// two shapes the PNLCS API serves its own screens with. Credentials ride in
// the identifier/secret parameters the API has always taken.

export function config(env = process.env) {
  const url = (env.PNLCS_URL || '').replace(/\/+$/, '');
  const identifier = env.PNLCS_IDENTIFIER || '';
  const secret = env.PNLCS_SECRET || '';
  if (!url || !identifier || !secret) {
    throw new Error('Set PNLCS_URL, PNLCS_IDENTIFIER and PNLCS_SECRET.');
  }

  return { url, identifier, secret, allowWrites: env.PNLCS_ALLOW_WRITES === '1' };
}

export async function callAction(cfg, action, params = {}, method = 'GET') {
  const clean = {};
  for (const [k, v] of Object.entries(params)) {
    if (v !== undefined && v !== null && v !== '') clean[k] = v;
  }

  let response;
  if (method === 'GET') {
    const qs = new URLSearchParams({ identifier: cfg.identifier, secret: cfg.secret });
    for (const [k, v] of Object.entries(clean)) qs.set(k, String(v));
    response = await fetch(`${cfg.url}/api/v1/${action}?${qs}`, {
      headers: { Accept: 'application/json' },
    });
  } else {
    response = await fetch(`${cfg.url}/api/v1/${action}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ identifier: cfg.identifier, secret: cfg.secret, ...clean }),
    });
  }

  let body;
  try {
    body = await response.json();
  } catch {
    throw new Error(`PNLCS answered ${response.status} with a non-JSON body.`);
  }

  // The API answers {result: "success"|"error"} on every action; validation
  // failures arrive as Laravel's {message, errors} with a 4xx status.
  if (body.result === 'error' || (!response.ok && body.result !== 'success')) {
    const detail = body.message || JSON.stringify(body.errors || body);
    const err = new Error(detail);
    err.status = response.status;
    throw err;
  }

  return body;
}
