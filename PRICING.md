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

## Fiyat önerisi (yüksek marj — 2026)

Maliyet varsayımı (`gpt-4o-mini` + site bilgisi): **~0,08–0,15 TL / konuşma**  
(≈ 0,002–0,004 USD; kur ~40 TL/$ — kendi faturanıza göre güncelleyin.)

| Paket | OpenAI maliyeti (yaklaşık) | **Liste fiyatı (KDV hariç)** | Brüt marj bandı |
|-------|----------------------------|------------------------------|-----------------|
| **Starter** (1.000) | 80–150 TL | **4.990 TL / yıl** | ~97% |
| **Business** (5.000) | 400–750 TL | **12.990 TL / yıl** | ~94–97% |
| **Pro** (15.000) | 1.200–2.250 TL | **29.990 TL / yıl** | ~92–96% |

**Önerilen satış seti (yüksek kar):**

| Kalem | Fiyat |
|-------|-------|
| Starter | **4.990 TL** |
| Business | **12.990 TL** (en çok satılacak “orta” paket) |
| Pro | **29.990 TL** |
| Kurulum / ayar hizmeti (tek sefer) | **2.490–4.990 TL** ekstra |
| Kota bitince +1.000 konuşma top-up | **1.490 TL** |
| Yıllık yenileme | Listenin **%80’i** (sadakat indirimi) |

### Neden bu rakamlar?

- Müşteri “chatbot + lead + WhatsApp + site tarama” alıyor; saf API değil.  
- Sizin riskiniz OpenAI + destek; fiyat yazılım + hizmet gibi konumlanmalı.  
- 5–10× API maliyeti hâlâ ucuz hissettirmez; **yüksek marj** için 15–40× bandı (yukarıdaki liste) daha doğru.  
- Business’ı “en mantıklısı” diye fiyatlandırın (anchor: Pro pahalı, Starter kısıtlı).

### Psikolojik paketleme

- Sitede **Business’ı “Popüler”** işaretleyin.  
- Starter’ı giriş / küçük site; Pro’yu ajans / e‑ticaret.  
- İlk 10 müşteriye kurulum dahil kampanya → sonra kurulum ayrı satılır.

### Dikkat

- Fiyata **KDV** ekleyin (liste + KDV).  
- Model’i `gpt-4o` gibi pahalıya çekerseniz kotayı düşürün veya fiyatı **×1,5–2** yapın.  
- Çok ucuza satmayın (2.000 TL altı Starter) — marj erir, destek aynı kalır.

