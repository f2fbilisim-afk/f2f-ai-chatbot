<?php
/**
 * Paid SaaS gateway — license → F2F platform (OpenAI key never on customer UI).
 *
 * @package F2F_AI_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Routes chat through F2F platform with license + credits.
 * Direct OpenAI only via server constant (F2F internal), never customer settings.
 */
class F2F_AI_Chatbot_Gateway {

	/**
	 * Platform API base (override with F2F_AI_PLATFORM_URL in wp-config).
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
	 * Master OpenAI key — only from wp-config, never from plugin UI.
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
	 * @return array{license:string, credits:?int, status:string, message:string}
	 */
	public static function license_status() {
		$s       = f2f_ai_chatbot_get_settings();
		$license = isset( $s['license_key'] ) ? trim( (string) $s['license_key'] ) : '';
		$cached  = get_transient( 'f2f_ai_license_status' );

		if ( $license && is_array( $cached ) ) {
			return $cached;
		}

		if ( ! $license ) {
			if ( self::master_openai_key() ) {
				return array(
					'license' => '',
					'credits' => null,
					'status'  => 'master',
					'message' => __( 'Geliştirici / F2F master modu (wp-config).', 'f2f-ai-chatbot' ),
				);
			}
			return array(
				'license' => '',
				'credits' => 0,
				'status'  => 'missing',
				'message' => __( 'Lisans anahtarı yok. platform.f2fbilisim.com üzerinden alın.', 'f2f-ai-chatbot' ),
			);
		}

		$remote = self::remote_license_check( $license );
		if ( ! empty( $remote['ok'] ) ) {
			set_transient( 'f2f_ai_license_status', $remote, 5 * MINUTE_IN_SECONDS );
			return $remote;
		}

		// Platform henüz yoksa / erişilemezse — master key ile devam (F2F kurulumu).
		if ( self::master_openai_key() ) {
			return array(
				'license' => $license,
				'credits' => null,
				'status'  => 'master',
				'message' => __( 'Platforma ulaşılamadı; master key ile çalışıyor.', 'f2f-ai-chatbot' ),
			);
		}

		return array(
			'license' => $license,
			'credits' => isset( $remote['credits'] ) ? $remote['credits'] : 0,
			'status'  => isset( $remote['status'] ) ? $remote['status'] : 'error',
			'message' => isset( $remote['message'] ) ? $remote['message'] : __( 'Lisans doğrulanamadı.', 'f2f-ai-chatbot' ),
		);
	}

	/**
	 * @param string $license License key.
	 * @return array<string, mixed>
	 */
	private static function remote_license_check( $license ) {
		$url = self::platform_url() . '/api/v1/chatbot/license';
		$res = wp_remote_post(
			$url,
			array(
				'timeout' => 12,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'license'  => $license,
						'site_url' => home_url( '/' ),
					)
				),
			)
		);

		if ( is_wp_error( $res ) ) {
			return array(
				'ok'      => false,
				'status'  => 'offline',
				'message' => $res->get_error_message(),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $res );
		$data = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( $code < 200 || $code >= 300 || ! is_array( $data ) ) {
			return array(
				'ok'      => false,
				'status'  => 'invalid',
				'message' => is_array( $data ) && ! empty( $data['message'] ) ? (string) $data['message'] : __( 'Lisans geçersiz veya platform yanıt vermedi.', 'f2f-ai-chatbot' ),
				'credits' => 0,
			);
		}

		return array(
			'ok'      => true,
			'license' => $license,
			'credits' => isset( $data['credits'] ) ? (int) $data['credits'] : 0,
			'status'  => isset( $data['status'] ) ? (string) $data['status'] : 'active',
			'message' => isset( $data['message'] ) ? (string) $data['message'] : __( 'Lisans aktif.', 'f2f-ai-chatbot' ),
			'plan'    => isset( $data['plan'] ) ? (string) $data['plan'] : '',
		);
	}

	/**
	 * Chat completion via platform (preferred) or master OpenAI key.
	 *
	 * @param array<int, array>    $messages Messages.
	 * @param array<string, mixed> $args     Args.
	 * @return array{ok:bool, content?:string, error?:string, credits?:int, usage?:array}
	 */
	public static function chat( $messages, $args = array() ) {
		$s       = f2f_ai_chatbot_get_settings();
		$license = isset( $s['license_key'] ) ? trim( (string) $s['license_key'] ) : '';

		if ( $license ) {
			$platform = self::chat_via_platform( $license, $messages, $args );
			if ( ! empty( $platform['ok'] ) || ( isset( $platform['status'] ) && 'no_credits' === $platform['status'] ) ) {
				return $platform;
			}
			// Platform down → fall through to master if available.
		}

		$master = self::master_openai_key();
		if ( $master ) {
			$out = F2F_AI_Chatbot_OpenAI::chat(
				$master,
				self::model(),
				$messages,
				array(
					'max_tokens'  => isset( $args['max_tokens'] ) ? (int) $args['max_tokens'] : 500,
					'temperature' => isset( $args['temperature'] ) ? (float) $args['temperature'] : 0.5,
				)
			);
			return $out;
		}

		if ( ! $license ) {
			return array(
				'ok'    => false,
				'error' => __( 'F2F lisans anahtarı gerekli. OpenAI anahtarı müşteri panelinde yoktur — kontör / lisans platform.f2fbilisim.com üzerinden yüklenir.', 'f2f-ai-chatbot' ),
			);
		}

		return array(
			'ok'    => false,
			'error' => isset( $platform['error'] ) ? $platform['error'] : __( 'Sohbet servisine bağlanılamadı. Lisans ve kontörünüzü kontrol edin.', 'f2f-ai-chatbot' ),
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
				'timeout' => 45,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $license,
				),
				'body'    => wp_json_encode(
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
			return array(
				'ok'    => false,
				'error' => $res->get_error_message(),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $res );
		$data = json_decode( (string) wp_remote_retrieve_body( $res ), true );

		if ( 402 === $code || ( is_array( $data ) && isset( $data['status'] ) && 'no_credits' === $data['status'] ) ) {
			return array(
				'ok'      => false,
				'status'  => 'no_credits',
				'error'   => __( 'Kontörünüz bitti. platform.f2fbilisim.com üzerinden yeni paket yükleyin.', 'f2f-ai-chatbot' ),
				'credits' => 0,
			);
		}

		if ( $code < 200 || $code >= 300 || ! is_array( $data ) ) {
			$msg = __( 'Platform sohbet hatası.', 'f2f-ai-chatbot' );
			if ( is_array( $data ) && ! empty( $data['message'] ) ) {
				$msg = (string) $data['message'];
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

		if ( isset( $data['credits'] ) ) {
			set_transient(
				'f2f_ai_license_status',
				array(
					'ok'      => true,
					'license' => $license,
					'credits' => (int) $data['credits'],
					'status'  => 'active',
					'message' => __( 'Lisans aktif.', 'f2f-ai-chatbot' ),
				),
				5 * MINUTE_IN_SECONDS
			);
		}

		$out = array(
			'ok'      => true,
			'content' => $reply,
		);
		if ( isset( $data['credits'] ) ) {
			$out['credits'] = (int) $data['credits'];
		}
		return $out;
	}
}
