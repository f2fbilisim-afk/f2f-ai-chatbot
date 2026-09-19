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
		if ( empty( $local['premium'] ) && self::master_openai_key() && defined( 'F2F_AI_ALLOW_MASTER_WITHOUT_LICENSE' ) && F2F_AI_ALLOW_MASTER_WITHOUT_LICENSE ) {
			return array(
				'ok'         => true,
				'license'    => '',
				'credits'    => null,
				'status'     => 'master',
				'message'    => __( 'Geliştirici master modu (wp-config).', 'f2f-ai-chatbot' ),
				'premium'    => true,
				'expires_at' => null,
				'days_left'  => null,
			);
		}

		$local['credits'] = null;
		return $local;
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
		if ( empty( $lic['premium'] ) && empty( $lic['ok'] ) ) {
			$status = isset( $lic['status'] ) ? (string) $lic['status'] : 'missing';
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

		// Optional remote platform (when you later proxy all traffic).
		if ( $license && apply_filters( 'f2f_ai_chatbot_prefer_platform', false ) ) {
			$platform = self::chat_via_platform( $license, $messages, $args );
			if ( ! empty( $platform['ok'] ) ) {
				return $platform;
			}
		}

		$master = self::master_openai_key();
		if ( $master ) {
			return F2F_AI_Chatbot_OpenAI::chat(
				$master,
				self::model(),
				$messages,
				array(
					'max_tokens'  => isset( $args['max_tokens'] ) ? (int) $args['max_tokens'] : 500,
					'temperature' => isset( $args['temperature'] ) ? (float) $args['temperature'] : 0.5,
				)
			);
		}

		// Fallback: try platform if master key not on this WP install.
		if ( $license ) {
			$platform = self::chat_via_platform( $license, $messages, $args );
			if ( ! empty( $platform['ok'] ) ) {
				return $platform;
			}
			return array(
				'ok'    => false,
				'error' => isset( $platform['error'] ) ? $platform['error'] : __( 'AI servisine bağlanılamadı. F2F kurulumunda OpenAI master key veya platform gerekir.', 'f2f-ai-chatbot' ),
			);
		}

		return array(
			'ok'    => false,
			'error' => __( 'Lisans aktif ama AI anahtarı yapılandırılmamış. F2F desteğine yazın.', 'f2f-ai-chatbot' ),
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

		return array(
			'ok'      => true,
			'content' => $reply,
		);
	}
}
