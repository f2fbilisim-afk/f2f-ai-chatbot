<?php
/**
 * OpenAI API client.
 *
 * @package F2F_AI_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Thin OpenAI Chat Completions client.
 */
class F2F_AI_Chatbot_OpenAI {

	/**
	 * Chat Completions endpoint.
	 *
	 * @var string
	 */
	const ENDPOINT = 'https://api.openai.com/v1/chat/completions';

	/**
	 * Send a chat completion request.
	 *
	 * @param string               $api_key  OpenAI API key.
	 * @param string               $model    Model id.
	 * @param array<int, array>    $messages Conversation messages.
	 * @param array<string, mixed> $args     Extra options (max_tokens, temperature).
	 * @return array{ok:bool, content?:string, error?:string, usage?:array}
	 */
	public static function chat( $api_key, $model, $messages, $args = array() ) {
		$api_key = trim( (string) $api_key );
		if ( '' === $api_key ) {
			return array(
				'ok'    => false,
				'error' => __( 'OpenAI API anahtarı tanımlı değil. Lütfen eklenti ayarlarından ekleyin.', 'f2f-ai-chatbot' ),
			);
		}

		$body = array(
			'model'       => $model ? $model : 'gpt-4o-mini',
			'messages'    => array_values( $messages ),
			'max_tokens'  => isset( $args['max_tokens'] ) ? (int) $args['max_tokens'] : 500,
			'temperature' => isset( $args['temperature'] ) ? (float) $args['temperature'] : 0.7,
		);

		$response = wp_remote_post(
			self::ENDPOINT,
			array(
				'timeout' => 45,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'ok'    => false,
				'error' => sprintf(
					/* translators: %s: error message */
					__( 'OpenAI bağlantı hatası: %s', 'f2f-ai-chatbot' ),
					$response->get_error_message()
				),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );

		if ( $code < 200 || $code >= 300 ) {
			$message = __( 'OpenAI isteği başarısız oldu.', 'f2f-ai-chatbot' );
			if ( is_array( $data ) && isset( $data['error']['message'] ) ) {
				$message = (string) $data['error']['message'];
			}
			return array(
				'ok'    => false,
				'error' => $message,
			);
		}

		$content = '';
		if ( is_array( $data ) && isset( $data['choices'][0]['message']['content'] ) ) {
			$content = trim( (string) $data['choices'][0]['message']['content'] );
		}

		if ( '' === $content ) {
			return array(
				'ok'    => false,
				'error' => __( 'OpenAI boş yanıt döndürdü.', 'f2f-ai-chatbot' ),
			);
		}

		$result = array(
			'ok'      => true,
			'content' => $content,
		);

		if ( isset( $data['usage'] ) && is_array( $data['usage'] ) ) {
			$result['usage'] = $data['usage'];
		}

		return $result;
	}
}
