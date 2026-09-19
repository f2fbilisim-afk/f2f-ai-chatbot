# F2F AI Chatbot — Lisans & satış modeli

## Fikir (uygulanan)

1. **Plugin ZIP ücretli** satılır.  
2. Kurulumda **Lisans anahtarı alanı boş** gelir.  
3. Satın alana 1000’lik havuzdan **bir anahtar** verilir.  
4. Anahtar eşleşirse site **1 yıl Premium** olur.  
5. OpenAI **sizin developer proje API’niz** ile çalışır; bakiyeniz bitince OpenAI’ye kontör yüklersiniz. Müşteri paneline API key **konmaz**.

```
Ziyaretçi → WP Plugin (premium lisans?) → F2F_AI_MASTER_OPENAI_KEY (wp-config)
                                         → OpenAI (sizin hesabınız)
```

## 1000 lisans anahtarı

Üretim:

```bash
node tools/generate-licenses.mjs
```

Çıktılar:

| Dosya | Kim görür? |
|-------|------------|
| `licenses/F2F-LICENSE-KEYS-PRIVATE.csv` | **Sadece siz** — satılacak anahtar listesi |
| `f2f-ai-chatbot/includes/license-pool.php` | Eklentide (yalnızca SHA-256 hash’ler) |

CSV sütunları: `index, license_key, status, sold_to, sold_at, notes`  
Satışta `status=sold` yapıp müşteri adını yazın; aynı anahtarı iki kez vermeyin.

Format: `F2F-XXXX-XXXX-XXXX-XXXX`

## Müşteri paneli

- **Lisans anahtarı** — boş placeholder  
- Durum: `MISSING` / `INVALID` / `PREMIUM` / `EXPIRED`  
- Premium’da kalan gün sayısı  

OpenAI / model / token alanları **yok**.

## Sizin kurulum (müşteri sitesi)

`wp-config.php`:

```php
define('F2F_AI_MASTER_OPENAI_KEY', 'sk-proj-...'); // OpenAI developer proje API
define('F2F_AI_MODEL', 'gpt-4o-mini'); // isteğe bağlı

// Kendi test sitenizde lisans olmadan denemek için:
// define('F2F_AI_ALLOW_MASTER_WITHOUT_LICENSE', true);
```

Akış:

1. Müşteriye ZIP + 1 satır anahtar verin  
2. Eklentiyi kurun, master key’i wp-config’e yazın  
3. Müşteri Ayarlar’a anahtarı yapıştırır → **Premium 365 gün**  
4. Sohbet sizin OpenAI bakiyenizden düşer  

## Ücret önerisi

| Kalem | Ne için |
|-------|---------|
| Plugin + 1 yıl lisans | Tek sefer satış (ZIP + anahtar) |
| Yıllık yenileme | Süre bitince yeni anahtar veya aynı anahtarı yeniden aktive (süresi dolmuşsa +365 gün) |
| Kurulum hizmeti | İsteğe bağlı (wp-config + tarama + kutular) |

OpenAI maliyetini (ortalama mesaj × fiyat) satış fiyatına **3–5×** gömün; bakiye azaldıkça developer hesabına top-up yapın.

## Güvenlik notu

- Eklentide **düz metin anahtar yok** — sadece hash.  
- CSV’yi asla ZIP’e / public repo’ya koymayın (`licenses/` gitignore’da).  
- İleride isterseniz `platform.f2fbilisim.com` ile site URL bağlama + uzaktan iptal eklenebilir.
