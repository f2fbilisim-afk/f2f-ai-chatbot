=== F2F AI Chatbot ===
Contributors: f2fbilisim
Tags: chatbot, openai, ai, widget, customer-support
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

OpenAI destekli floating chatbot widget. Site ziyaretçilerine anında yanıt verin.

== Description ==

F2F AI Chatbot, WordPress sitenize OpenAI bağlı bir sohbet balonu ekler. API anahtarı sunucuda saklanır; ziyaretçi tarayıcısına gönderilmez.

Özellikler:

* Floating widget (sağ/sol alt)
* Sistem promptu, karşılama mesajı, renk ve model ayarları
* REST API + nonce + IP rate limit

== Installation ==

1. `f2f-ai-chatbot` klasörünü `wp-content/plugins/` altına yükleyin.
2. Eklentiyi etkinleştirin.
3. Ayarlar → F2F AI Chatbot ekranından OpenAI API anahtarını girin.

== Frequently Asked Questions ==

= API anahtarı nereden alınır? =

https://platform.openai.com adresinden API key oluşturun.

= Widget görünmüyor =

Ayarlarda "Widget aktif" işaretli mi kontrol edin. Cache eklentisi varsa önbelleği temizleyin.

== Changelog ==

= 1.0.0 =
* İlk sürüm: OpenAI chat, admin ayarları, floating widget.
