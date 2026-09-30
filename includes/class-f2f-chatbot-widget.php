<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class F2F_Chatbot_Widget {

	public static function init(): void {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'wp_footer', array( __CLASS__, 'render' ) );
	}

	public static function assets(): void {
		$s = F2F_Chatbot_Settings::get();
		if ( empty( $s['enabled'] ) || ! F2F_Chatbot_Settings::client()->is_configured() ) {
			return;
		}

		wp_enqueue_style(
			'f2f-ai-chatbot',
			F2F_AI_CHATBOT_URL . 'assets/chat-widget.css',
			array(),
			F2F_AI_CHATBOT_VERSION
		);
		wp_enqueue_script(
			'f2f-ai-chatbot',
			F2F_AI_CHATBOT_URL . 'assets/chat-widget.js',
			array(),
			F2F_AI_CHATBOT_VERSION,
			true
		);
		wp_localize_script(
			'f2f-ai-chatbot',
			'F2FChatbot',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'f2f_chatbot' ),
				'botName' => $s['bot_name'],
				'welcome' => $s['welcome'],
			)
		);
	}

	public static function render(): void {
		$s = F2F_Chatbot_Settings::get();
		if ( empty( $s['enabled'] ) || ! F2F_Chatbot_Settings::client()->is_configured() ) {
			return;
		}
		?>
		<div id="f2f-chatbot-root" aria-live="polite"></div>
		<?php
	}
}
