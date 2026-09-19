<?php
/**
 * Plugin Name:       F2F AI Chatbot
 * Plugin URI:        https://www.f2fbilisim.com
 * Description:       OpenAI destekli floating chatbot widget. Site ziyaretçilerine anında yanıt verin.
 * Version:           1.0.0
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

define( 'F2F_AI_CHATBOT_VERSION', '1.0.0' );
define( 'F2F_AI_CHATBOT_FILE', __FILE__ );
define( 'F2F_AI_CHATBOT_PATH', plugin_dir_path( __FILE__ ) );
define( 'F2F_AI_CHATBOT_URL', plugin_dir_url( __FILE__ ) );

require_once F2F_AI_CHATBOT_PATH . 'includes/class-openai.php';
require_once F2F_AI_CHATBOT_PATH . 'includes/class-admin.php';
require_once F2F_AI_CHATBOT_PATH . 'includes/class-rest-api.php';
require_once F2F_AI_CHATBOT_PATH . 'includes/class-frontend.php';

/**
 * Bootstrap plugin.
 */
function f2f_ai_chatbot_init() {
	load_plugin_textdomain( 'f2f-ai-chatbot', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	F2F_AI_Chatbot_Admin::instance();
	F2F_AI_Chatbot_REST_API::instance();
	F2F_AI_Chatbot_Frontend::instance();
}
add_action( 'plugins_loaded', 'f2f_ai_chatbot_init' );

/**
 * Default settings.
 *
 * @return array<string, mixed>
 */
function f2f_ai_chatbot_default_settings() {
	return array(
		'api_key'         => '',
		'model'           => 'gpt-4o-mini',
		'system_prompt'   => "Sen F2F Bilişim'in yardımcı asistanısın. Ziyaretçilere e-ticaret, e-ihracat, web sitesi ve dijital hizmetler konusunda nazik, net ve kısa Türkçe yanıtlar ver. Bilmediğin konularda spekülasyon yapma; iletişim için info@f2fbilisim.com veya +90 549 900 93 10 yönlendir.",
		'welcome_message' => 'Merhaba! F2F Bilişim asistanıyım. Size nasıl yardımcı olabilirim?',
		'bot_name'        => 'F2F Asistan',
		'primary_color'   => '#0B6E4F',
		'position'        => 'right',
		'enabled'         => '1',
		'rate_limit'      => 20,
		'max_tokens'      => 500,
		'temperature'     => 0.7,
	);
}

/**
 * Get merged plugin settings.
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
 * Activation: seed defaults.
 */
function f2f_ai_chatbot_activate() {
	if ( false === get_option( 'f2f_ai_chatbot_settings', false ) ) {
		add_option( 'f2f_ai_chatbot_settings', f2f_ai_chatbot_default_settings() );
	}
}
register_activation_hook( __FILE__, 'f2f_ai_chatbot_activate' );
