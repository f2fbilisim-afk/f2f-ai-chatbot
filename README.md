# F2F AI Chatbot

WordPress için **paketli lisanslı** AI chatbot (keşif → lead → sohbet + WhatsApp).

| Paket | Konuşma / yıl |
|-------|----------------|
| Starter | 1.000 |
| Business | 5.000 |
| Pro | 15.000 |

- Lisans alanı **boş** gelir; anahtar paketi + **1 yıl** açar  
- OpenAI müşteri panelinde **yok** — `F2F_AI_MASTER_OPENAI_KEY` (sizin API)  
- **Lead e-posta bildirimi:** panele düşen her lead + AI özeti, müşterinin girdiği adrese `noreply@f2fbilisim.com` üzerinden gider  

Detay: [PRICING.md](./PRICING.md)

## Kurulum (müşteri)

1. ZIP yükle → etkinleştir  
2. **Ayarlar → Lisans** → F2F anahtarını yapıştır  
3. Adım 5’te **bildirim e-postası** girin (boşsa WP yönetici maili)  
4. Hizmet kutuları + **Siteyi tara**  

## Kurulum (F2F)

```php
define('F2F_AI_MASTER_OPENAI_KEY', 'sk-proj-...');
```

Sunucuda `wp_mail` / SMTP çalışır olmalı; From adresi `noreply@f2fbilisim.com` (SPF/DKIM önerilir).

## Anahtar üretimi

```bash
node tools/generate-licenses.mjs
# Gizli CSV: licenses/F2F-LICENSE-KEYS-PRIVATE.csv
```

## Demo

```bash
cd demo && npm start
# http://127.0.0.1:43145
```
