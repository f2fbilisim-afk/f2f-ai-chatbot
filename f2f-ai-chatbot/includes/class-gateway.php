<?php
/**
 * Chat gateway — premium license unlocks AI; OpenAI key stays on F2F (wp-config / platform).
 *
 * @package F2F_AI_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Routes chat when premium license is active.
 * OpenAI is never entered in the customer UI.
 */
class F2F_AI_Chatbot_Gateway {

	/**
	 * Platform API base (optional remote proxy).
	 *
	 * @return string
	 */
	public static function platform_url() {
		if ( defined( 'F2F_AI_PLATFORM_URL' ) && F2F_AI_PLATFORM_URL ) {
			return untrailingslashit( (string) F2F_AI_PLATFORM_URL );
		}
		/**
		 * Filter platform base URL.
		 *
		 * @param string $url URL.
		 */
		return untrailingslashit( (string) apply_filters( 'f2f_ai_chatbot_platform_url', 'https://platform.f2fbilisim.com' ) );
	}

	/**
	 * Master OpenAI key — only from wp-config (your developer project API).
	 *
	 * @return string
	 */
	public static function master_openai_key() {
		if ( defined( 'F2F_AI_MASTER_OPENAI_KEY' ) && F2F_AI_MASTER_OPENAI_KEY ) {
			return (string) F2F_AI_MASTER_OPENAI_KEY;
		}
		return '';
	}

	/**
	 * Model controlled by F2F (not customer).
	 *
	 * @return string
	 */
	public static function model() {
		if ( defined( 'F2F_AI_MODEL' ) && F2F_AI_MODEL ) {
			return (string) F2F_AI_MODEL;
		}
		return 'gpt-4o-mini';
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function license_status() {
		$local = F2F_AI_Chatbot_License::status();

		// Developer / F2F-managed site: master key may run without a sold license.
		if ( empty( $local['can_chat'] ) && self::master_openai_key() && defined( 'F2F_AI_ALLOW_MASTER_WITHOUT_LICENSE' ) && F2F_AI_ALLOW_MASTER_WITHOUT_LICENSE ) {
			return array(
				'ok'             => true,
				'license'        => '',
				'credits'        => null,
				'status'         => 'master',
				'message'        => __( 'Geliştirici master modu (wp-config).', 'f2f-ai-chatbot' ),
				'premium'        => true,
				'can_chat'       => true,
				'expires_at'     => null,
				'days_left'      => null,
				'plan'           => 'master',
				'plan_label'     => 'Master',
				'messages_limit' => null,
				'messages_used'  => null,
				'messages_left'  => null,
			);
		}

		if ( ! isset( $local['credits'] ) && isset( $local['messages_left'] ) ) {
			$local['credits'] = $local['messages_left'];
		}
		return $local;
	}

	/**
	 * Whether remote platform proxy is enabled.
	 * Default OFF — calling a missing host with long timeouts exhausts PHP workers (503).
	 *
	 * Enable with: define('F2F_AI_PLATFORM_URL', 'https://...');
	 * or filter f2f_ai_chatbot_use_platform → true.
	 *
	 * @return bool
	 */
	public static function platform_enabled() {
		if ( defined( 'F2F_AI_PLATFORM_URL' ) && F2F_AI_PLATFORM_URL ) {
			return (bool) apply_filters( 'f2f_ai_chatbot_use_platform', true );
		}
		return (bool) apply_filters( 'f2f_ai_chatbot_use_platform', false );
	}

	/**
	 * Chat completion via master OpenAI key (preferred) or optional platform proxy.
	 *
	 * @param array<int, array>    $messages Messages.
	 * @param array<string, mixed> $args     Args.
	 * @return array{ok:bool, content?:string, error?:string, credits?:int, usage?:array}
	 */
	public static function chat( $messages, $args = array() ) {
		$lic = self::license_status();
		if ( empty( $lic['can_chat'] ) ) {
			$status = isset( $lic['status'] ) ? (string) $lic['status'] : 'missing';
			if ( 'exhausted' === $status ) {
				return array(
					'ok'      => false,
					'status'  => 'no_credits',
					'error'   => isset( $lic['message'] ) ? (string) $lic['message'] : __( 'Paket konuşma kotası doldu.', 'f2f-ai-chatbot' ),
					'credits' => 0,
				);
			}
			if ( 'expired' === $status ) {
				return array(
					'ok'    => false,
					'error' => __( 'Premium lisans süresi doldu. Yeni anahtar için F2F Bilişim ile iletişime geçin.', 'f2f-ai-chatbot' ),
				);
			}
			if ( 'invalid' === $status ) {
				return array(
					'ok'    => false,
					'error' => __( 'Lisans anahtarı geçersiz.', 'f2f-ai-chatbot' ),
				);
			}
			return array(
				'ok'    => false,
				'error' => __( 'Premium lisans gerekli. Ayarlar → F2F Lisans alanına satın aldığınız anahtarı girin.', 'f2f-ai-chatbot' ),
			);
		}

		$s       = f2f_ai_chatbot_get_settings();
		$license = isset( $s['license_key'] ) ? trim( (string) $s['license_key'] ) : '';
		$result  = null;
		$use_plat = self::platform_enabled() && $license && ! get_transient( 'f2f_ai_platform_down' );

		// Optional remote platform (explicitly enabled only).
		if ( $use_plat && apply_filters( 'f2f_ai_chatbot_prefer_platform', false ) ) {
			$result = self::chat_via_platform( $license, $messages, $args );
			if ( empty( $result['ok'] ) && empty( self::master_openai_key() ) ) {
				return $result;
			}
			if ( ! empty( $result['ok'] ) ) {
				F2F_AI_Chatbot_License::consume_message();
				$st = F2F_AI_Chatbot_License::status();
				$result['credits'] = isset( $st['messages_left'] ) ? (int) $st['messages_left'] : null;
				return $result;
			}
		}

		$master = self::master_openai_key();
		if ( $master ) {
			$result = F2F_AI_Chatbot_OpenAI::chat(
				$master,
				self::model(),
				$messages,
				array(
					'max_tokens'  => isset( $args['max_tokens'] ) ? (int) $args['max_tokens'] : 500,
					'temperature' => isset( $args['temperature'] ) ? (float) $args['temperature'] : 0.5,
				)
			);
			if ( ! empty( $result['ok'] ) ) {
				F2F_AI_Chatbot_License::consume_message();
				$st = F2F_AI_Chatbot_License::status();
				$result['credits'] = isset( $st['messages_left'] ) ? (int) $st['messages_left'] : null;
			}
			return $result;
		}

		// Platform only when explicitly enabled — never hammer a missing host (causes 503).
		if ( $use_plat ) {
			$result = self::chat_via_platform( $license, $messages, $args );
			if ( ! empty( $result['ok'] ) ) {
				F2F_AI_Chatbot_License::consume_message();
				$st = F2F_AI_Chatbot_License::status();
				$result['credits'] = isset( $st['messages_left'] ) ? (int) $st['messages_left'] : null;
				return $result;
			}
			$platform_err = isset( $result['error'] ) ? (string) $result['error'] : '';
			return array(
				'ok'    => false,
				'error' => sprintf(
					/* translators: %s: platform/network detail */
					__( 'AI yanıt veremiyor: master anahtar yok ve platform proxy başarısız (%s).', 'f2f-ai-chatbot' ),
					$platform_err ? $platform_err : __( 'bağlantı yok', 'f2f-ai-chatbot' )
				),
			);
		}

		return array(
			'ok'    => false,
			'error' => __( 'Lisans aktif ama AI anahtarı yapılandırılmamış. wp-config.php içine define(\'F2F_AI_MASTER_OPENAI_KEY\', \'sk-...\'); ekleyin (tırnakları kapatmayı unutmayın).', 'f2f-ai-chatbot' ),
		);
	}

	/**
	 * @param string               $license  License.
	 * @param array<int, array>    $messages Messages.
	 * @param array<string, mixed> $args     Args.
	 * @return array<string, mixed>
	 */
	private static function chat_via_platform( $license, $messages, $args ) {
		$url = self::platform_url() . '/api/v1/chatbot/chat';
		$res = wp_remote_post(
			$url,
			array(
				'timeout'     => 8,
				'redirection' => 2,
				'headers'     => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $license,
				),
				'body'        => wp_json_encode(
					array(
						'license'  => $license,
						'site_url' => home_url( '/' ),
						'messages' => array_values( $messages ),
						'meta'     => array(
							'plugin' => F2F_AI_CHATBOT_VERSION,
						),
					)
				),
			)
		);

		if ( is_wp_error( $res ) ) {
			set_transient( 'f2f_ai_platform_down', 1, HOUR_IN_SECONDS );
			return array(
				'ok'    => false,
				'error' => $res->get_error_message(),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $res );
		$data = json_decode( (string) wp_remote_retrieve_body( $res ), true );

		if ( $code < 200 || $code >= 300 || ! is_array( $data ) ) {
			// 404/5xx: back off so workers are not exhausted.
			if ( $code >= 400 || 0 === $code ) {
				set_transient( 'f2f_ai_platform_down', 1, 6 * HOUR_IN_SECONDS );
			}
			$host = wp_parse_url( self::platform_url(), PHP_URL_HOST );
			$msg  = sprintf(
				/* translators: 1: http code, 2: host */
				__( 'Platform yanıt vermedi (HTTP %1$d · %2$s).', 'f2f-ai-chatbot' ),
				$code ? $code : 0,
				$host ? $host : 'platform'
			);
			if ( is_array( $data ) && ! empty( $data['message'] ) ) {
				$msg = (string) $data['message'];
			} elseif ( is_array( $data ) && ! empty( $data['error'] ) ) {
				$msg = is_string( $data['error'] ) ? (string) $data['error'] : $msg;
			}
			return array(
				'ok'    => false,
				'error' => $msg,
			);
		}

		$reply = isset( $data['reply'] ) ? trim( (string) $data['reply'] ) : '';
		if ( '' === $reply && isset( $data['content'] ) ) {
			$reply = trim( (string) $data['content'] );
		}
		if ( '' === $reply ) {
			return array(
				'ok'    => false,
				'error' => __( 'Platform boş yanıt döndürdü.', 'f2f-ai-chatbot' ),
			);
		}

		delete_transient( 'f2f_ai_platform_down' );

		return array(
			'ok'      => true,
			'content' => $reply,
		);
	}
}
