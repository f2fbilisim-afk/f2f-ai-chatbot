# F2F AI Chatbot

WordPress için **lisanslı** AI chatbot (keşif → lead formu → sohbet + WhatsApp).

- Lisans alanı **boş** gelir; geçerli anahtar → **1 yıl Premium**  
- OpenAI anahtarı müşteri panelinde **yok** — sizin developer API’niz (`wp-config`)  
- Site içeriği taranır; sektör kutuları panelden ayarlanır  

Satış modeli: [PRICING.md](./PRICING.md)

## Kurulum (müşteri)

1. `f2f-ai-chatbot.zip` yükle → etkinleştir  
2. **Ayarlar → F2F AI Chatbot Lisans** → satın aldığınız anahtarı yapıştırın  
3. Hizmet kutularını doldurun → **Siteyi tara**  

## Kurulum (F2F / sizin OpenAI’niz)

```php
// wp-config.php
define('F2F_AI_MASTER_OPENAI_KEY', 'sk-proj-...');
```

## 1000 lisans üretimi

```bash
node tools/generate-licenses.mjs
```

- Özel liste: `licenses/F2F-LICENSE-KEYS-PRIVATE.csv` (**paylaşmayın**)  
- Hash havuzu eklentiye gömülü: `includes/license-pool.php`

## Demo

```bash
cd demo && npm start
# http://127.0.0.1:43145
# Zip: http://127.0.0.1:43145/download/f2f-ai-chatbot.zip
```
