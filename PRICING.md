# F2F AI Chatbot — Satış & kontör modeli

## Neden müşteriye API key verilmez?

Müşteri kendi OpenAI anahtarını girerse:

- Token maliyeti **müşteriye** gider → siz komisyon alamazsınız  
- Model / limit kontrolü elinizden çıkar  
- Destek ve abuse yönetimi zorlaşır  

Doğru model: **WordPress eklentisi = istemci**, **OpenAI anahtarı = F2F platformu**.

## Mimari

```
Ziyaretçi → WP Plugin → platform.f2fbilisim.com (lisans + kontör düşümü) → OpenAI
```

Müşteri panelinde görünenler:

- Lisans anahtarı  
- Marka / hizmet kutuları / WhatsApp / site tarama  
- Kalan kontör (platformdan okunur)  

Görünmeyenler (F2F kontrolünde):

- OpenAI API key  
- Model, temperature, max tokens  

F2F kendi sitelerinde / geliştirmede: `wp-config.php` içine

```php
define('F2F_AI_MASTER_OPENAI_KEY', 'sk-...');
// isteğe bağlı:
define('F2F_AI_PLATFORM_URL', 'https://platform.f2fbilisim.com');
define('F2F_AI_MODEL', 'gpt-4o-mini');
```

## Önerilen ücret politikası

**1) Kurulum ücreti (tek sefer)**  
Eklenti kurulumu + ilk site tarama + hizmet kutusu ayarı.

**2) Aylık paket (önerilen ana gelir)**  

| Paket | Aylık mesaj hakkı (yaklaşık) | Kim için |
|-------|------------------------------|----------|
| Starter | 500–1.000 mesaj | Küçük site |
| Business | 3.000–5.000 mesaj | Orta trafik |
| Pro | 10.000+ mesaj | Yoğun / çok dil |

1 “mesaj” = ziyaretçi 1 soru + asistan 1 yanıt (veya platformda 1 completion).

**3) Kontör / top-up**  
Paket bitince: +500 / +2000 / +5000 mesaj paketleri. Soft limit: %90’da uyarı, %100’de sohbet “kontör bitti” der.

**4) Maliyet çarpanı**  
OpenAI maliyetinin **3–5×**’i satış fiyatı olarak sağlıklıdır (özetleme + bilgi bankası + destek payı).

Örnek (kabaca): gpt-4o-mini ile ortalama 1 tur ~$0.001–0.003 ise müşteriye 1 kontörü 0.05–0.15 TL bandında satmak (kur ve pakete göre ayarlanır) sürdürülebilir marj bırakır — kesin rakamı kendi OpenAI faturanıza göre netleştirin.

## Platformda tutulması gerekenler

- Lisans ↔ site URL bağlama (kopya koruması)  
- Kontör bakiyesi + her completion düşümü  
- Rate limit (IP + lisans)  
- Kullanım raporu (müşteri panelinde)  

## Eklenti tarafı (bu sürüm)

- Müşteri UI’dan API key / token / model kaldırıldı  
- `license_key` alanı + durum/kontör gösterimi  
- Sohbet `F2F_AI_Chatbot_Gateway` üzerinden platforma gider  
- Platform yoksa yalnızca `F2F_AI_MASTER_OPENAI_KEY` ile çalışır (sizin sunucunuz)
