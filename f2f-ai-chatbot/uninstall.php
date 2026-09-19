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
delete_option( 'f2f_ai_chatbot_knowledge' );
delete_option( 'f2f_ai_license_meta' );
delete_option( 'f2f_ai_setup_done' );
delete_option( 'f2f_ai_setup_step' );
delete_transient( 'f2f_ai_license_status' );

global $wpdb;
$wpdb->query(
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_f2f_ai_rl_%' OR option_name LIKE '_transient_timeout_f2f_ai_rl_%'"
);

$leads = get_posts(
	array(
		'post_type'      => 'f2f_chat_lead',
		'posts_per_page' => -1,
		'post_status'    => 'any',
		'fields'         => 'ids',
	)
);
foreach ( $leads as $lead_id ) {
	wp_delete_post( $lead_id, true );
}
