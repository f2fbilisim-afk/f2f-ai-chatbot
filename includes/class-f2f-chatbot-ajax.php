<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class F2F_Chatbot_Ajax {

	public static function init(): void {
		add_action( 'wp_ajax_f2f_chatbot_message', array( __CLASS__, 'handle' ) );
		add_action( 'wp_ajax_nopriv_f2f_chatbot_message', array( __CLASS__, 'handle' ) );
	}

	public static function handle(): void {
		check_ajax_referer( 'f2f_chatbot', 'nonce' );

		$message = isset( $_POST['message'] ) ? sanitize_text_field( wp_unslash( $_POST['message'] ) ) : '';
		if ( '' === $message ) {
			wp_send_json_error( array( 'message' => __( 'Mesaj boş.', 'f2f-ai-chatbot' ) ), 400 );
		}

		$history_raw = isset( $_POST['history'] ) ? wp_unslash( $_POST['history'] ) : '[]';
		$history     = json_decode( $history_raw, true );
		if ( ! is_array( $history ) ) {
			$history = array();
		}

		$messages = array();
		foreach ( array_slice( $history, -10 ) as $turn ) {
			if ( empty( $turn['role'] ) || empty( $turn['content'] ) ) {
				continue;
			}
			$role = 'assistant' === $turn['role'] ? 'assistant' : 'user';
			$messages[] = array(
				'role'    => $role,
				'content' => sanitize_text_field( (string) $turn['content'] ),
			);
		}
		$messages[] = array( 'role' => 'user', 'content' => $message );

		$s      = F2F_Chatbot_Settings::get();
		$client = F2F_Chatbot_Settings::client();
		$result = $client->chat(
			$messages,
			array(
				'system_prompt' => $s['system_prompt'],
				'site_context'  => sprintf(
					'Site: %s (%s)',
					get_bloginfo( 'name' ),
					home_url( '/' )
				),
			)
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array( 'message' => $result->get_error_message() ),
				500
			);
		}

		wp_send_json_success(
			array(
				'reply' => $result['reply'] ?? '',
			)
		);
	}
}
