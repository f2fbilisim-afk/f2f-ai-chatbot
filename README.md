# F2F AI Chatbot

WordPress siteleri için **OpenAI bağlı floating chatbot widget** eklentisi.  
`platform.f2fbilisim.com` tarzı kurumsal asistan deneyimini herhangi bir WordPress sitesine ekler.

## Özellikler

- Floating sohbet balonu (sağ/sol alt)
- OpenAI Chat Completions (`gpt-4o-mini` varsayılan)
- Admin paneli: API anahtarı, model, sistem promptu, karşılama mesajı, renk, rate limit
- API anahtarı yalnızca sunucuda saklanır (frontend’e çıkmaz)
- REST endpoint + WP nonce + IP başına saatlik istek limiti
- Türkçe arayüz (F2F Bilişim varsayılan metinleri)

## WordPress kurulumu

1. `f2f-ai-chatbot/` klasörünü `wp-content/plugins/f2f-ai-chatbot/` olarak kopyalayın  
   (veya klasörü zipleyip **Eklentiler → Yeni ekle → Yükle** ile yükleyin).
2. **Eklentiler** ekranından **F2F AI Chatbot**’u etkinleştirin.
3. **Ayarlar → F2F AI Chatbot** sayfasından OpenAI API anahtarınızı girin.
4. Sistem promptunu markanıza göre düzenleyin; kaydedin.
5. Widget tüm public sayfalarda otomatik görünür.

### REST uçları

| Method | Path | Açıklama |
|--------|------|----------|
| `GET`  | `/wp-json/f2f-ai-chatbot/v1/config` | Public config (secret yok) |
| `POST` | `/wp-json/f2f-ai-chatbot/v1/chat`   | Sohbet turu (`message`, `history`) |

`POST /chat` için `X-WP-Nonce` (wp_rest) zorunludur.

## Yerel widget demosu

Bu ortamda WordPress yok; aynı widget arayüzünü Node ile önizleyebilirsiniz:

```bash
cd demo
npm start
```

Tarayıcı: [http://127.0.0.1:43145](http://127.0.0.1:43145)

Canlı OpenAI yanıtları için:

```bash
export OPENAI_API_KEY=sk-...
export OPENAI_MODEL=gpt-4o-mini   # isteğe bağlı
npm start
```

Anahtar yoksa demo, F2F hizmetlerine uygun mock yanıtlar döner.

## Dizin yapısı

```
f2f-ai-chatbot/          ← WordPress eklentisi (zip/upload)
  f2f-ai-chatbot.php
  includes/
  assets/
demo/                    ← Önizleme sunucusu
  server.js
  public/
```

## Güvenlik notları

- API anahtarını asla tema / JS içine yazmayın.
- Rate limit varsayılanı: IP başına 20 istek / saat (ayarlardan değiştirilebilir).
- Üretimde mümkünse ek WAF / Cloudflare rate limit kullanın.

## Lisans

GPL-2.0-or-later (WordPress eklenti uyumu)
