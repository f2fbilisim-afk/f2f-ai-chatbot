# F2F AI Chatbot

WordPress için **paketli lisanslı** AI chatbot (keşif → lead → sohbet + WhatsApp).

| Paket | Konuşma / yıl |
|-------|----------------|
| Starter | 1.000 |
| Business | 5.000 |
| Pro | 15.000 |

- Lisans alanı **boş** gelir; anahtar paketi + **1 yıl** açar (ilk aktivasyon kilitli — aynı anahtarı tekrar girmek uzatmaz)  
- OpenAI müşteri panelinde **yok** — `F2F_AI_MASTER_OPENAI_KEY` (sizin API)  
- **Lead e-posta bildirimi:** panele düşen her lead + AI özeti, müşterinin girdiği adrese `noreply@f2fbilisim.com` üzerinden gider  

Detay: [PRICING.md](./PRICING.md)

## Kurulum (müşteri)

1. Eski plugin klasörlerini silin (`f2f-ai-chatbot`, `f2f-ai-chatbot-2`, `f2f-ai-chatbot-8` …) — **tek kopya** kalsın  
2. ZIP yükle → etkinleştir  
3. **Ayarlar → Lisans** → F2F anahtarını yapıştır  
4. Adım 5 / **E-posta bildirimi** adresini girin  
5. Hizmet kutuları + **Siteyi tara**  

## Kurulum (F2F)

Müşteri sitesinin `wp-config.php` dosyasına (FTP / hosting paneli):

```php
define('F2F_AI_MASTER_OPENAI_KEY', 'sk-proj-...');
```

Bu satır yoksa lisans çalışsa bile sohbet **“Platform sohbet hatası”** verir — çünkü henüz canlı bir `platform.f2fbilisim.com` proxy yok; yanıtlar doğrudan OpenAI master key ile üretilir.

İsteğe bağlı model:

```php
define('F2F_AI_MODEL', 'gpt-4o-mini');
```

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
