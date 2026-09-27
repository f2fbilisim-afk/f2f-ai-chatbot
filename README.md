# F2F AI Chatbot

WordPress için **paketli lisanslı** AI chatbot (keşif → lead → sohbet + WhatsApp).

| Paket | Konuşma / yıl |
|-------|----------------|
| Starter | 1.000 |
| Business | 5.000 |
| Pro | 15.000 |

- Lisans + **1 yıl** (ilk aktivasyon kilitli)  
- OpenAI müşteri panelinde **yok** — `F2F_AI_MASTER_OPENAI_KEY`  
- Lead e-posta bildirimi  
- **Otomatik güncelleme** — GitHub Releases  

Detay: [PRICING.md](./PRICING.md)

## Kurulum (müşteri)

1. Tek plugin klasörü bırakın  
2. ZIP yükle → etkinleştir  
3. Lisans + e-posta bildirimi  
4. Siteyi tara  

Müşteri sitesine **OpenAI / wp-config anahtarı yok**. Sohbet F2F hub’a (`f2fbilisim.com`) gider.

## Kurulum (F2F hub — anahtar yalnız burada)

`f2fbilisim.com` (veya hub siten) `wp-config.php`:

```php
define('F2F_AI_MASTER_OPENAI_KEY', 'sk-proj-...');
// Hub otomatik açılır (master key varsa). Kapatmak için:
// define('F2F_AI_PLATFORM_MODE', false);

// Müşteri siteleri başka bir hub kullanacaksa (onlarda):
// define('F2F_AI_PLATFORM_URL', 'https://www.f2fbilisim.com');
```

Hub endpoint: `https://www.f2fbilisim.com/wp-json/f2f-ai-platform/v1/ping`  
( “has_key”: true olmalı )
## Otomatik güncelleme (GitHub)

1. Bu repoyu **public** GitHub’a bağlayın (`f2fbilisim-afk/f2f-ai-chatbot` veya kendi adınız).  
2. Yeni sürüm yayınlamak için:

```bash
# sürüm numarasını f2f-ai-chatbot.php içinde yükseltin, commit edin, sonra:
git tag v1.8.2
git push origin v1.8.2
```

veya GitHub → Actions → **Release plugin update** → Run workflow.

3. Actions ZIP’i Release’e koyar + `updates/f2f-ai-chatbot.json` günceller.  
4. Müşteri siteleri WordPress **Eklentiler → Güncelle** görür.

Manifest varsayılan URL:

`https://raw.githubusercontent.com/f2fbilisim-afk/f2f-ai-chatbot/main/updates/f2f-ai-chatbot.json`

Manuel paket (FTP için):

```bash
bash tools/pack-plugin.sh
```

## Anahtar üretimi

```bash
node tools/generate-licenses.mjs
```

## Demo

```bash
cd demo && npm start
# http://127.0.0.1:43145
```
