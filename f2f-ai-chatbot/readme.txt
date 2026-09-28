=== F2F AI Chatbot ===
Contributors: f2fbilisim
Tags: chatbot, openai, ai, widget, customer-support, lead
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.9.9
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

F2F lisanslı AI chatbot — keşif, lead formu, sohbet, WhatsApp, e-posta bildirimi ve otomatik güncelleme.

== Description ==

F2F AI Chatbot, WordPress sitenize paket lisanslı bir sohbet balonu ekler. OpenAI anahtarı müşteri panelinde yoktur.

Özellikler:

* Floating widget (keşif → lead → sohbet + WhatsApp)
* Lisans + konuşma kotası (Starter / Business / Pro)
* Konuşmalar paneli — sol menü: F2F AI Chatbot
* Lead e-posta bildirimi
* Lisans süresi ilk aktivasyona kilitli
* F2F sunucusundan otomatik güncelleme

== Installation ==

1. Eski f2f-ai-chatbot kopyalarını silin (tek klasör).
2. ZIP yükleyip etkinleştirin.
3. Lisans + e-posta bildirimini kaydedin; siteyi tarayın.
4. Sonraki sürümler: Eklentiler → Güncelle (F2F update sunucusu).

== Changelog ==

= 1.9.9 =
* SMTP 550 fix: Easy WP SMTP hesabıyla From eşleşir; noreply@ zorlaması kaldırıldı.

= 1.9.8 =
* Mobil widget tam ekran sheet; 4 hizmet kartı tek ekranda, FAB açıkken gizlenir.

= 1.9.7 =
* Otomatik güncelleme artık F2F hub’dan (f2fbilisim.com) okunur; GitHub gecikmesi kalkar.
* Hub /update + /plugin-zip endpoint’leri.

= 1.9.6 =
* Konuşma mailleri F2F hub üzerinden iletilir (müşteri hosting wp_mail düşse bile).
* Özet ~60 sn + sekme kapanınca tetiklenir; son mail durumu panelde görünür.

= 1.9.5 =
* Bilgi bankası WooCommerce ürün kategorilerini ve panel hizmetlerini indeksler.
* Panelde seçilen hizmet için “sitede bilgi yok” demeyi engeller; Türkçe arama iyileştirildi.

= 1.9.4 =
* Bildirim mailleri sitenin kendi alan adından gönderilir (yabancı From reddi giderildi).
* Konuşma kaydı özet mailine eklenir; test maili butonu.
* WP-Cron yavaşsa gecikmiş özetler sayfa yüklemesinde işlenir.

= 1.8.1 =
* Sohbet “Bağlantı kurulamadı” hatasında sunucu/JSON hatalarını daha net göster.

= 1.8.0 =
* Otomatik güncelleme: F2F update JSON + WordPress Eklentiler ekranı.

= 1.7.10 =
* Müşteri panelinden master OpenAI uyarı kutusu kaldırıldı.

= 1.7.9 =
* Lead e-posta ayarı metni sadeleştirildi (noreply UI’dan çıkarıldı).

= 1.7.7 =
* Lisans süresi / kota ilk aktivasyona kilitlendi.

= 1.0.0 =
* İlk sürüm.
