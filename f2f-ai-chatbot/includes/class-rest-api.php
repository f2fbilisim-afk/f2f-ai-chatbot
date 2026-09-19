<?php
/**
 * REST API: lead + chat.
 *
 * @package F2F_AI_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * /wp-json/f2f-ai-chatbot/v1/*
 */
class F2F_AI_Chatbot_REST_API {

	/**
	 * @var self|null
	 */
	private static $instance = null;

	const NS = 'f2f-ai-chatbot/v1';

	/**
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route(
			self::NS,
			'/config',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_config' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NS,
			'/lead',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_lead' ),
				'permission_callback' => array( $this, 'permission' ),
			)
		);

		register_rest_route(
			self::NS,
			'/chat',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_chat' ),
				'permission_callback' => array( $this, 'permission' ),
			)
		);

		register_rest_route(
			self::NS,
			'/summarize',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_summarize' ),
				'permission_callback' => array( $this, 'permission' ),
			)
		);
	}

	/**
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
			return new WP_Error( 'f2f_nonce', __( 'Geçersiz güvenlik anahtarı. Sayfayı yenileyin.', 'f2f-ai-chatbot' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * @return WP_REST_Response
	 */
	public function handle_config() {
		return rest_ensure_response( f2f_ai_chatbot_public_config() );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_lead( $request ) {
		$first   = trim( (string) $request->get_param( 'first_name' ) );
		$last    = trim( (string) $request->get_param( 'last_name' ) );
		$phone   = trim( (string) $request->get_param( 'phone' ) );
		$email   = trim( (string) $request->get_param( 'email' ) );
		$service = trim( (string) $request->get_param( 'service' ) );
		$intent  = trim( (string) $request->get_param( 'intent' ) );

		if ( '' === $first || '' === $last ) {
			return new WP_Error( 'f2f_name', __( 'Ad ve soyad zorunludur.', 'f2f-ai-chatbot' ), array( 'status' => 400 ) );
		}
		if ( '' === $phone || mb_strlen( $phone ) < 7 ) {
			return new WP_Error( 'f2f_phone', __( 'Geçerli bir telefon girin.', 'f2f-ai-chatbot' ), array( 'status' => 400 ) );
		}
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'f2f_email', __( 'Geçerli bir e-posta girin.', 'f2f-ai-chatbot' ), array( 'status' => 400 ) );
		}

		$lead_id = F2F_AI_Chatbot_Leads::save(
			array(
				'first_name' => $first,
				'last_name'  => $last,
				'phone'      => $phone,
				'email'      => $email,
				'service'    => $service,
				'intent'     => $intent,
			)
		);

		if ( is_wp_error( $lead_id ) ) {
			return $lead_id;
		}

		$settings = f2f_ai_chatbot_get_settings();
		$welcome  = (string) $settings['chat_welcome'];
		$welcome  = str_replace(
			array( '{ad}', '{soyad}', '{hizmet}' ),
			array( $first, $last, $service ? $service : __( 'proje', 'f2f-ai-chatbot' ) ),
			$welcome
		);

		return rest_ensure_response(
			array(
				'ok'      => true,
				'leadId'  => $lead_id,
				'welcome' => $welcome,
			)
		);
	}

	/**
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
			return new WP_Error( 'f2f_long', __( 'Mesaj çok uzun.', 'f2f-ai-chatbot' ), array( 'status' => 400 ) );
		}

		$rate = $this->check_rate_limit( (int) $settings['rate_limit'] );
		if ( is_wp_error( $rate ) ) {
			return $rate;
		}

		$lead = $request->get_param( 'lead' );
		if ( ! is_array( $lead ) ) {
			$lead = array();
		}

		$query_for_kb = $message;
		if ( ! empty( $lead['service'] ) ) {
			$query_for_kb .= ' ' . sanitize_text_field( (string) $lead['service'] );
		}
		if ( ! empty( $lead['intent'] ) ) {
			$query_for_kb .= ' ' . sanitize_text_field( (string) $lead['intent'] );
		}

		$system = F2F_AI_Chatbot_Knowledge::build_system_prompt( $settings, $query_for_kb, $lead );

		$messages = array(
			array(
				'role'    => 'system',
				'content' => $system,
			),
		);

		$history = $request->get_param( 'history' );
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

		$result = F2F_AI_Chatbot_Gateway::chat(
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

		$reply   = $result['content'];
		$lead_id = absint( $request->get_param( 'leadId' ) );
		if ( ! $lead_id && ! empty( $lead['id'] ) ) {
			$lead_id = absint( $lead['id'] );
		}
		if ( $lead_id ) {
			F2F_AI_Chatbot_Leads::append_history(
				$lead_id,
				array(
					array(
						'role'    => 'user',
						'content' => $message,
					),
					array(
						'role'    => 'assistant',
						'content' => $reply,
					),
				)
			);
		}

		return rest_ensure_response(
			array(
				'reply'            => $reply,
				'leadId'           => $lead_id ? $lead_id : null,
				'summaryInSeconds' => 120,
			)
		);
	}

	/**
	 * Force / complete summary after 2 minutes idle (browser backup for WP-Cron).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_summarize( $request ) {
		$lead_id = absint( $request->get_param( 'leadId' ) );
		if ( ! $lead_id ) {
			return new WP_Error( 'f2f_lead', __( 'leadId gerekli.', 'f2f-ai-chatbot' ), array( 'status' => 400 ) );
		}

		$force = (bool) $request->get_param( 'force' );
		$out   = F2F_AI_Chatbot_Leads::summarize_lead( $lead_id, $force );

		if ( empty( $out['ok'] ) ) {
			return rest_ensure_response(
				array(
					'ok'      => false,
					'pending' => true,
					'message' => isset( $out['error'] ) ? $out['error'] : '',
				)
			);
		}

		return rest_ensure_response(
			array(
				'ok'       => true,
				'summary'  => $out['summary'],
				'interest' => $out['interest'],
			)
		);
	}

	/**
	 * @param int $limit Limit.
	 * @return true|WP_Error
	 */
	private function check_rate_limit( $limit ) {
		$ip   = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$key  = 'f2f_ai_rl_' . md5( $ip );
		$hits = (int) get_transient( $key );
		if ( $hits >= $limit ) {
			return new WP_Error(
				'f2f_rate',
				__( 'Çok fazla istek. Lütfen sonra tekrar deneyin.', 'f2f-ai-chatbot' ),
				array( 'status' => 429 )
			);
		}
		set_transient( $key, $hits + 1, HOUR_IN_SECONDS );
		return true;
	}
}
