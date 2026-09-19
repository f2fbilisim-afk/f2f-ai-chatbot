# F2F AI Chatbot — AI Proje Ajanı

WordPress floating chatbot: **keşif ekranı → lead formu → OpenAI sohbeti**, WhatsApp canlı görüşme butonu.

Ekranlar, gönderdiğiniz mockup’larla hizalıdır. Profil fotoğrafı, başlık, öne çıkan kutu ve 4 hizmet kutusu eklenti panelinden düzenlenir.

## Akış

1. Ziyaretçi widget’ı açar → hizmet seçer **veya** yazıp Enter’a basar  
2. **Sizi tanıyalım** formu (Ad, Soyad, Telefon, E-posta)  
3. Formdan sonra OpenAI sohbeti başlar  
4. Sohbet sırasında gönder çubuğunun altında **Canlı Görüşmeye Başla** → paneldeki WhatsApp numarasına yönlendirir  

## WordPress kurulumu

1. `f2f-ai-chatbot/` klasörünü `wp-content/plugins/` altına kopyalayın (veya zip yükleyin)  
2. Etkinleştirin → **Ayarlar → F2F AI Chatbot**  
3. Ayarlayın:
   - Profil fotoğrafı + chatbot başlığı  
   - Keşif metinleri, öne çıkan kutu, 4 hizmet  
   - Lead formu metinleri  
   - WhatsApp telefon (örn. `905499009310`)  
   - OpenAI API anahtarı + sistem promptu  
4. Lead’ler **Ayarlar → AI Leadler** altında listelenir  

## Demo (bu ortam)

```bash
cd demo
npm start
# http://127.0.0.1:43145
```

Canlı OpenAI:

```bash
export OPENAI_API_KEY=sk-...
npm start
```

## REST

| Method | Path | Açıklama |
|--------|------|----------|
| GET | `/wp-json/f2f-ai-chatbot/v1/config` | Public config |
| POST | `/wp-json/f2f-ai-chatbot/v1/lead` | Lead kaydı |
| POST | `/wp-json/f2f-ai-chatbot/v1/chat` | OpenAI sohbet |

## Lisans

GPL-2.0-or-later
