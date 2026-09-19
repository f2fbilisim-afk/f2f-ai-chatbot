<?php
/**
 * Uninstall cleanup.
 *
 * @package F2F_AI_Chatbot
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'f2f_ai_chatbot_settings' );

global $wpdb;
$wpdb->query(
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_f2f_ai_rl_%' OR option_name LIKE '_transient_timeout_f2f_ai_rl_%'"
);
