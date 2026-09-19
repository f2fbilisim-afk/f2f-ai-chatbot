/**
 * F2F AI Chatbot — interactive widget demo
 * Mirrors the WordPress plugin UI; uses OpenAI when OPENAI_API_KEY is set,
 * otherwise returns contextual mock replies.
 */

const http = require('http');
const fs = require('fs');
const path = require('path');
const { URL } = require('url');

const PORT = Number(process.env.PORT || 43145);
const ROOT = __dirname;
const PLUGIN_ASSETS = path.join(__dirname, '..', 'f2f-ai-chatbot', 'assets');
const OPENAI_KEY = process.env.OPENAI_API_KEY || '';
const MODEL = process.env.OPENAI_MODEL || 'gpt-4o-mini';

const SYSTEM_PROMPT =
  process.env.F2F_SYSTEM_PROMPT ||
  "Sen F2F Bilişim'in yardımcı asistanısın. Ziyaretçilere e-ticaret, e-ihracat, web sitesi ve dijital hizmetler konusunda nazik, net ve kısa Türkçe yanıtlar ver. Bilmediğin konularda spekülasyon yapma; iletişim için info@f2fbilisim.com veya +90 549 900 93 10 yönlendir.";

const MIME = {
  '.html': 'text/html; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.js': 'application/javascript; charset=utf-8',
  '.json': 'application/json; charset=utf-8',
  '.svg': 'image/svg+xml',
  '.png': 'image/png',
  '.ico': 'image/x-icon',
};

function send(res, status, body, headers = {}) {
  const payload = Buffer.isBuffer(body) ? body : Buffer.from(body);
  res.writeHead(status, {
    'Content-Length': payload.length,
    ...headers,
  });
  res.end(payload);
}

function sendJson(res, status, obj) {
  send(res, status, JSON.stringify(obj), {
    'Content-Type': 'application/json; charset=utf-8',
  });
}

function readBody(req) {
  return new Promise((resolve, reject) => {
    const chunks = [];
    req.on('data', (c) => chunks.push(c));
    req.on('end', () => resolve(Buffer.concat(chunks).toString('utf8')));
    req.on('error', reject);
  });
}

function mockReply(message) {
  const m = String(message || '').toLowerCase();
  if (/fiyat|ücret|kaç|paket|vip/.test(m)) {
    return 'Fiyatlandırma projeye göre değişir. Web sitesi, e-ticaret veya VIP Enterprise için info@f2fbilisim.com ya da +90 549 900 93 10 üzerinden teklif alabilirsiniz.';
  }
  if (/woocommerce|e-?ticaret|mağaza/.test(m)) {
    return 'WooCommerce ve özel e-ticaret kurulumlarında yanınızdayız: ödeme sistemleri, stok, kargo ve çok dilli satış. İhtiyacınızı yazın, yönlendireyim.';
  }
  if (/hosting|sunucu|domain|alan adı/.test(m)) {
    return 'Alan adı tahsisi, hosting ve e-posta sistemlerini F2F bünyesinde kuruyoruz. Mevcut alan adınız varsa yönlendirmeyi biz yapabiliriz.';
  }
  if (/merhaba|selam|hello|hi\b/.test(m)) {
    return 'Merhaba! F2F Bilişim asistanıyım. Web sitesi, e-ticaret veya dijital hizmetler hakkında sorabilirsiniz.';
  }
  return `Demo modundayım (OPENAI_API_KEY yok). "${message}" sorunuza canlı OpenAI yanıtı için ortam değişkenine API anahtarınızı ekleyin. Gerçek kurulumda WordPress eklentisi anahtarı Ayarlar → F2F AI Chatbot ekranından alır. İletişim: info@f2fbilisim.com`;
}

async function openaiChat(messages) {
  const res = await fetch('https://api.openai.com/v1/chat/completions', {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${OPENAI_KEY}`,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      model: MODEL,
      messages,
      max_tokens: 500,
      temperature: 0.7,
    }),
  });
  const data = await res.json().catch(() => ({}));
  if (!res.ok) {
    const err = (data && data.error && data.error.message) || `OpenAI HTTP ${res.status}`;
    throw new Error(err);
  }
  const content = data?.choices?.[0]?.message?.content;
  if (!content) throw new Error('OpenAI boş yanıt döndürdü.');
  return String(content).trim();
}

function serveFile(res, filePath) {
  fs.readFile(filePath, (err, data) => {
    if (err) {
      send(res, 404, 'Not found', { 'Content-Type': 'text/plain; charset=utf-8' });
      return;
    }
    const ext = path.extname(filePath).toLowerCase();
    send(res, 200, data, {
      'Content-Type': MIME[ext] || 'application/octet-stream',
      'Cache-Control': 'no-cache',
    });
  });
}

const server = http.createServer(async (req, res) => {
  try {
    const url = new URL(req.url, `http://${req.headers.host || '127.0.0.1'}`);

    if (req.method === 'GET' && (url.pathname === '/' || url.pathname === '/index.html')) {
      serveFile(res, path.join(ROOT, 'public', 'index.html'));
      return;
    }

    if (req.method === 'GET' && url.pathname.startsWith('/plugin/')) {
      const rel = url.pathname.replace('/plugin/', '');
      const safe = path.normalize(rel).replace(/^(\.\.(\/|\\|$))+/, '');
      serveFile(res, path.join(PLUGIN_ASSETS, safe));
      return;
    }

    if (req.method === 'GET' && url.pathname.startsWith('/assets/')) {
      const rel = url.pathname.replace('/assets/', '');
      const safe = path.normalize(rel).replace(/^(\.\.(\/|\\|$))+/, '');
      serveFile(res, path.join(ROOT, 'public', 'assets', safe));
      return;
    }

    if (req.method === 'GET' && url.pathname === '/api/config') {
      sendJson(res, 200, {
        enabled: true,
        botName: 'F2F Asistan',
        welcomeMessage: 'Merhaba! F2F Bilişim asistanıyım. Size nasıl yardımcı olabilirim?',
        primaryColor: '#0B6E4F',
        position: 'right',
        mode: OPENAI_KEY ? 'openai' : 'demo',
        hasApiKey: Boolean(OPENAI_KEY),
      });
      return;
    }

    if (req.method === 'POST' && url.pathname === '/api/chat') {
      const raw = await readBody(req);
      let body = {};
      try {
        body = JSON.parse(raw || '{}');
      } catch {
        sendJson(res, 400, { message: 'Geçersiz JSON.' });
        return;
      }
      const message = String(body.message || '').trim();
      if (!message) {
        sendJson(res, 400, { message: 'Mesaj boş olamaz.' });
        return;
      }
      if (message.length > 2000) {
        sendJson(res, 400, { message: 'Mesaj çok uzun (max 2000 karakter).' });
        return;
      }

      const history = Array.isArray(body.history) ? body.history.slice(-12) : [];
      const messages = [{ role: 'system', content: SYSTEM_PROMPT }];
      for (const turn of history) {
        if (!turn || typeof turn !== 'object') continue;
        if (turn.role !== 'user' && turn.role !== 'assistant') continue;
        const content = String(turn.content || '').slice(0, 2000);
        if (!content) continue;
        messages.push({ role: turn.role, content });
      }
      messages.push({ role: 'user', content: message });

      try {
        let reply;
        if (OPENAI_KEY) {
          reply = await openaiChat(messages);
        } else {
          await new Promise((r) => setTimeout(r, 450));
          reply = mockReply(message);
        }
        sendJson(res, 200, { reply, mode: OPENAI_KEY ? 'openai' : 'demo' });
      } catch (err) {
        sendJson(res, 502, { message: err.message || 'Yanıt alınamadı.' });
      }
      return;
    }

    send(res, 404, 'Not found', { 'Content-Type': 'text/plain; charset=utf-8' });
  } catch (err) {
    sendJson(res, 500, { message: err.message || 'Sunucu hatası' });
  }
});

server.listen(PORT, '0.0.0.0', () => {
  console.log(`F2F AI Chatbot demo → http://127.0.0.1:${PORT}`);
  console.log(OPENAI_KEY ? `OpenAI mode (${MODEL})` : 'Demo mode (set OPENAI_API_KEY for live replies)');
});
