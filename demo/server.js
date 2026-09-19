/**
 * Demo: Anadolu Makina — proves sector-specific knowledge (not F2F hosting jargon).
 */

const http = require('http');
const fs = require('fs');
const path = require('path');
const { URL } = require('url');

(function loadEnvFile() {
  const envPath = path.join(__dirname, '.env');
  if (!fs.existsSync(envPath)) return;
  for (const line of fs.readFileSync(envPath, 'utf8').split(/\r?\n/)) {
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

const SITE = {
  name: 'Anadolu Makina',
  description: 'CNC torna, freze ve endüstriyel makina imalatı — Konya.',
  notes:
    'CNC torna, CNC freze, yedek parça ve teknik servis sunuyoruz. Hosting, domain, web sitesi veya gıda ürünü satmıyoruz.',
  url: 'https://ornek-anadolu-makina.local/',
};

const SITE_DOCS = [
  {
    id: 1,
    type: 'page',
    title: 'Hakkımızda',
    url: '/hakkimizda',
    content:
      'Anadolu Makina 1998’den beri CNC torna ve freze tezgahları üretmektedir. Konya OSB’de 4200 m² üretim alanı. ISO 9001 kalite sistemi.',
  },
  {
    id: 2,
    type: 'page',
    title: 'CNC Torna',
    url: '/urunler/cnc-torna',
    content:
      'CNC torna tezgahlarımız Ø20–Ø450 mm çap aralığında hassas talaş kaldırma yapar. Otomotiv ve savunma yan sanayi için uygundur. Standart teslimat 6–10 hafta.',
  },
  {
    id: 3,
    type: 'page',
    title: 'CNC Freze',
    url: '/urunler/cnc-freze',
    content:
      '3 ve 5 eksen CNC freze merkezleri. Alüminyum, çelik ve titanyum işleme. Takım yolu optimizasyonu ve fikstür tasarımı hizmeti verilir.',
  },
  {
    id: 4,
    type: 'page',
    title: 'Teknik Servis',
    url: '/servis',
    content:
      'Yerinde kurulum, periyodik bakım ve yedek parça. 7/24 arıza hattı: +90 332 000 00 00. Garantili makinelerde ilk yıl ücretsiz bakım.',
  },
  {
    id: 5,
    type: 'page',
    title: 'İletişim',
    url: '/iletisim',
    content:
      'Adres: Konya OSB 12. Cadde No:45. E-posta: info@anadolumakina.example Tel: +90 332 000 00 00.',
  },
];

const PUBLIC_CONFIG = {
  enabled: true,
  botName: 'Anadolu Makina Asistan',
  avatarUrl: '/plugin/img/avatar-default.svg',
  primaryColor: '#22C55E',
  position: 'right',
  bottomMargin: 4,
  sideMargin: 2,
  launcherLabel: 'AI',
  teaserTitle: 'ANADOLU MAKINA',
  teaserMessage: 'Merhaba! CNC torna, freze veya servis hakkında yazın.',
  showTeaser: true,
  discoverHeadline: 'Size nasıl yardımcı olabiliriz?',
  discoverSubtext:
    'Makina veya servis seçin, kısa iletişim bilgisinden sonra asistan yanıtlasın.',
  inputPlaceholder: 'Mesajınızı yazın...',
  dividerText: 'VEYA KEŞFET',
  featured: {
    id: 'featured',
    label: 'CNC Torna teklifi',
    subtitle: 'En çok sorulan',
    icon: 'star',
  },
  services: [
    { id: 'service_1', label: 'CNC Freze', icon: 'sparkle' },
    { id: 'service_2', label: 'Teknik Servis', icon: 'globe' },
    { id: 'service_3', label: 'Yedek Parça', icon: 'bag' },
    { id: 'service_4', label: 'Kurulum', icon: 'code' },
  ],
  lead: {
    headline: 'Sizi tanıyalım',
    subtext:
      'Satış ekibimizin dönüş yapabilmesi için iletişim bilgilerinizi alın. Ardından asistanla devam edeceğiz.',
    btn: 'Devam et',
    cancel: 'Vazgeç',
  },
  whatsapp: {
    phone: '903320000000',
    message: 'Merhaba, Anadolu Makina sitesinden yazıyorum.',
    label: 'Canlı Görüşmeye Başla',
  },
  chatWelcome: 'Merhaba {ad}! {hizmet} hakkında yardımcı olayım. Ne öğrenmek istersiniz?',
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

function searchDocs(query, limit = 5) {
  const q = String(query || '').toLowerCase();
  const tokens = q.split(/[\s\W]+/).filter((t) => t.length >= 3);
  const scored = SITE_DOCS.map((doc) => {
    const hay = (doc.title + ' ' + doc.content).toLowerCase();
    let score = 0;
    for (const t of tokens) if (hay.includes(t)) score += 2;
    return { score, doc };
  }).sort((a, b) => b.score - a.score);
  const out = scored.filter((r) => r.score > 0).slice(0, limit).map((r) => r.doc);
  if (out.length < 3) {
    for (const d of SITE_DOCS) {
      if (out.length >= limit) break;
      if (!out.find((x) => x.id === d.id)) out.push(d);
    }
  }
  return out;
}

function buildSystemPrompt(query, lead) {
  const services = [
    PUBLIC_CONFIG.featured.label,
    ...PUBLIC_CONFIG.services.map((s) => s.label),
  ].join(', ');
  const docs = searchDocs(
    [query, lead && lead.service, lead && lead.intent].filter(Boolean).join(' ')
  );
  let kb = `SİTE KİMLİĞİ\nAd: ${SITE.name}\nURL: ${SITE.url}\nAçıklama: ${SITE.description}\n\nİLGİLİ SİTE İÇERİKLERİ:\n`;
  for (const d of docs) {
    kb += `\n### ${d.title}\nURL: ${d.url}\n${d.content}\n`;
  }
  let leadBlock = '';
  if (lead && typeof lead === 'object') {
    leadBlock =
      '\nZiyaretçi:\n' +
      [
        lead.first_name && 'Ad: ' + lead.first_name,
        lead.last_name && 'Soyad: ' + lead.last_name,
        lead.phone && 'Telefon: ' + lead.phone,
        lead.email && 'E-posta: ' + lead.email,
        lead.service && 'Seçilen hizmet: ' + lead.service,
        lead.intent && 'İlk niyet: ' + lead.intent,
      ]
        .filter(Boolean)
        .join('\n');
  }
  return (
    `Sen ${SITE.name} web sitesinin yapay zeka asistanısın.\n` +
    `Site açıklaması: ${SITE.description}\n` +
    `İşletme notları: ${SITE.notes}\n` +
    `Hizmet/ürün etiketleri: ${services}\n\n` +
    `ZORUNLU KURALLAR:\n` +
    `- Yalnızca ${SITE.name} içeriğine göre yanıt ver.\n` +
    `- Hosting, domain, web tasarımı, e-ticaret yazılımı gibi bu sitede olmayan hizmetleri ASLA önerme.\n` +
    `- Bilmiyorsan söyle ve canlı görüşmeye yönlendir.\n` +
    `- Kısa, net Türkçe.\n\n` +
    `--- SİTE BİLGİ BANKASI ---\n${kb}\n--- BİLGİ BANKASI SONU ---` +
    leadBlock
  );
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
      temperature: 0.4,
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

function mockReply(message, lead) {
  const m = String(message || '').toLowerCase();
  const name = lead && lead.first_name ? lead.first_name + ', ' : '';
  if (/hosting|domain|wordpress|web site|e-?ticaret/.test(m)) {
    return `${name}Biz Anadolu Makina olarak CNC torna/freze üretiyoruz; hosting veya web hizmeti sunmuyoruz. Makina veya teknik servis hakkında sorabilirsiniz.`;
  }
  if (/torna|cnc/.test(m)) {
    return `${name}CNC torna tezgahlarımız Ø20–Ø450 mm aralığında. Standart teslimat 6–10 hafta. Hangi çap / malzeme için bakıyorsunuz?`;
  }
  if (/freze/.test(m)) {
    return `${name}3 ve 5 eksen CNC freze merkezlerimiz var. Alüminyum, çelik, titanyum işleriz. Parça tipinizi paylaşır mısınız?`;
  }
  if (/servis|bakım|arıza|yedek/.test(m)) {
    return `${name}Teknik servis ve yedek parça desteğimiz var. Arıza hattı: +90 332 000 00 00. Makina modelinizi yazarsanız yönlendireyim.`;
  }
  return `${name}Anadolu Makina asistanıyım — CNC torna, freze, kurulum ve servis konularında yardımcı olurum.`;
}

const leads = [];

const server = http.createServer(async (req, res) => {
  try {
    const url = new URL(req.url, `http://${req.headers.host || '127.0.0.1'}`);

    if (req.method === 'GET' && (url.pathname === '/' || url.pathname === '/index.html')) {
      fs.readFile(path.join(ROOT, 'public', 'index.html'), (err, data) => {
        if (err) {
          send(res, 404, 'Not found', { 'Content-Type': 'text/plain; charset=utf-8' });
          return;
        }
        send(res, 200, data, {
          'Content-Type': 'text/html; charset=utf-8',
          'Cache-Control': 'no-store, no-cache, must-revalidate',
          Pragma: 'no-cache',
        });
      });
      return;
    }
    if (req.method === 'GET' && (url.pathname === '/mail-ayarlari.html' || url.pathname === '/mail-ayarlari')) {
      fs.readFile(path.join(ROOT, 'public', 'mail-ayarlari.html'), (err, data) => {
        if (err) {
          send(res, 404, 'Not found', { 'Content-Type': 'text/plain; charset=utf-8' });
          return;
        }
        send(res, 200, data, {
          'Content-Type': 'text/html; charset=utf-8',
          'Cache-Control': 'no-store, no-cache, must-revalidate',
          Pragma: 'no-cache',
        });
      });
      return;
    }
    if (req.method === 'GET' && url.pathname.startsWith('/plugin/')) {
      const rel = path.normalize(url.pathname.replace('/plugin/', '')).replace(/^(\.\.(\/|\\|$))+/, '');
      serveFile(res, path.join(PLUGIN_ASSETS, rel));
      return;
    }
    if (
      (req.method === 'GET' || req.method === 'HEAD') &&
      (url.pathname.startsWith('/download/') || url.pathname === '/f2f-ai-chatbot.zip')
    ) {
      const rel =
        url.pathname === '/f2f-ai-chatbot.zip'
          ? 'f2f-ai-chatbot.zip'
          : path.normalize(url.pathname.replace('/download/', '')).replace(/^(\.\.(\/|\\|$))+/, '');
      const candidates = [
        path.join(ROOT, 'public', 'download', rel),
        path.join(ROOT, 'public', 'assets', rel),
        path.join('/opt/cursor/artifacts', path.basename(rel)),
      ];
      const filePath = candidates.find((p) => fs.existsSync(p) && fs.statSync(p).isFile());
      if (!filePath) {
        send(res, 404, 'Not found', { 'Content-Type': 'text/plain; charset=utf-8' });
        return;
      }
      const data = fs.readFileSync(filePath);
      const headers = {
        'Content-Type': 'application/zip',
        'Content-Disposition': `attachment; filename="${path.basename(rel)}"`,
        'Cache-Control': 'no-store, no-cache, must-revalidate',
        'Access-Control-Allow-Origin': '*',
        'Access-Control-Expose-Headers': 'Content-Disposition, Content-Length, Content-Type',
        'X-Content-Type-Options': 'nosniff',
      };
      if (req.method === 'HEAD') {
        res.writeHead(200, { 'Content-Length': data.length, ...headers });
        res.end();
        return;
      }
      send(res, 200, data, headers);
      return;
    }

    if (req.method === 'GET' && url.pathname.startsWith('/assets/')) {
      const rel = path.normalize(url.pathname.replace('/assets/', '')).replace(/^(\.\.(\/|\\|$))+/, '');
      const filePath = path.join(ROOT, 'public', 'assets', rel);
      if (path.extname(rel).toLowerCase() === '.zip' && fs.existsSync(filePath)) {
        const data = fs.readFileSync(filePath);
        send(res, 200, data, {
          'Content-Type': 'application/zip',
          'Content-Disposition': `attachment; filename="${path.basename(rel)}"`,
          'Cache-Control': 'no-store',
          'Access-Control-Allow-Origin': '*',
        });
        return;
      }
      serveFile(res, filePath);
      return;
    }

    if (req.method === 'GET' && (url.pathname === '/api/config' || url.pathname === '/api/')) {
      sendJson(res, 200, {
        ...PUBLIC_CONFIG,
        mode: OPENAI_KEY ? 'openai' : 'demo',
        hasApiKey: Boolean(OPENAI_KEY),
        siteName: SITE.name,
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
      };
      leads.push(lead);
      let welcome = PUBLIC_CONFIG.chatWelcome
        .replace(/\{ad\}/g, first)
        .replace(/\{soyad\}/g, last)
        .replace(/\{hizmet\}/g, lead.service || 'ürünlerimiz');
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
      const system = buildSystemPrompt(message, lead);
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
          await new Promise((r) => setTimeout(r, 400));
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
  console.log(`Anadolu Makina AI demo → http://127.0.0.1:${PORT}`);
  console.log(OPENAI_KEY ? `OpenAI (${MODEL})` : 'Demo mode');
});
