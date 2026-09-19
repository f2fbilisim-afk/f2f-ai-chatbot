<?php
/**
 * REST API for chat messages.
 *
 * @package F2F_AI_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Public chat endpoint under /wp-json/f2f-ai-chatbot/v1/chat
 */
class F2F_AI_Chatbot_REST_API {

	/**
	 * Singleton.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Namespace.
	 *
	 * @var string
	 */
	const NS = 'f2f-ai-chatbot/v1';

	/**
	 * Get instance.
	 *
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register routes.
	 */
	public function register_routes() {
		register_rest_route(
			self::NS,
			'/chat',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_chat' ),
				'permission_callback' => array( $this, 'permission' ),
				'args'                => array(
					'message'  => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'history'  => array(
						'required' => false,
						'type'     => 'array',
						'default'  => array(),
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/config',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_config' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Permission: widget must be enabled + valid nonce.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function permission( $request ) {
		$settings = f2f_ai_chatbot_get_settings();
		if ( empty( $settings['enabled'] ) || '1' !== (string) $settings['enabled'] ) {
			return new WP_Error( 'f2f_disabled', __( 'Chatbot kapalı.', 'f2f-ai-chatbot' ), array( 'status' => 403 ) );
		}

		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! $nonce ) {
			$nonce = $request->get_param( '_wpnonce' );
		}
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'f2f_nonce', __( 'Geçersiz güvenlik anahtarı. Sayfayı yenileyip tekrar deneyin.', 'f2f-ai-chatbot' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Public widget config (no secrets).
	 *
	 * @return WP_REST_Response
	 */
	public function handle_config() {
		$s = f2f_ai_chatbot_get_settings();
		return rest_ensure_response(
			array(
				'enabled'         => ( '1' === (string) $s['enabled'] ),
				'botName'         => $s['bot_name'],
				'welcomeMessage'  => $s['welcome_message'],
				'primaryColor'    => $s['primary_color'],
				'position'        => $s['position'],
				'hasApiKey'       => ! empty( $s['api_key'] ),
			)
		);
	}

	/**
	 * Handle chat turn.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_chat( $request ) {
		$settings = f2f_ai_chatbot_get_settings();
		$message  = trim( (string) $request->get_param( 'message' ) );

		if ( '' === $message ) {
			return new WP_Error( 'f2f_empty', __( 'Mesaj boş olamaz.', 'f2f-ai-chatbot' ), array( 'status' => 400 ) );
		}

		if ( mb_strlen( $message ) > 2000 ) {
			return new WP_Error( 'f2f_long', __( 'Mesaj çok uzun (max 2000 karakter).', 'f2f-ai-chatbot' ), array( 'status' => 400 ) );
		}

		$rate = $this->check_rate_limit( (int) $settings['rate_limit'] );
		if ( is_wp_error( $rate ) ) {
			return $rate;
		}

		$history  = $request->get_param( 'history' );
		$messages = array(
			array(
				'role'    => 'system',
				'content' => (string) $settings['system_prompt'],
			),
		);

		if ( is_array( $history ) ) {
			$history = array_slice( $history, -12 );
			foreach ( $history as $turn ) {
				if ( ! is_array( $turn ) ) {
					continue;
				}
				$role = isset( $turn['role'] ) ? (string) $turn['role'] : '';
				$text = isset( $turn['content'] ) ? sanitize_text_field( (string) $turn['content'] ) : '';
				if ( ! in_array( $role, array( 'user', 'assistant' ), true ) || '' === $text ) {
					continue;
				}
				$messages[] = array(
					'role'    => $role,
					'content' => mb_substr( $text, 0, 2000 ),
				);
			}
		}

		$messages[] = array(
			'role'    => 'user',
			'content' => $message,
		);

		$result = F2F_AI_Chatbot_OpenAI::chat(
			(string) $settings['api_key'],
			(string) $settings['model'],
			$messages,
			array(
				'max_tokens'  => (int) $settings['max_tokens'],
				'temperature' => (float) $settings['temperature'],
			)
		);

		if ( empty( $result['ok'] ) ) {
			return new WP_Error(
				'f2f_openai',
				isset( $result['error'] ) ? $result['error'] : __( 'Yanıt alınamadı.', 'f2f-ai-chatbot' ),
				array( 'status' => 502 )
			);
		}

		return rest_ensure_response(
			array(
				'reply' => $result['content'],
			)
		);
	}

	/**
	 * Simple IP-based hourly rate limit via transients.
	 *
	 * @param int $limit Max requests per hour.
	 * @return true|WP_Error
	 */
	private function check_rate_limit( $limit ) {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$key = 'f2f_ai_rl_' . md5( $ip );
		$hits = (int) get_transient( $key );
		if ( $hits >= $limit ) {
			return new WP_Error(
				'f2f_rate',
				__( 'Çok fazla istek gönderildi. Lütfen bir süre sonra tekrar deneyin.', 'f2f-ai-chatbot' ),
				array( 'status' => 429 )
			);
		}
		set_transient( $key, $hits + 1, HOUR_IN_SECONDS );
		return true;
	}
}
