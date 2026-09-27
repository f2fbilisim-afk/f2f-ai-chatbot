<?php
/**
 * Chat gateway — license unlocks AI; OpenAI via local master key OR F2F platform hub.
 *
 * Seller (hub) site:  F2F_AI_MASTER_OPENAI_KEY in wp-config → serves /wp-json/f2f-ai-platform/v1/*
 * Customer site:      only license key → proxies chat to hub (no OpenAI in wp-config)
 *
 * @package F2F_AI_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Routes chat when premium license is active.
 */
class F2F_AI_Chatbot_Gateway {

	/**
	 * Master OpenAI key — only from wp-config (seller / hub).
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
	 * Chat: local master key first, else F2F platform hub.
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
				$st                = F2F_AI_Chatbot_License::status( true );
				$result['credits'] = isset( $st['messages_left'] ) ? (int) $st['messages_left'] : null;
			}
			return $result;
		}

		// Customer site: no local OpenAI key → F2F hub.
		$s       = f2f_ai_chatbot_get_settings();
		$license = isset( $s['license_key'] ) ? F2F_AI_Chatbot_License::normalize( (string) $s['license_key'] ) : '';
		if ( ! $license ) {
			return array(
				'ok'    => false,
				'error' => __( 'Lisans anahtarı gerekli.', 'f2f-ai-chatbot' ),
			);
		}

		if ( get_transient( 'f2f_ai_platform_down' ) ) {
			return array(
				'ok'    => false,
				'error' => __( 'F2F platform geçici olarak yanıt vermiyor. Bir süre sonra tekrar deneyin.', 'f2f-ai-chatbot' ),
			);
		}

		$result = self::chat_via_platform( $license, $messages, $args );
		if ( ! empty( $result['ok'] ) ) {
			F2F_AI_Chatbot_License::consume_message();
			$st                = F2F_AI_Chatbot_License::status( true );
			$result['credits'] = isset( $st['messages_left'] ) ? (int) $st['messages_left'] : null;
			return $result;
		}

		$err = isset( $result['error'] ) ? (string) $result['error'] : '';
		return array(
			'ok'    => false,
			'error' => $err
				? $err
				: __( 'F2F platform sohbet yanıtı alınamadı. F2F Bilişim ile iletişime geçin.', 'f2f-ai-chatbot' ),
		);
	}

	/**
	 * @param string               $license  License.
	 * @param array<int, array>    $messages Messages.
	 * @param array<string, mixed> $args     Args.
	 * @return array<string, mixed>
	 */
	private static function chat_via_platform( $license, $messages, $args ) {
		$url = F2F_AI_Chatbot_Platform::chat_endpoint();
		$res = wp_remote_post(
			$url,
			array(
				'timeout'     => 25,
				'redirection' => 2,
				'headers'     => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $license,
					'Accept'        => 'application/json',
					'User-Agent'    => 'F2F-AI-Chatbot/' . F2F_AI_CHATBOT_VERSION,
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
			set_transient( 'f2f_ai_platform_down', 1, 10 * MINUTE_IN_SECONDS );
			return array(
				'ok'    => false,
				'error' => sprintf(
					/* translators: %s: error */
					__( 'Platform bağlantı hatası: %s', 'f2f-ai-chatbot' ),
					$res->get_error_message()
				),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $res );
		$raw  = (string) wp_remote_retrieve_body( $res );
		$data = json_decode( $raw, true );

		// Hub may return HTTP 200 with ok:false (avoids Cloudflare swallowing 502).
		if ( is_array( $data ) && array_key_exists( 'ok', $data ) && empty( $data['ok'] ) ) {
			$msg = '';
			if ( ! empty( $data['error'] ) ) {
				$msg = (string) $data['error'];
			} elseif ( ! empty( $data['message'] ) ) {
				$msg = (string) $data['message'];
			}
			return array(
				'ok'    => false,
				'error' => $msg ? $msg : __( 'Platform sohbet hatası.', 'f2f-ai-chatbot' ),
			);
		}

		if ( $code < 200 || $code >= 300 || ! is_array( $data ) ) {
			if ( $code >= 500 || 0 === $code || 404 === $code ) {
				set_transient( 'f2f_ai_platform_down', 1, 10 * MINUTE_IN_SECONDS );
			}
			$msg = sprintf(
				/* translators: 1: http code */
				__( 'Platform yanıt vermedi (HTTP %d). Hub sitede F2F_AI_MASTER_OPENAI_KEY ve eklenti güncel mi?', 'f2f-ai-chatbot' ),
				$code ? $code : 0
			);
			if ( is_array( $data ) ) {
				if ( ! empty( $data['message'] ) ) {
					$msg = (string) $data['message'];
				} elseif ( ! empty( $data['error'] ) && is_string( $data['error'] ) ) {
					$msg = (string) $data['error'];
				}
			} elseif ( is_string( $raw ) && false !== stripos( $raw, 'error code: 502' ) ) {
				$msg = __( 'Hub OpenAI çağrısı başarısız (Cloudflare 502). f2fbilisim.com’da OpenAI anahtarı / bakiyesi / api.openai.com çıkışını kontrol edin.', 'f2f-ai-chatbot' );
			}
			return array(
				'ok'    => false,
				'error' => $msg,
			);
		}

		$reply = '';
		if ( ! empty( $data['reply'] ) ) {
			$reply = trim( (string) $data['reply'] );
		} elseif ( ! empty( $data['content'] ) ) {
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
