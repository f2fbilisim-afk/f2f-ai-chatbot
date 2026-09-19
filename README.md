# F2F AI Chatbot

WordPress müşteri siteleri için **lisanslı** AI chatbot.

OpenAI anahtarı müşteri panelinde **yoktur**. Sohbet `platform.f2fbilisim.com` üzerinden gider; kontör F2F’te düşülür.

Detaylı satış modeli: [PRICING.md](./PRICING.md)

## Müşteri ne ayarlar?

- Lisans anahtarı  
- Profil, renk, konum, 4 hizmet kutusu  
- Sektör notları + site tarama  
- WhatsApp  

## Müşteri ne ayarlayamaz?

- OpenAI API key  
- Model / temperature / max tokens  

## Kurulum

1. `f2f-ai-chatbot.zip` yükle → etkinleştir  
2. **AI Chat Bot → Ayarlar** → lisans anahtarı  
3. Hizmet kutularını sektöre göre doldur → **Siteyi tara**  

F2F geliştirme (master key):

```php
// wp-config.php
define('F2F_AI_MASTER_OPENAI_KEY', 'sk-...');
```

## Demo

```bash
cd demo && npm start
# http://127.0.0.1:43145
# Zip: http://127.0.0.1:43145/download/f2f-ai-chatbot.zip
```
