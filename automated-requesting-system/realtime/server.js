'use strict';

const crypto = require('node:crypto');
const fs = require('node:fs');
const http = require('node:http');
const https = require('node:https');
const path = require('node:path');

const secretPath = path.join(process.env.ARS_STORAGE_DIR || '/shared-storage', 'realtime.secret');
const clients = new Set();

function loadSecret() {
    const value = fs.readFileSync(secretPath, 'utf8').trim();
    if (value.length < 32) throw new Error('Realtime signing key is invalid.');
    return value;
}

const secret = loadSecret();

function secureEqualHex(actual, expected) {
    if (!/^[a-f0-9]+$/i.test(actual) || actual.length !== expected.length) return false;
    return crypto.timingSafeEqual(Buffer.from(actual, 'hex'), Buffer.from(expected, 'hex'));
}

function decodeToken(token) {
    const parts = String(token || '').split('.');
    if (parts.length !== 2) return null;

    const expected = crypto.createHmac('sha256', secret).update(parts[0]).digest();
    let received;
    try {
        received = Buffer.from(parts[1], 'base64url');
    } catch {
        return null;
    }
    if (received.length !== expected.length || !crypto.timingSafeEqual(received, expected)) return null;

    try {
        const claims = JSON.parse(Buffer.from(parts[0], 'base64url').toString('utf8'));
        if (!Number.isInteger(claims.user_id) || claims.user_id < 1) return null;
        if (!Number.isInteger(claims.exp) || claims.exp <= Math.floor(Date.now() / 1000)) return null;
        if (![1, 2, 3, 4, 5, 6, 7, 8, 9].includes(claims.role_id)) return null;
        return claims;
    } catch {
        return null;
    }
}

function readBody(request, maxBytes = 4096) {
    return new Promise((resolve, reject) => {
        const chunks = [];
        let size = 0;
        request.on('data', (chunk) => {
            size += chunk.length;
            if (size > maxBytes) {
                reject(new Error('Request body too large.'));
                request.destroy();
                return;
            }
            chunks.push(chunk);
        });
        request.on('end', () => resolve(Buffer.concat(chunks).toString('utf8')));
        request.on('error', reject);
    });
}

function broadcastPendingCountChanged() {
    for (const client of clients) {
        client.write('event: pending-count-changed\ndata: {}\n\n');
    }
}

const publishServer = http.createServer(async (request, response) => {
    if (request.method === 'GET' && request.url === '/health') {
        response.writeHead(200, { 'Content-Type': 'text/plain' });
        response.end('ok');
        return;
    }
    if (request.method !== 'POST' || request.url !== '/publish') {
        response.writeHead(404);
        response.end();
        return;
    }

    try {
        const body = await readBody(request);
        const signature = request.headers['x-ars-signature'] || '';
        const expected = crypto.createHmac('sha256', secret).update(body).digest('hex');
        if (!secureEqualHex(signature, expected) || body !== '{"type":"pending-count-changed"}') {
            response.writeHead(403);
            response.end();
            return;
        }

        broadcastPendingCountChanged();
        response.writeHead(204);
        response.end();
    } catch {
        if (!response.headersSent) response.writeHead(400);
        response.end();
    }
});

function handleEventRequest(request, response) {
    const requestUrl = new URL(request.url, 'https://realtime.invalid');
    if (request.method !== 'GET' || requestUrl.pathname !== '/events') {
        response.writeHead(404);
        response.end();
        return;
    }

    const origin = request.headers.origin;
    let originUrl;
    let requestHost;
    try {
        originUrl = new URL(origin);
        requestHost = new URL('https://' + request.headers.host).hostname.toLowerCase();
    } catch {
        response.writeHead(403);
        response.end();
        return;
    }
    if (originUrl.protocol !== 'https:' || originUrl.hostname.toLowerCase() !== requestHost) {
        response.writeHead(403);
        response.end();
        return;
    }

    const claims = decodeToken(requestUrl.searchParams.get('token'));
    if (!claims) {
        response.writeHead(401, { 'Access-Control-Allow-Origin': origin, 'Vary': 'Origin' });
        response.end();
        return;
    }

    response.writeHead(200, {
        'Content-Type': 'text/event-stream; charset=utf-8',
        'Cache-Control': 'no-cache, no-transform',
        'Connection': 'keep-alive',
        'Access-Control-Allow-Origin': origin,
        'Vary': 'Origin',
    });
    response.flushHeaders();
    clients.add(response);
    response.write('retry: 3000\nevent: pending-count-changed\ndata: {}\n\n');

    const heartbeat = setInterval(() => response.write(': keep-alive\n\n'), 20000);
    const expiry = setTimeout(() => response.end(), Math.max(0, claims.exp * 1000 - Date.now()));
    response.on('close', () => {
        clearInterval(heartbeat);
        clearTimeout(expiry);
        clients.delete(response);
    });
}

async function start() {
    let tlsOptions;
    for (let attempt = 0; attempt < 30; attempt += 1) {
        try {
            tlsOptions = {
                key: fs.readFileSync('/certs/ars-selfsigned.key'),
                cert: fs.readFileSync('/certs/ars-selfsigned.crt'),
            };
            break;
        } catch {
            await new Promise((resolve) => setTimeout(resolve, 1000));
        }
    }
    if (!tlsOptions) throw new Error('Shared TLS certificate is not available.');

    const eventServer = https.createServer(tlsOptions, handleEventRequest);
    publishServer.listen(3000, '0.0.0.0');
    eventServer.listen(8443, '0.0.0.0');
}

start().catch((error) => {
    console.error(error.message);
    process.exit(1);
});