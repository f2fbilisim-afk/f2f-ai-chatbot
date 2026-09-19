/**
 * F2F AI Proje Ajanı — demo preview server
 */

const http = require('http');
const fs = require('fs');
const path = require('path');
const { URL } = require('url');

// Load demo/.env if present (never commit — root .gitignore covers .env)
(function loadEnvFile() {
  const envPath = path.join(__dirname, '.env');
  if (!fs.existsSync(envPath)) return;
  const lines = fs.readFileSync(envPath, 'utf8').split(/\r?\n/);
  for (const line of lines) {
    const trimmed = line.trim();
    if (!trimmed || trimmed.startsWith('#')) continue;
    const eq = trimmed.indexOf('=');
    if (eq < 1) continue;
    const key = trimmed.slice(0, eq).trim();
    let val = trimmed.slice(eq + 1).trim();
    if (
      (val.startsWith('"') && val.endsWith('"')) ||
      (val.startsWith("'") && val.endsWith("'"))
    ) {
      val = val.slice(1, -1);
    }
    if (!(key in process.env)) process.env[key] = val;
  }
})();

const PORT = Number(process.env.PORT || 43145);
const ROOT = __dirname;
const PLUGIN_ASSETS = path.join(__dirname, '..', 'f2f-ai-chatbot', 'assets');
const OPENAI_KEY = process.env.OPENAI_API_KEY || '';
const MODEL = process.env.OPENAI_MODEL || 'gpt-4o-mini';

const SYSTEM_PROMPT =
  process.env.F2F_SYSTEM_PROMPT ||
  "Sen F2F Bilişim'in AI Proje Ajanısın. Ziyaretçi bir hizmet seçti ve iletişim bilgilerini verdi. Nazik, net ve kısa Türkçe yanıtlar ver. Web sitesi, hosting, domain, e-ticaret ve yazılım projelerini netleştirmeye yardımcı ol.";

const PUBLIC_CONFIG = {
  enabled: true,
  botName: 'AI Proje Ajanı',
  avatarUrl: '/plugin/img/avatar-default.svg',
  primaryColor: '#22C55E',
  position: 'right',
  bottomMargin: 4,
  sideMargin: 2,
  launcherLabel: 'AI',
  teaserTitle: 'AI PROJE AJANI',
  teaserMessage: 'Merhaba! Fikrinizi anlatın, birlikte netleştirelim.',
  showTeaser: true,
  discoverHeadline: 'Ne oluşturmak istiyorsunuz?',
  discoverSubtext:
    'Ben F2F AI Proje Ajanı. Seçimden sonra kısa iletişim bilgisi alıp projenizi netleştirelim.',
  inputPlaceholder: "AI Proje Ajanı'na yazın...",
  dividerText: 'VEYA KEŞFET',
  featured: {
    id: 'featured',
    label: 'Web sitesi oluştur',
    subtitle: 'En popüler · ~2 dk',
    icon: 'layout',
  },
  services: [
    { id: 'service_1', label: 'Hosting al', icon: 'sparkle' },
    { id: 'service_2', label: 'Domain bul', icon: 'globe' },
    { id: 'service_3', label: 'E-ticaret', icon: 'bag' },
    { id: 'service_4', label: 'Yazılım', icon: 'code' },
  ],
  lead: {
    headline: 'Sizi tanıyalım',
    subtext:
      'Satış ekibimizin dönüş yapabilmesi için iletişim bilgilerinizi alın. Ardından ajanla devam edeceğiz.',
    btn: 'Devam et',
    cancel: 'Vazgeç',
  },
  whatsapp: {
    phone: '905499009310',
    message: 'Merhaba, AI Proje Ajanı üzerinden yazıyorum.',
    label: 'Canlı Görüşmeye Başla',
  },
  chatWelcome:
    'Merhaba {ad}! {hizmet} konusunda yardımcı olayım. Projenizi kısaca anlatır mısınız?',
  i18n: {
    firstName: 'Ad',
    lastName: 'Soyad',
    phone: 'Telefon',
    email: 'E-posta',
    phonePh: '05xx xxx xx xx',
    emailPh: 'ornek@firma.com',
    namePh: 'Adınız',
    lastPh: 'Soyadınız',
    open: 'Sohbeti aç',
    close: 'Küçült',
    send: 'Gönder',
    thinking: 'Ajan yazıyor...',
    error: 'Bir hata oluştu. Tekrar deneyin.',
    offline: 'Bağlantı kurulamadı.',
    required: 'Lütfen tüm alanları doldurun.',
  },
};

const MIME = {
  '.html': 'text/html; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.js': 'application/javascript; charset=utf-8',
  '.json': 'application/json; charset=utf-8',
  '.svg': 'image/svg+xml',
  '.png': 'image/png',
};

function send(res, status, body, headers = {}) {
  const payload = Buffer.isBuffer(body) ? body : Buffer.from(body);
  res.writeHead(status, { 'Content-Length': payload.length, ...headers });
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

function mockReply(message, lead) {
  const name = lead && lead.first_name ? lead.first_name : '';
  const svc = lead && lead.service ? lead.service : '';
  const m = String(message || '').toLowerCase();
  if (/hosting|sunucu/.test(m) || /hosting/i.test(svc)) {
    return `${name ? name + ', ' : ''}Hosting paketlerimiz SSD disk, yedekleme ve e-posta hesapları içerir. Trafik ve depolama ihtiyacınızı söylerseniz uygun paketi önereyim.`;
  }
  if (/domain|alan adı/.test(m) || /domain/i.test(svc)) {
    return `${name ? name + ', ' : ''}Domain tescili ve DNS yönlendirmesini F2F üzerinden yapabiliyoruz. Aklınızdaki alan adını yazar mısınız?`;
  }
  if (/e-?ticaret|woocommerce|mağaza/.test(m) || /e-ticaret/i.test(svc)) {
    return `${name ? name + ', ' : ''}E-ticaret kurulumunda ödeme, kargo ve stok entegrasyonlarını birlikte planlarız. Kaç ürünle başlayacaksınız?`;
  }
  if (/yazılım|özel|crm|erp/.test(m) || /yazılım/i.test(svc)) {
    return `${name ? name + ', ' : ''}Özel yazılım için kapsamı netleştirelim: web paneli, mobil uygulama veya otomasyon mu düşünüyorsunuz?`;
  }
  if (/web|site|oluştur/.test(m) || /web sitesi/i.test(svc)) {
    return `${name ? name + ', ' : ''}Web sitesi için kurumsal, landing veya blog hangisi daha yakın? Ayrıca kaç sayfa / dil ihtiyacınız var?`;
  }
  if (!OPENAI_KEY) {
    return `${name ? 'Merhaba ' + name + '! ' : ''}Demo modundayım. "${message}" için canlı OpenAI yanıtı almak üzere OPENAI_API_KEY tanımlayın. Canlı görüşme için alttaki WhatsApp butonunu kullanabilirsiniz.`;
  }
  return 'Nasıl yardımcı olabilirim?';
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
    throw new Error((data && data.error && data.error.message) || `OpenAI HTTP ${res.status}`);
  }
  const content = data?.choices?.[0]?.message?.content;
  if (!content) throw new Error('OpenAI boş yanıt.');
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

const leads = [];

const server = http.createServer(async (req, res) => {
  try {
    const url = new URL(req.url, `http://${req.headers.host || '127.0.0.1'}`);

    if (req.method === 'GET' && (url.pathname === '/' || url.pathname === '/index.html')) {
      serveFile(res, path.join(ROOT, 'public', 'index.html'));
      return;
    }
    if (req.method === 'GET' && url.pathname.startsWith('/plugin/')) {
      const rel = path.normalize(url.pathname.replace('/plugin/', '')).replace(/^(\.\.(\/|\\|$))+/, '');
      serveFile(res, path.join(PLUGIN_ASSETS, rel));
      return;
    }
    if (req.method === 'GET' && url.pathname.startsWith('/assets/')) {
      const rel = path.normalize(url.pathname.replace('/assets/', '')).replace(/^(\.\.(\/|\\|$))+/, '');
      serveFile(res, path.join(ROOT, 'public', 'assets', rel));
      return;
    }

    if (req.method === 'GET' && (url.pathname === '/api/config' || url.pathname === '/api/')) {
      sendJson(res, 200, {
        ...PUBLIC_CONFIG,
        mode: OPENAI_KEY ? 'openai' : 'demo',
        hasApiKey: Boolean(OPENAI_KEY),
      });
      return;
    }

    if (req.method === 'POST' && url.pathname === '/api/lead') {
      const raw = await readBody(req);
      let body = {};
      try {
        body = JSON.parse(raw || '{}');
      } catch {
        sendJson(res, 400, { message: 'Geçersiz JSON.' });
        return;
      }
      const first = String(body.first_name || '').trim();
      const last = String(body.last_name || '').trim();
      const phone = String(body.phone || '').trim();
      const email = String(body.email || '').trim();
      if (!first || !last) {
        sendJson(res, 400, { message: 'Ad ve soyad zorunludur.' });
        return;
      }
      if (phone.length < 7) {
        sendJson(res, 400, { message: 'Geçerli bir telefon girin.' });
        return;
      }
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        sendJson(res, 400, { message: 'Geçerli bir e-posta girin.' });
        return;
      }
      const lead = {
        id: leads.length + 1,
        first_name: first,
        last_name: last,
        phone,
        email,
        service: String(body.service || ''),
        intent: String(body.intent || ''),
        at: new Date().toISOString(),
      };
      leads.push(lead);
      console.log('[lead]', JSON.stringify(lead));
      let welcome = PUBLIC_CONFIG.chatWelcome;
      welcome = welcome
        .replace(/\{ad\}/g, first)
        .replace(/\{soyad\}/g, last)
        .replace(/\{hizmet\}/g, lead.service || 'proje');
      sendJson(res, 200, { ok: true, leadId: lead.id, welcome });
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
      const lead = body.lead && typeof body.lead === 'object' ? body.lead : null;
      let system = SYSTEM_PROMPT;
      if (lead) {
        system +=
          '\n\nZiyaretçi bilgileri:\n' +
          [
            lead.first_name && 'Ad: ' + lead.first_name,
            lead.last_name && 'Soyad: ' + lead.last_name,
            lead.phone && 'Telefon: ' + lead.phone,
            lead.email && 'E-posta: ' + lead.email,
            lead.service && 'Seçilen hizmet: ' + lead.service,
            lead.intent && 'İlk mesaj/niyet: ' + lead.intent,
          ]
            .filter(Boolean)
            .join('\n');
      }
      const messages = [{ role: 'system', content: system }];
      const history = Array.isArray(body.history) ? body.history.slice(-12) : [];
      for (const turn of history) {
        if (!turn || (turn.role !== 'user' && turn.role !== 'assistant')) continue;
        const content = String(turn.content || '').slice(0, 2000);
        if (content) messages.push({ role: turn.role, content });
      }
      messages.push({ role: 'user', content: message });

      try {
        let reply;
        if (OPENAI_KEY) {
          reply = await openaiChat(messages);
        } else {
          await new Promise((r) => setTimeout(r, 500));
          reply = mockReply(message, lead);
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
  console.log(`F2F AI Proje Ajanı demo → http://127.0.0.1:${PORT}`);
  console.log(OPENAI_KEY ? `OpenAI (${MODEL})` : 'Demo mode — set OPENAI_API_KEY for live replies');
});
