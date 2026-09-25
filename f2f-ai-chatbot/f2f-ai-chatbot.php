<?php
/**
 * Plugin Name:       F2F AI Chatbot
 * Plugin URI:        https://www.f2fbilisim.com
 * Description:       F2F lisanslı AI chatbot — Starter/Business/Pro paket + 1 yıl. OpenAI anahtarı müşteri panelinde yoktur.
 * Version:           1.8.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            F2F Bilişim
 * Author URI:        https://www.f2fbilisim.com
 * License:           GPL-2.0-or-later
 * Text Domain:       f2f-ai-chatbot
 * Domain Path:       /languages
 * Update URI:        https://www.f2fbilisim.com/updates/f2f-ai-chatbot.json
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Only skip if THIS bootstrap already completed (same request / double include).
if ( defined( 'F2F_AI_CHATBOT_LOADED' ) ) {
	return;
}

// Another plugin folder copy already loaded — don't redeclare classes/functions.
if ( defined( 'F2F_AI_CHATBOT_VERSION' ) || class_exists( 'F2F_AI_Chatbot_Admin', false ) ) {
	add_action(
		'admin_notices',
		static function () {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'F2F AI Chatbot birden fazla klasörde yüklü. wp-content/plugins altında f2f-ai-chatbot dışında f2f-ai-chatbot-2 / -8 gibi kopyaları silin; yalnızca bir tane bırakıp etkinleştirin.', 'f2f-ai-chatbot' );
			echo '</p></div>';
		}
	);
	return;
}

define( 'F2F_AI_CHATBOT_VERSION', '1.8.1' );
define( 'F2F_AI_CHATBOT_FILE', __FILE__ );
define( 'F2F_AI_CHATBOT_PATH', plugin_dir_path( __FILE__ ) );
define( 'F2F_AI_CHATBOT_URL', plugin_dir_url( __FILE__ ) );

require_once F2F_AI_CHATBOT_PATH . 'includes/class-openai.php';
require_once F2F_AI_CHATBOT_PATH . 'includes/class-license.php';
require_once F2F_AI_CHATBOT_PATH . 'includes/class-gateway.php';
require_once F2F_AI_CHATBOT_PATH . 'includes/class-notify.php';
require_once F2F_AI_CHATBOT_PATH . 'includes/class-leads.php';
require_once F2F_AI_CHATBOT_PATH . 'includes/class-knowledge.php';
require_once F2F_AI_CHATBOT_PATH . 'includes/class-admin.php';
require_once F2F_AI_CHATBOT_PATH . 'includes/class-conversations-admin.php';
require_once F2F_AI_CHATBOT_PATH . 'includes/class-rest-api.php';
require_once F2F_AI_CHATBOT_PATH . 'includes/class-frontend.php';
require_once F2F_AI_CHATBOT_PATH . 'includes/class-updater.php';

/**
 * Bootstrap — admin menus register immediately so they always appear.
 */
function f2f_ai_chatbot_bootstrap() {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;

	load_plugin_textdomain( 'f2f-ai-chatbot', false, dirname( plugin_basename( F2F_AI_CHATBOT_FILE ) ) . '/languages' );

	// Menus first — never bury behind other bootstrap that might fail.
	if ( class_exists( 'F2F_AI_Chatbot_Admin' ) ) {
		F2F_AI_Chatbot_Admin::instance();
	}
	if ( class_exists( 'F2F_AI_Chatbot_Conversations_Admin' ) ) {
		F2F_AI_Chatbot_Conversations_Admin::instance();
	}
	if ( class_exists( 'F2F_AI_Chatbot_Updater' ) ) {
		F2F_AI_Chatbot_Updater::instance();
	}

	if ( class_exists( 'F2F_AI_Chatbot_Leads' ) ) {
		F2F_AI_Chatbot_Leads::instance();
	}
	if ( class_exists( 'F2F_AI_Chatbot_Knowledge' ) ) {
		F2F_AI_Chatbot_Knowledge::instance();
	}
	if ( class_exists( 'F2F_AI_Chatbot_REST_API' ) ) {
		F2F_AI_Chatbot_REST_API::instance();
	}
	if ( class_exists( 'F2F_AI_Chatbot_Frontend' ) ) {
		F2F_AI_Chatbot_Frontend::instance();
	}
}

// Prefer early hook so admin_menu callbacks are registered before admin_menu fires.
if ( did_action( 'plugins_loaded' ) ) {
	f2f_ai_chatbot_bootstrap();
} else {
	add_action( 'plugins_loaded', 'f2f_ai_chatbot_bootstrap', 5 );
}

// Extra safety: if something delayed plugins_loaded init, still attach menus.
add_action(
	'admin_menu',
	static function () {
		if ( class_exists( 'F2F_AI_Chatbot_Conversations_Admin' ) ) {
			F2F_AI_Chatbot_Conversations_Admin::instance();
		}
		if ( class_exists( 'F2F_AI_Chatbot_Admin' ) ) {
			F2F_AI_Chatbot_Admin::instance();
		}
	},
	1
);

define( 'F2F_AI_CHATBOT_LOADED', true );

// Keep old callback name for any external references / dual-install detection.
if ( ! function_exists( 'f2f_ai_chatbot_init' ) ) {
	/**
	 * @deprecated 1.7.6 Use f2f_ai_chatbot_bootstrap().
	 */
	function f2f_ai_chatbot_init() {
		f2f_ai_chatbot_bootstrap();
	}
}

/**
 * Generic defaults — müşteri sektör jargonunu panelden yazar.
 * {site_name} / {site_description} / {services} / {business_notes} çalışma anında dolar.
 *
 * @return array<string, mixed>
 */
function f2f_ai_chatbot_default_settings() {
	return array(
		'enabled'              => '1',
		'license_key'          => '',
		// Kept internally / migrated; NEVER shown in customer UI.
		'api_key'              => '',
		'model'                => 'gpt-4o-mini',
		'system_prompt'        => "Sen {site_name} web sitesinin yapay zeka asistanısın.\nSite açıklaması: {site_description}\nİşletme notları: {business_notes}\nHizmet/ürün etiketleri: {services}\n\nGörevin: ziyaretçinin sorularını YALNIZCA bu firmanın sunduğu ürün ve hizmetler çerçevesinde yanıtlamak. Sitede olmayan şeyleri önerme.",
		'business_notes'       => '',
		'auto_reindex'         => '1',
		'max_tokens'           => 500,
		'temperature'          => 0.5,
		'rate_limit'           => 30,
		'position'             => 'right',
		'bottom_margin'        => 4,
		'side_margin'          => 2,
		'primary_color'        => '#22C55E',
		'bot_name'             => 'AI Asistan',
		'launcher_label'       => 'AI',
		'teaser_title'         => 'AI ASISTAN',
		'teaser_message'       => 'Merhaba! Size nasıl yardımcı olabilirim?',
		'show_teaser'          => '1',
		'avatar_url'           => '',
		'avatar_id'            => 0,
		'discover_headline'    => 'Size nasıl yardımcı olabiliriz?',
		'discover_subtext'     => 'Bir konu seçin veya sorunuzu yazın. Kısa iletişim bilgisinden sonra size yardımcı olayım.',
		'input_placeholder'    => 'Mesajınızı yazın...',
		'featured_label'       => 'Öne çıkan hizmet',
		'featured_subtitle'    => 'En çok sorulan',
		'featured_icon'        => 'star',
		'service_1_label'      => 'Hizmet 1',
		'service_1_icon'       => 'sparkle',
		'service_2_label'      => 'Hizmet 2',
		'service_2_icon'       => 'globe',
		'service_3_label'      => 'Hizmet 3',
		'service_3_icon'       => 'bag',
		'service_4_label'      => 'Hizmet 4',
		'service_4_icon'       => 'code',
		'divider_text'         => 'VEYA KEŞFET',
		'lead_headline'        => 'Sizi tanıyalım',
		'lead_subtext'         => 'Ekibimizin dönüş yapabilmesi için iletişim bilgilerinizi alın. Ardından asistanla devam edeceğiz.',
		'lead_btn'             => 'Devam et',
		'lead_cancel'          => 'Vazgeç',
		'whatsapp_phone'       => '',
		'whatsapp_message'     => 'Merhaba, sitedeki AI asistan üzerinden yazıyorum.',
		'whatsapp_btn_label'   => 'Canlı Görüşmeye Başla',
		'chat_welcome'         => 'Merhaba {ad}! {hizmet} hakkında yardımcı olayım. Ne öğrenmek istersiniz?',
		'notify_email'         => '',
		'notify_on_lead'       => '1',
		'notify_on_summary'    => '1',
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
 * Resolve avatar URL.
 *
 * @param array<string, mixed>|null $settings Settings.
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
		'enabled'          => ( '1' === (string) $s['enabled'] ),
		'botName'          => $s['bot_name'],
		'avatarUrl'        => f2f_ai_chatbot_avatar_url( $s ),
		'primaryColor'     => $s['primary_color'],
		'position'         => $s['position'],
		'bottomMargin'     => (float) $s['bottom_margin'],
		'sideMargin'       => (float) $s['side_margin'],
		'launcherLabel'    => $s['launcher_label'],
		'teaserTitle'      => $s['teaser_title'],
		'teaserMessage'    => $s['teaser_message'],
		'showTeaser'       => ( '1' === (string) $s['show_teaser'] ),
		'discoverHeadline' => $s['discover_headline'],
		'discoverSubtext'  => $s['discover_subtext'],
		'inputPlaceholder' => $s['input_placeholder'],
		'dividerText'      => $s['divider_text'],
		'featured'         => array(
			'label'    => $s['featured_label'],
			'subtitle' => $s['featured_subtitle'],
			'icon'     => $s['featured_icon'],
			'id'       => 'featured',
		),
		'services'         => array(
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
		'lead'             => array(
			'headline' => $s['lead_headline'],
			'subtext'  => $s['lead_subtext'],
			'btn'      => $s['lead_btn'],
			'cancel'   => $s['lead_cancel'],
		),
		'whatsapp'         => array(
			'phone'   => preg_replace( '/\D+/', '', (string) $s['whatsapp_phone'] ),
			'message' => $s['whatsapp_message'],
			'label'   => $s['whatsapp_btn_label'],
		),
		'chatWelcome'      => $s['chat_welcome'],
	);
}

/**
 * Activation.
 */
function f2f_ai_chatbot_activate() {
	if ( false === get_option( 'f2f_ai_chatbot_settings', false ) ) {
		$defaults = f2f_ai_chatbot_default_settings();
		// Seed bot name from site if available.
		$name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		if ( $name ) {
			$defaults['bot_name']       = $name . ' Asistan';
			$defaults['teaser_title']   = mb_strtoupper( $name );
			$defaults['teaser_message'] = 'Merhaba! ' . $name . ' hakkında size nasıl yardımcı olabilirim?';
		}
		add_option( 'f2f_ai_chatbot_settings', $defaults );
	}
	F2F_AI_Chatbot_Leads::register_post_type();
	if ( class_exists( 'F2F_AI_Chatbot_Knowledge' ) ) {
		F2F_AI_Chatbot_Knowledge::reindex();
	}
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
