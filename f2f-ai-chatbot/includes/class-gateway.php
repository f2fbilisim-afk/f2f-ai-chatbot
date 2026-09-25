<?php
/**
 * Chat gateway — premium license unlocks AI; OpenAI key only from wp-config.
 *
 * Flow: ziyaretçi → eklenti (lisans + kota) → F2F_AI_MASTER_OPENAI_KEY → OpenAI
 * Müşteri paneline OpenAI anahtarı yok. Harici "platform" sunucusu yok.
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
	 * Master OpenAI key — only from wp-config (seller / F2F project API).
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
	 * Chat completion via master OpenAI key in wp-config.
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
		if ( ! $master ) {
			return array(
				'ok'    => false,
				'error' => __( 'Lisans aktif ama AI anahtarı yapılandırılmamış. Bu sitenin wp-config.php dosyasına define(\'F2F_AI_MASTER_OPENAI_KEY\', \'sk-...\'); ekleyin.', 'f2f-ai-chatbot' ),
			);
		}

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
}
