<?php
/**
 * Plugin Name: F2F AI Chatbot
 * Description: Merkezi api.f2fbilisim.com üzerinden lisanslı AI sohbet widget'ı. OpenAI anahtarı gerekmez.
 * Version:     1.0.0
 * Author:      F2F Bilişim
 * Text Domain: f2f-ai-chatbot
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'F2F_AI_CHATBOT_VERSION', '1.0.0' );
define( 'F2F_AI_CHATBOT_FILE', __FILE__ );
define( 'F2F_AI_CHATBOT_DIR', plugin_dir_path( __FILE__ ) );
define( 'F2F_AI_CHATBOT_URL', plugin_dir_url( __FILE__ ) );

require_once F2F_AI_CHATBOT_DIR . 'includes/class-f2f-saas-client.php';
require_once F2F_AI_CHATBOT_DIR . 'includes/class-f2f-chatbot-settings.php';
require_once F2F_AI_CHATBOT_DIR . 'includes/class-f2f-chatbot-ajax.php';
require_once F2F_AI_CHATBOT_DIR . 'includes/class-f2f-chatbot-widget.php';
require_once F2F_AI_CHATBOT_DIR . 'includes/class-f2f-chatbot-admin.php';

final class F2F_AI_Chatbot_Plugin {
	public static function init(): void {
		F2F_Chatbot_Settings::init();
		F2F_Chatbot_Ajax::init();
		F2F_Chatbot_Widget::init();
		F2F_Chatbot_Admin::init();
	}
}

add_action( 'plugins_loaded', array( 'F2F_AI_Chatbot_Plugin', 'init' ) );
