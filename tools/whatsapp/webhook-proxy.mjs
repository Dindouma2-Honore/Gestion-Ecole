import http from 'node:http';
import { pathToFileURL } from 'node:url';

// Development gateway: only the signed WhatsApp endpoint is public.
export function createWebhookProxy(upstream = 'http://laravel.test:80') {
  const origin = new URL(upstream);
  if (origin.protocol !== 'http:' || origin.username || origin.password) throw new Error('Invalid internal upstream');
  const server = http.createServer((req, res) => {
    const reply = (status, body = 'Webhook request rejected') => {
      if (res.headersSent || res.destroyed) return;
      res.writeHead(status, { 'Content-Type': 'text/plain; charset=utf-8', 'Cache-Control': 'no-store', 'X-Content-Type-Options': 'nosniff' });
      res.end(body);
    };
    let url;
    try { url = new URL(req.url, 'http://gateway'); } catch { return reply(400); }
    if (url.pathname !== '/webhooks/whatsapp' || !req.url.startsWith('/webhooks/whatsapp')) return reply(404);
    if (!['GET', 'POST'].includes(req.method)) return reply(405);
    const limit = 1024 * 1024;
    if (Number(req.headers['content-length'] || 0) > limit) return reply(413);
    if (req.method === 'POST' && !String(req.headers['content-type'] || '').startsWith('application/json')) return reply(415);
    let size = 0;
    const chunks = [];
    req.on('data', chunk => {
      size += chunk.length;
      if (size > limit) { reply(413); req.destroy(); return; }
      chunks.push(chunk);
    });
    req.on('error', () => reply(400));
    req.on('end', () => {
      if (size > limit) return;
      // No cookies, user credentials, forwarded routing headers or arbitrary paths.
      const headers = { 'Accept': 'application/json', 'Content-Type': 'application/json', 'Content-Length': size };
      if (req.headers['x-hub-signature-256']) headers['X-Hub-Signature-256'] = req.headers['x-hub-signature-256'];
      const target = http.request({ hostname: origin.hostname, port: origin.port || 80, path: url.pathname + url.search, method: req.method, headers, timeout: 10000 }, incoming => {
        if (incoming.statusCode !== 200) { incoming.resume(); reply(incoming.statusCode >= 400 && incoming.statusCode < 500 ? incoming.statusCode : 502); return; }
        let responseSize = 0;
        const data = [];
        incoming.on('data', chunk => {
          responseSize += chunk.length;
          if (responseSize > 65536) { incoming.destroy(); reply(502); return; }
          data.push(chunk);
        });
        incoming.on('error', () => reply(502));
        incoming.on('end', () => reply(200, Buffer.concat(data)));
      });
      target.on('timeout', () => { target.destroy(); reply(504); });
      target.on('error', () => reply(502));
      target.end(Buffer.concat(chunks));
    });
  });
  server.headersTimeout = 5000;
  server.requestTimeout = 15000;
  server.on('upgrade', (_req, socket) => socket.destroy());
  return server;
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  createWebhookProxy(process.env.WHATSAPP_WEBHOOK_UPSTREAM).listen(8080, '0.0.0.0');
}
