import { test } from 'node:test';
import assert from 'node:assert/strict';
import http from 'node:http';
import { createWebhookProxy } from './webhook-proxy.mjs';

test('gateway isolates routes, preserves signed bytes and hides internal errors', async () => {
  const seen = [];
  const upstream = http.createServer((req, res) => {
    const chunks = [];
    req.on('data', chunk => chunks.push(chunk));
    req.on('end', () => {
      seen.push({ url: req.url, headers: req.headers, body: Buffer.concat(chunks).toString() });
      if (req.url.includes('error')) { res.writeHead(500); res.end('PRIVATE DEBUG DATA'); }
      else { res.writeHead(200, { 'Set-Cookie': 'private=value' }); res.end('challenge'); }
    });
  });
  await new Promise(resolve => upstream.listen(0, '127.0.0.1', resolve));
  const proxy = createWebhookProxy(`http://127.0.0.1:${upstream.address().port}`);
  await new Promise(resolve => proxy.listen(0, '127.0.0.1', resolve));
  const base = `http://127.0.0.1:${proxy.address().port}`;
  try {
    for (const path of ['/', '/admin/login', '/.env', '/webhooks/whatsapp/extra']) assert.equal((await fetch(base + path)).status, 404);
    assert.equal((await fetch(base + '/webhooks/whatsapp', { method: 'DELETE' })).status, 405);
    assert.equal((await fetch(base + '/webhooks/whatsapp', { method: 'POST', body: 'bad' })).status, 415);
    assert.equal(seen.length, 0);
    const body = '{ "entry": [] }';
    const response = await fetch(base + '/webhooks/whatsapp?hub.challenge=demo', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Hub-Signature-256': 'sha256=test-only', Cookie: 'session=private', Authorization: 'Bearer private', 'X-Forwarded-Host': 'evil.example' }, body });
    assert.equal(response.status, 200);
    assert.equal(await response.text(), 'challenge');
    assert.equal(response.headers.get('set-cookie'), null);
    assert.equal(seen[0].body, body);
    assert.equal(seen[0].headers['x-hub-signature-256'], 'sha256=test-only');
    for (const name of ['cookie', 'authorization', 'x-forwarded-host']) assert.equal(seen[0].headers[name], undefined);
    const error = await fetch(base + '/webhooks/whatsapp?error=1');
    assert.equal(error.status, 502);
    assert.equal((await error.text()).includes('PRIVATE'), false);
    assert.equal((await fetch(base + '/webhooks/whatsapp', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: 'x'.repeat(1024 * 1024 + 1) })).status, 413);
  } finally {
    proxy.closeAllConnections(); upstream.closeAllConnections();
    await Promise.all([new Promise(resolve => proxy.close(resolve)), new Promise(resolve => upstream.close(resolve))]);
  }
});
