# F2F AI Chatbot — Lisans, paket & satış

## Model

1. **Plugin ZIP ücretli** satılır.  
2. Kurulumda **lisans alanı boş** gelir.  
3. Satın alana havuzdan **paketli bir anahtar** verilir.  
4. Anahtar → **1 yıl + konuşma kotası**.  
5. OpenAI **sizin developer API’niz**; bakiye bitince OpenAI’ye top-up yaparsınız.

```
Ziyaretçi → WP Plugin (süre + kota?) → F2F_AI_MASTER_OPENAI_KEY → OpenAI
```

## Paketler (anahtara gömülü)

| Paket | Konuşma hakkı / yıl | Anahtar adedi (1000) | Kim için |
|-------|---------------------|----------------------|----------|
| **Starter** | **1.000** | 600 | Küçük site |
| **Business** | **5.000** | 300 | Orta trafik |
| **Pro** | **15.000** | 100 | Yoğun site |

1 konuşma = ziyaretçi 1 mesaj + asistan 1 yanıt (başarılı completion).

Süre dolunca veya kota bitince sohbet durur; yeni anahtar / üst paket / yenileme satarsınız.

## Neden süre + kota?

- Sadece 1 yıl → bir site OpenAI bakiyenizi eritebilir  
- Sadece kota → süresi bitmeyen “bedava” kullanım hissi  
- **İkisi birlikte** maliyeti ve yenileme gelirini dengeler  

## 1000 anahtar

```bash
node tools/generate-licenses.mjs
```

| Dosya | Kim? |
|-------|------|
| `licenses/F2F-LICENSE-KEYS-PRIVATE.csv` | Sadece siz (`plan`, `messages_limit` sütunları) |
| `f2f-ai-chatbot/includes/license-pool.php` | Eklentide (hash → plan) |

CSV: `index,license_key,plan,messages_limit,status,sold_to,sold_at,notes`

## Müşteri paneli

- Lisans anahtarı (boş)  
- Paket adı + kalan konuşma + süre  
- Kota dolunca `EXHAUSTED`  

## Sizin kurulum

```php
// wp-config.php
define('F2F_AI_MASTER_OPENAI_KEY', 'sk-proj-...');
define('F2F_AI_MODEL', 'gpt-4o-mini');
// define('F2F_AI_ALLOW_MASTER_WITHOUT_LICENSE', true); // sadece kendi test siteniz
```

## Fiyat önerisi

| Kalem | Not |
|-------|-----|
| Starter / Business / Pro | ZIP + 1 yıl + kota |
| Yenileme | Süre veya kota bitince aynı paketten yeni anahtar |
| Upgrade | Business/Pro anahtarı verin (eskiyi `sold` bırakın) |
| Kurulum hizmeti | İsteğe bağlı |

OpenAI maliyetinin **3–5×**’ini paket fiyatına gömün.
