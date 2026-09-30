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
				'error' => __( 'OpenAI API anahtarı tanımlı değil (F2F master / platform).', 'f2f-ai-chatbot' ),
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
				'timeout'   => 15,
				'sslverify' => true,
				'headers'   => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'      => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			$err = $response->get_error_message();
			return array(
				'ok'    => false,
				'error' => sprintf(
					/* translators: %s: error message */
					__( 'OpenAI’ye ulaşılamadı (%s). Hosting’in api.openai.com çıkışına izin verdiğini kontrol edin.', 'f2f-ai-chatbot' ),
					$err
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

	/**
	 * Quick connectivity probe (models list).
	 *
	 * @param string $api_key Key.
	 * @return array{ok:bool, error?:string, latency_ms?:int, http?:int}
	 */
	public static function ping( $api_key ) {
		$api_key = trim( (string) $api_key );
		if ( '' === $api_key ) {
			return array(
				'ok'    => false,
				'error' => 'empty_key',
			);
		}
		$t0  = microtime( true );
		$res = wp_remote_get(
			'https://api.openai.com/v1/models',
			array(
				'timeout'   => 8,
				'sslverify' => true,
				'headers'   => array(
					'Authorization' => 'Bearer ' . $api_key,
				),
			)
		);
		$ms = (int) round( ( microtime( true ) - $t0 ) * 1000 );
		if ( is_wp_error( $res ) ) {
			return array(
				'ok'         => false,
				'error'      => $res->get_error_message(),
				'latency_ms' => $ms,
			);
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		if ( $code < 200 || $code >= 300 ) {
			$body = json_decode( (string) wp_remote_retrieve_body( $res ), true );
			$msg  = is_array( $body ) && isset( $body['error']['message'] )
				? (string) $body['error']['message']
				: ( 'HTTP ' . $code );
			return array(
				'ok'         => false,
				'error'      => $msg,
				'latency_ms' => $ms,
				'http'       => $code,
			);
		}
		return array(
			'ok'         => true,
			'latency_ms' => $ms,
		);
	}
}
