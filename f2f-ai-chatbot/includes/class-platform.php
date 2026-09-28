<?php
/**
 * F2F platform API — seller site holds OpenAI key; customer sites proxy here.
 *
 * Enable on the F2F / hub WordPress (f2fbilisim.com):
 *   define('F2F_AI_MASTER_OPENAI_KEY', 'sk-...');
 *   // optional: define('F2F_AI_PLATFORM_MODE', true);  // default: on when master key exists
 *
 * Customer sites need NO OpenAI key — they call this API with their license.
 *
 * @package F2F_AI_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Remote chat proxy hosted on the seller WordPress.
 */
class F2F_AI_Chatbot_Platform {

	const NS = 'f2f-ai-platform/v1';

	/**
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * @return bool
	 */
	public static function is_hub() {
		if ( defined( 'F2F_AI_PLATFORM_MODE' ) ) {
			return (bool) F2F_AI_PLATFORM_MODE;
		}
		// Auto-hub: site that holds the master OpenAI key serves customer sites.
		return (bool) F2F_AI_Chatbot_Gateway::master_openai_key();
	}

	/**
	 * Base URL customers should call (no trailing slash).
	 *
	 * @return string
	 */
	public static function public_base_url() {
		if ( defined( 'F2F_AI_PLATFORM_URL' ) && F2F_AI_PLATFORM_URL ) {
			return untrailingslashit( (string) F2F_AI_PLATFORM_URL );
		}
		/**
		 * Filter platform base (scheme + host + optional path prefix). Default: f2fbilisim.com home.
		 *
		 * @param string $url URL.
		 */
		return untrailingslashit(
			(string) apply_filters( 'f2f_ai_chatbot_platform_url', 'https://www.f2fbilisim.com' )
		);
	}

	/**
	 * Full notify endpoint URL.
	 *
	 * @return string
	 */
	public static function notify_endpoint() {
		return self::public_base_url() . '/wp-json/' . self::NS . '/notify';
	}

	/**
	 * Full chat endpoint URL.
	 *
	 * @return string
	 */
	public static function chat_endpoint() {
		return self::public_base_url() . '/wp-json/' . self::NS . '/chat';
	}

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
		if ( ! self::is_hub() ) {
			return;
		}
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route(
			self::NS,
			'/chat',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_chat' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			self::NS,
			'/ping',
			array(
				'methods'             => 'GET',
				'callback'            => static function () {
					return rest_ensure_response(
						array(
							'ok'      => true,
							'hub'     => true,
							'plugin'  => F2F_AI_CHATBOT_VERSION,
							'has_key' => (bool) F2F_AI_Chatbot_Gateway::master_openai_key(),
							'mail'    => true,
						)
					);
				},
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			self::NS,
			'/notify',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_notify' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Relay notification email from hub (SPF-friendly From on f2fbilisim.com).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_notify( $request ) {
		$license = '';
		$auth    = (string) $request->get_header( 'Authorization' );
		if ( preg_match( '/Bearer\s+(\S+)/i', $auth, $m ) ) {
			$license = trim( $m[1] );
		}
		if ( ! $license ) {
			$license = trim( (string) $request->get_param( 'license' ) );
		}
		$license = F2F_AI_Chatbot_License::normalize( $license );
		$pkg     = F2F_AI_Chatbot_License::package_for_key( $license );
		if ( ! $pkg ) {
			return new WP_Error(
				'f2f_hub_license',
				__( 'Lisans geçersiz.', 'f2f-ai-chatbot' ),
				array( 'status' => 403 )
			);
		}

		$to       = sanitize_email( (string) $request->get_param( 'to' ) );
		$subject  = sanitize_text_field( (string) $request->get_param( 'subject' ) );
		$html     = (string) $request->get_param( 'html' );
		$reply_to = sanitize_email( (string) $request->get_param( 'reply_to' ) );
		$site_url = esc_url_raw( (string) $request->get_param( 'site_url' ) );

		if ( ! is_email( $to ) || '' === $subject || '' === trim( wp_strip_all_tags( $html ) ) ) {
			return new WP_Error(
				'f2f_hub_mail',
				__( 'to / subject / html gerekli.', 'f2f-ai-chatbot' ),
				array( 'status' => 400 )
			);
		}

		// Cap HTML size (~120KB).
		if ( strlen( $html ) > 120000 ) {
			$html = substr( $html, 0, 120000 );
		}

		$rl_key = 'f2f_hub_mail_' . md5( F2F_AI_Chatbot_License::hash( $license ) );
		$n      = (int) get_transient( $rl_key );
		if ( $n > 40 ) {
			return new WP_Error(
				'f2f_hub_mail_rate',
				__( 'Çok fazla mail isteği.', 'f2f-ai-chatbot' ),
				array( 'status' => 429 )
			);
		}
		set_transient( $rl_key, $n + 1, HOUR_IN_SECONDS );

		$from = 'noreply@f2fbilisim.com';
		/**
		 * Filter hub notification From.
		 *
		 * @param string $from From email.
		 */
		$from = (string) apply_filters( 'f2f_ai_chatbot_hub_notify_from', $from );

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: F2F AI Chatbot <' . $from . '>',
		);
		if ( $reply_to && is_email( $reply_to ) ) {
			$headers[] = 'Reply-To: ' . $reply_to;
		}
		if ( $site_url ) {
			$headers[] = 'X-F2F-Site: ' . $site_url;
		}

		$filter = static function ( $phpmailer ) use ( $from ) {
			$phpmailer->setFrom( $from, 'F2F AI Chatbot', false );
			$phpmailer->Sender = $from;
		};
		$mail_error = '';
		$fail_cb    = static function ( $wp_error ) use ( &$mail_error ) {
			if ( is_wp_error( $wp_error ) ) {
				$mail_error = $wp_error->get_error_message();
			}
		};
		add_action( 'wp_mail_failed', $fail_cb );
		add_action( 'phpmailer_init', $filter );
		$ok = wp_mail( $to, $subject, $html, $headers );
		remove_action( 'phpmailer_init', $filter );
		remove_action( 'wp_mail_failed', $fail_cb );

		$error = '';
		if ( ! $ok ) {
			$error = $mail_error ? $mail_error : __( 'Hub wp_mail başarısız.', 'f2f-ai-chatbot' );
			update_option( 'f2f_ai_notify_last_error', $error, false );
		}

		return rest_ensure_response(
			array(
				'ok'      => (bool) $ok,
				'via'     => 'hub',
				'to'      => $to,
				'from'    => $from,
				'error'   => $error,
				'message' => $ok ? 'sent' : $error,
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_chat( $request ) {
		$master = F2F_AI_Chatbot_Gateway::master_openai_key();
		if ( ! $master ) {
			return rest_ensure_response(
				array(
					'ok'      => false,
					'error'   => __( 'Platform hub: F2F_AI_MASTER_OPENAI_KEY eksik.', 'f2f-ai-chatbot' ),
					'code'    => 'f2f_hub_no_key',
					'message' => __( 'Platform hub: F2F_AI_MASTER_OPENAI_KEY eksik.', 'f2f-ai-chatbot' ),
				)
			);
		}
		if ( ! F2F_AI_Chatbot_Gateway::master_key_looks_valid() ) {
			return rest_ensure_response(
				array(
					'ok'      => false,
					'error'   => __( 'Hub wp-config’te yanlış anahtar var: F2F-… lisans değil, OpenAI sk-… anahtarı olmalı (platform.openai.com).', 'f2f-ai-chatbot' ),
					'code'    => 'f2f_hub_bad_key',
					'message' => __( 'Hub wp-config’te yanlış anahtar var: F2F-… lisans değil, OpenAI sk-… anahtarı olmalı (platform.openai.com).', 'f2f-ai-chatbot' ),
				)
			);
		}

		$license = '';
		$auth    = (string) $request->get_header( 'Authorization' );
		if ( preg_match( '/Bearer\s+(\S+)/i', $auth, $m ) ) {
			$license = trim( $m[1] );
		}
		if ( ! $license ) {
			$license = trim( (string) $request->get_param( 'license' ) );
		}
		$license = F2F_AI_Chatbot_License::normalize( $license );

		$pkg = F2F_AI_Chatbot_License::package_for_key( $license );
		if ( ! $pkg ) {
			return new WP_Error(
				'f2f_hub_license',
				__( 'Lisans geçersiz.', 'f2f-ai-chatbot' ),
				array( 'status' => 403 )
			);
		}

		$messages = $request->get_param( 'messages' );
		if ( ! is_array( $messages ) || ! $messages ) {
			return new WP_Error(
				'f2f_hub_messages',
				__( 'messages gerekli.', 'f2f-ai-chatbot' ),
				array( 'status' => 400 )
			);
		}

		$clean = array();
		foreach ( array_slice( $messages, 0, 24 ) as $turn ) {
			if ( ! is_array( $turn ) ) {
				continue;
			}
			$role = isset( $turn['role'] ) ? (string) $turn['role'] : '';
			$text = isset( $turn['content'] ) ? sanitize_textarea_field( (string) $turn['content'] ) : '';
			if ( ! in_array( $role, array( 'system', 'user', 'assistant' ), true ) || '' === $text ) {
				continue;
			}
			$clean[] = array(
				'role'    => $role,
				'content' => mb_substr( $text, 0, 8000 ),
			);
		}
		if ( ! $clean ) {
			return new WP_Error(
				'f2f_hub_messages',
				__( 'Geçerli mesaj yok.', 'f2f-ai-chatbot' ),
				array( 'status' => 400 )
			);
		}

		// Simple IP rate limit on hub.
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '0';
		$key = 'f2f_hub_rl_' . md5( $ip . '|' . F2F_AI_Chatbot_License::hash( $license ) );
		$n   = (int) get_transient( $key );
		if ( $n > 60 ) {
			return new WP_Error(
				'f2f_hub_rate',
				__( 'Çok fazla istek.', 'f2f-ai-chatbot' ),
				array( 'status' => 429 )
			);
		}
		set_transient( $key, $n + 1, MINUTE_IN_SECONDS );

		$result = F2F_AI_Chatbot_OpenAI::chat(
			$master,
			F2F_AI_Chatbot_Gateway::model(),
			$clean,
			array(
				'max_tokens'  => 500,
				'temperature' => 0.5,
			)
		);

		if ( empty( $result['ok'] ) ) {
			// Do NOT use HTTP 502 — Cloudflare replaces 502 bodies with "error code: 502".
			return rest_ensure_response(
				array(
					'ok'      => false,
					'error'   => isset( $result['error'] ) ? (string) $result['error'] : __( 'OpenAI hatası.', 'f2f-ai-chatbot' ),
					'code'    => 'f2f_hub_openai',
					'message' => isset( $result['error'] ) ? (string) $result['error'] : __( 'OpenAI hatası.', 'f2f-ai-chatbot' ),
				)
			);
		}

		return rest_ensure_response(
			array(
				'ok'      => true,
				'reply'   => $result['content'],
				'content' => $result['content'],
				'plan'    => $pkg['plan'],
			)
		);
	}
}
