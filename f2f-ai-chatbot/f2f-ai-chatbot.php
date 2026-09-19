<?php
/**
 * Plugin Name:       F2F AI Chatbot
 * Plugin URI:        https://www.f2fbilisim.com
 * Description:       AI Proje Ajanı — keşif ekranı, lead formu ve OpenAI sohbeti. WhatsApp canlı görüşme.
 * Version:           1.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            F2F Bilişim
 * Author URI:        https://www.f2fbilisim.com
 * License:           GPL-2.0-or-later
 * Text Domain:       f2f-ai-chatbot
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'F2F_AI_CHATBOT_VERSION', '1.1.0' );
define( 'F2F_AI_CHATBOT_FILE', __FILE__ );
define( 'F2F_AI_CHATBOT_PATH', plugin_dir_path( __FILE__ ) );
define( 'F2F_AI_CHATBOT_URL', plugin_dir_url( __FILE__ ) );

require_once F2F_AI_CHATBOT_PATH . 'includes/class-openai.php';
require_once F2F_AI_CHATBOT_PATH . 'includes/class-leads.php';
require_once F2F_AI_CHATBOT_PATH . 'includes/class-admin.php';
require_once F2F_AI_CHATBOT_PATH . 'includes/class-rest-api.php';
require_once F2F_AI_CHATBOT_PATH . 'includes/class-frontend.php';

/**
 * Bootstrap.
 */
function f2f_ai_chatbot_init() {
	load_plugin_textdomain( 'f2f-ai-chatbot', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	F2F_AI_Chatbot_Leads::instance();
	F2F_AI_Chatbot_Admin::instance();
	F2F_AI_Chatbot_REST_API::instance();
	F2F_AI_Chatbot_Frontend::instance();
}
add_action( 'plugins_loaded', 'f2f_ai_chatbot_init' );

/**
 * Default settings matching F2F AI Proje Ajanı UI.
 *
 * @return array<string, mixed>
 */
function f2f_ai_chatbot_default_settings() {
	return array(
		'enabled'              => '1',
		'api_key'              => '',
		'model'                => 'gpt-4o-mini',
		'system_prompt'        => "Sen F2F Bilişim'in AI Proje Ajanısın. Ziyaretçi bir hizmet seçti ve iletişim bilgilerini verdi. Nazik, net ve kısa Türkçe yanıtlar ver. Web sitesi, hosting, domain, e-ticaret ve yazılım projelerini netleştirmeye yardımcı ol. Bilmediğin konularda spekülasyon yapma; canlı görüşme için WhatsApp'a yönlendir.",
		'max_tokens'           => 500,
		'temperature'          => 0.7,
		'rate_limit'           => 30,
		'position'             => 'right',
		'bottom_margin'        => 4,
		'side_margin'          => 2,
		'primary_color'        => '#22C55E',
		'bot_name'             => 'AI Proje Ajanı',
		'launcher_label'       => 'AI',
		'teaser_title'         => 'AI PROJE AJANI',
		'teaser_message'       => 'Merhaba! Fikrinizi anlatın, birlikte netleştirelim.',
		'show_teaser'          => '1',
		'avatar_url'           => '',
		'avatar_id'            => 0,
		'discover_headline'    => 'Ne oluşturmak istiyorsunuz?',
		'discover_subtext'     => 'Ben F2F AI Proje Ajanı. Seçimden sonra kısa iletişim bilgisi alıp projenizi netleştirelim.',
		'input_placeholder'    => "AI Proje Ajanı'na yazın...",
		'featured_label'       => 'Web sitesi oluştur',
		'featured_subtitle'    => 'En popüler · ~2 dk',
		'featured_icon'        => 'layout',
		'service_1_label'      => 'Hosting al',
		'service_1_icon'       => 'sparkle',
		'service_2_label'      => 'Domain bul',
		'service_2_icon'       => 'globe',
		'service_3_label'      => 'E-ticaret',
		'service_3_icon'       => 'bag',
		'service_4_label'      => 'Yazılım',
		'service_4_icon'       => 'code',
		'divider_text'         => 'VEYA KEŞFET',
		'lead_headline'        => 'Sizi tanıyalım',
		'lead_subtext'         => 'Satış ekibimizin dönüş yapabilmesi için iletişim bilgilerinizi alın. Ardından ajanla devam edeceğiz.',
		'lead_btn'             => 'Devam et',
		'lead_cancel'          => 'Vazgeç',
		'whatsapp_phone'       => '905499009310',
		'whatsapp_message'     => 'Merhaba, AI Proje Ajanı üzerinden yazıyorum.',
		'whatsapp_btn_label'   => 'Canlı Görüşmeye Başla',
		'chat_welcome'         => 'Merhaba {ad}! {hizmet} konusunda yardımcı olayım. Projenizi kısaca anlatır mısınız?',
	);
}

/**
 * Merged settings.
 *
 * @return array<string, mixed>
 */
function f2f_ai_chatbot_get_settings() {
	$saved = get_option( 'f2f_ai_chatbot_settings', array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}
	return wp_parse_args( $saved, f2f_ai_chatbot_default_settings() );
}

/**
 * Resolve avatar URL (media library or custom URL or bundled default).
 *
 * @param array<string, mixed> $settings Settings.
 * @return string
 */
function f2f_ai_chatbot_avatar_url( $settings = null ) {
	if ( null === $settings ) {
		$settings = f2f_ai_chatbot_get_settings();
	}
	$id = isset( $settings['avatar_id'] ) ? absint( $settings['avatar_id'] ) : 0;
	if ( $id ) {
		$url = wp_get_attachment_image_url( $id, 'thumbnail' );
		if ( $url ) {
			return $url;
		}
	}
	if ( ! empty( $settings['avatar_url'] ) ) {
		return esc_url_raw( $settings['avatar_url'] );
	}
	return F2F_AI_CHATBOT_URL . 'assets/img/avatar-default.svg';
}

/**
 * Public widget config (no secrets).
 *
 * @return array<string, mixed>
 */
function f2f_ai_chatbot_public_config() {
	$s = f2f_ai_chatbot_get_settings();
	return array(
		'enabled'           => ( '1' === (string) $s['enabled'] ),
		'botName'           => $s['bot_name'],
		'avatarUrl'         => f2f_ai_chatbot_avatar_url( $s ),
		'primaryColor'      => $s['primary_color'],
		'position'          => $s['position'],
		'bottomMargin'      => (float) $s['bottom_margin'],
		'sideMargin'        => (float) $s['side_margin'],
		'launcherLabel'     => $s['launcher_label'],
		'teaserTitle'       => $s['teaser_title'],
		'teaserMessage'     => $s['teaser_message'],
		'showTeaser'        => ( '1' === (string) $s['show_teaser'] ),
		'discoverHeadline'  => $s['discover_headline'],
		'discoverSubtext'   => $s['discover_subtext'],
		'inputPlaceholder'  => $s['input_placeholder'],
		'dividerText'       => $s['divider_text'],
		'featured'          => array(
			'label'    => $s['featured_label'],
			'subtitle' => $s['featured_subtitle'],
			'icon'     => $s['featured_icon'],
			'id'       => 'featured',
		),
		'services'          => array(
			array(
				'id'    => 'service_1',
				'label' => $s['service_1_label'],
				'icon'  => $s['service_1_icon'],
			),
			array(
				'id'    => 'service_2',
				'label' => $s['service_2_label'],
				'icon'  => $s['service_2_icon'],
			),
			array(
				'id'    => 'service_3',
				'label' => $s['service_3_label'],
				'icon'  => $s['service_3_icon'],
			),
			array(
				'id'    => 'service_4',
				'label' => $s['service_4_label'],
				'icon'  => $s['service_4_icon'],
			),
		),
		'lead'              => array(
			'headline' => $s['lead_headline'],
			'subtext'  => $s['lead_subtext'],
			'btn'      => $s['lead_btn'],
			'cancel'   => $s['lead_cancel'],
		),
		'whatsapp'          => array(
			'phone'   => preg_replace( '/\D+/', '', (string) $s['whatsapp_phone'] ),
			'message' => $s['whatsapp_message'],
			'label'   => $s['whatsapp_btn_label'],
		),
		'chatWelcome'       => $s['chat_welcome'],
	);
}

/**
 * Activation.
 */
function f2f_ai_chatbot_activate() {
	if ( false === get_option( 'f2f_ai_chatbot_settings', false ) ) {
		add_option( 'f2f_ai_chatbot_settings', f2f_ai_chatbot_default_settings() );
	}
	F2F_AI_Chatbot_Leads::register_post_type();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'f2f_ai_chatbot_activate' );

/**
 * Deactivation.
 */
function f2f_ai_chatbot_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'f2f_ai_chatbot_deactivate' );
