# F2F AI Chatbot

WordPress müşteri siteleri için sektöre özel AI chatbot.

Her firmada jargon **panelden** ayarlanır. Eklenti yayındaki sayfa/yazıları (ve varsa WooCommerce ürünlerini) tarar; OpenAI yanıtlarını **yalnızca o site içeriğine** dayandırır.

## Neden önemli?

Makina firmasına “domain / hosting” demesin diye: aktivasyonda veya **Siteyi şimdi tara** ile bilgi bankası oluşur. Sohbette ilgili sayfalar seçilir ve sistem promptuna eklenir.

## Akış

1. Keşif (özelleştirilebilir 4 hizmet + öne çıkan)  
2. Lead formu (Ad, Soyad, Telefon, E-posta)  
3. OpenAI sohbeti (site bilgisi + işletme notları)  
4. **Canlı Görüşmeye Başla** → WhatsApp  

## WordPress kurulumu

1. `f2f-ai-chatbot/` → `wp-content/plugins/`  
2. Etkinleştir (ilk taramayı otomatik dener)  
3. **Ayarlar → F2F AI Chatbot**  
   - Hizmet kutularını müşteri sektörüne göre yazın  
   - **İşletme / sektör notları** doldurun  
   - **Siteyi şimdi tara**  
   - OpenAI API key + WhatsApp numarası  

## Demo (makina firması örneği)

```bash
cd demo
npm start
# http://127.0.0.1:43145
```

Demo site: **Anadolu Makina** (CNC torna/freze). Hosting sorunca reddeder.

## Lisans

GPL-2.0-or-later
