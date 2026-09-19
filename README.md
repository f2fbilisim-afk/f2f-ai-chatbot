# F2F AI Chatbot

WordPress için **paketli lisanslı** AI chatbot (keşif → lead → sohbet + WhatsApp).

| Paket | Konuşma / yıl |
|-------|----------------|
| Starter | 1.000 |
| Business | 5.000 |
| Pro | 15.000 |

- Lisans alanı **boş** gelir; anahtar paketi + **1 yıl** açar (ilk aktivasyon kilitli — aynı anahtarı tekrar girmek uzatmaz)  
- OpenAI müşteri panelinde **yok** — `F2F_AI_MASTER_OPENAI_KEY` (sizin API)  
- **Lead e-posta bildirimi:** ziyaretçi chat bilgileri, müşterinin yazdığı adrese gider  
- **Otomatik güncelleme:** müşteri siteleri WordPress Eklentiler ekranından günceller  

Detay: [PRICING.md](./PRICING.md)

## Kurulum (müşteri)

1. Eski plugin klasörlerini silin (`f2f-ai-chatbot`, `f2f-ai-chatbot-2`, `f2f-ai-chatbot-8` …) — **tek kopya** kalsın  
2. ZIP yükle → etkinleştir  
3. **Ayarlar → Lisans** → F2F anahtarını yapıştır  
4. **E-posta bildirimi** adresini girin  
5. Hizmet kutuları + **Siteyi tara**  

## Kurulum (F2F)

Müşteri sitesinin `wp-config.php` dosyasına:

```php
define('F2F_AI_MASTER_OPENAI_KEY', 'sk-proj-...');
```

İsteğe bağlı model:

```php
define('F2F_AI_MODEL', 'gpt-4o-mini');
```

## Otomatik güncelleme (manuel ZIP yüklemeyi bitirir)

Her sürümde paketleyin:

```bash
bash tools/pack-plugin.sh
```

Çıktı:

| Dosya | Hosting’e koy |
|-------|----------------|
| `demo/public/download/f2f-ai-chatbot.zip` | `https://www.f2fbilisim.com/downloads/f2f-ai-chatbot.zip` |
| `demo/public/updates/f2f-ai-chatbot.json` | `https://www.f2fbilisim.com/updates/f2f-ai-chatbot.json` |

Müşteri sitelerindeki eklenti bu JSON’u kontrol eder; yeni sürüm varsa **Eklentiler → Güncelle** çıkar.

İsteğe bağlı (test / staging):

```php
define('F2F_AI_UPDATE_JSON', 'https://staging.ornek.com/updates/f2f-ai-chatbot.json');
```

Sunucuda `wp_mail` / SMTP çalışır olmalı (lead mailleri için).

## Anahtar üretimi

```bash
node tools/generate-licenses.mjs
# Gizli CSV: licenses/F2F-LICENSE-KEYS-PRIVATE.csv
```

## Demo

```bash
cd demo && npm start
# http://127.0.0.1:43145
# Update JSON: http://127.0.0.1:43145/updates/f2f-ai-chatbot.json
```
