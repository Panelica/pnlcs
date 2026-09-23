// The thin wire to a PNLCS install. Every tool goes through callAction: read
// actions travel as GET query strings, write actions as JSON POSTs - the same
// two shapes the PNLCS API serves its own screens with.
//
// Credentials ride in the X-API-Key / X-API-Secret headers, never in the URL
// or the body. They used to go in the query string of every read, which put
// the secret into the web server's access log, any proxy's log and the
// install's own request log on every call.

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

  // A hung install must become a readable error, not a call that never
  // returns; 30 seconds is far beyond any healthy PNLCS answer.
  const signal = AbortSignal.timeout(30_000);

  const auth = { 'X-API-Key': cfg.identifier, 'X-API-Secret': cfg.secret, Accept: 'application/json' };

  let response;
  try {
    if (method === 'GET') {
      const qs = new URLSearchParams();
      for (const [k, v] of Object.entries(clean)) qs.set(k, String(v));
      const query = qs.toString();
      response = await fetch(`${cfg.url}/api/v1/${action}${query ? `?${query}` : ''}`, {
        headers: auth,
        signal,
      });
    } else {
      response = await fetch(`${cfg.url}/api/v1/${action}`, {
        method: 'POST',
        headers: { ...auth, 'Content-Type': 'application/json' },
        body: JSON.stringify(clean),
        signal,
      });
    }
  } catch (e) {
    if (e.name === 'TimeoutError' || e.name === 'AbortError') {
      throw new Error(`PNLCS did not answer within 30 seconds (${action}).`);
    }
    throw e;
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
