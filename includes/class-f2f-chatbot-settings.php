<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class F2F_Chatbot_Settings {

	const OPTION_KEY = 'f2f_ai_chatbot_settings';

	public static function init(): void {
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
	}

	public static function defaults(): array {
		return array(
			'saas_api_base'  => 'https://api.f2fbilisim.com',
			'license_key'    => '',
			'bot_name'       => 'Asistan',
			'welcome'        => 'Merhaba! Size nasıl yardımcı olabilirim?',
			'system_prompt'  => '',
			'enabled'        => 1,
		);
	}

	public static function get(): array {
		$stored = get_option( self::OPTION_KEY, array() );
		return array_merge( self::defaults(), is_array( $stored ) ? $stored : array() );
	}

	public static function client(): F2F_SaaS_Client {
		return new F2F_SaaS_Client( 'ai-chatbot', self::OPTION_KEY );
	}

	public static function register(): void {
		register_setting(
			'f2f_ai_chatbot_settings_group',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
			)
		);
	}

	public static function sanitize( $input ): array {
		$out  = self::defaults();
		$data = is_array( $input ) ? $input : array();
		$out['saas_api_base'] = esc_url_raw( trim( $data['saas_api_base'] ?? $out['saas_api_base'] ) );
		$out['license_key']   = sanitize_text_field( $data['license_key'] ?? '' );
		$out['bot_name']      = sanitize_text_field( $data['bot_name'] ?? $out['bot_name'] );
		$out['welcome']       = sanitize_text_field( $data['welcome'] ?? $out['welcome'] );
		$out['system_prompt'] = sanitize_textarea_field( $data['system_prompt'] ?? '' );
		$out['enabled']       = empty( $data['enabled'] ) ? 0 : 1;
		return $out;
	}
}
