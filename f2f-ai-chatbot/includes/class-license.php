<?php
/**
 * Local license pool — 1 year premium after a valid key is activated.
 *
 * @package F2F_AI_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validates keys against the shipped hash pool (no plaintext keys in the plugin).
 */
class F2F_AI_Chatbot_License {

	const META_OPTION   = 'f2f_ai_license_meta';
	const PREMIUM_DAYS  = 365;
	const KEY_PATTERN   = '/^F2F-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}$/';

	/**
	 * @return array<int, string>
	 */
	public static function pool_hashes() {
		static $hashes = null;
		if ( null !== $hashes ) {
			return $hashes;
		}
		$file = F2F_AI_CHATBOT_PATH . 'includes/license-pool.php';
		$list = file_exists( $file ) ? include $file : array();
		$hashes = is_array( $list ) ? $list : array();
		return $hashes;
	}

	/**
	 * @param string $key Raw key.
	 * @return string
	 */
	public static function normalize( $key ) {
		$key = strtoupper( trim( (string) $key ) );
		$key = preg_replace( '/\s+/', '', $key );
		return is_string( $key ) ? $key : '';
	}

	/**
	 * @param string $key Normalized or raw key.
	 * @return string
	 */
	public static function hash( $key ) {
		return hash( 'sha256', self::normalize( $key ) );
	}

	/**
	 * @param string $key Key.
	 * @return string Masked display.
	 */
	public static function mask( $key ) {
		$key = self::normalize( $key );
		if ( strlen( $key ) < 12 ) {
			return 'F2F-****';
		}
		return substr( $key, 0, 4 ) . '-****-****-****-' . substr( $key, -4 );
	}

	/**
	 * @param string $key Key.
	 * @return bool
	 */
	public static function is_in_pool( $key ) {
		$key = self::normalize( $key );
		if ( ! $key || ! preg_match( self::KEY_PATTERN, $key ) ) {
			return false;
		}
		$hash = self::hash( $key );
		return in_array( $hash, self::pool_hashes(), true );
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function meta() {
		$meta = get_option( self::META_OPTION, array() );
		return is_array( $meta ) ? $meta : array();
	}

	/**
	 * Activate or refresh meta when a valid key is saved.
	 *
	 * @param string $key License key from settings.
	 * @return array{ok:bool, status:string, message:string, expires_at?:int}
	 */
	public static function activate( $key ) {
		$key = self::normalize( $key );

		if ( '' === $key ) {
			delete_option( self::META_OPTION );
			return array(
				'ok'      => false,
				'status'  => 'missing',
				'message' => __( 'Lisans anahtarı boş. Satın alma sonrası size verilen F2F anahtarını girin.', 'f2f-ai-chatbot' ),
			);
		}

		if ( ! self::is_in_pool( $key ) ) {
			delete_option( self::META_OPTION );
			return array(
				'ok'      => false,
				'status'  => 'invalid',
				'message' => __( 'Lisans anahtarı geçersiz. F2F Bilişim’den aldığınız anahtarı kontrol edin.', 'f2f-ai-chatbot' ),
			);
		}

		$existing = self::meta();
		$hash     = self::hash( $key );
		$now      = time();

		// Same key already activated — keep original expiry unless expired (then renew 1 year).
		if ( ! empty( $existing['key_hash'] ) && $existing['key_hash'] === $hash && ! empty( $existing['expires_at'] ) ) {
			$expires = (int) $existing['expires_at'];
			if ( $expires > $now ) {
				return array(
					'ok'         => true,
					'status'     => 'premium',
					'message'    => __( 'Premium lisans aktif.', 'f2f-ai-chatbot' ),
					'expires_at' => $expires,
				);
			}
		}

		$expires = $now + ( self::PREMIUM_DAYS * DAY_IN_SECONDS );
		$meta    = array(
			'key_hash'      => $hash,
			'key_masked'    => self::mask( $key ),
			'activated_at'  => $now,
			'expires_at'    => $expires,
			'premium_days'  => self::PREMIUM_DAYS,
			'site_url'      => home_url( '/' ),
		);
		update_option( self::META_OPTION, $meta, false );

		return array(
			'ok'         => true,
			'status'     => 'premium',
			'message'    => sprintf(
				/* translators: %s: expiry date */
				__( 'Premium aktif — %s tarihine kadar geçerli (1 yıl).', 'f2f-ai-chatbot' ),
				wp_date( get_option( 'date_format' ), $expires )
			),
			'expires_at' => $expires,
		);
	}

	/**
	 * Current license / premium status for admin + gateway.
	 *
	 * @return array{ok:bool, license:string, status:string, message:string, expires_at:?int, premium:bool, days_left:?int}
	 */
	public static function status() {
		$s       = f2f_ai_chatbot_get_settings();
		$license = isset( $s['license_key'] ) ? self::normalize( $s['license_key'] ) : '';
		$meta    = self::meta();
		$now     = time();

		if ( '' === $license ) {
			return array(
				'ok'         => false,
				'license'    => '',
				'status'     => 'missing',
				'message'    => __( 'Lisans anahtarı girilmemiş. Eklenti indirildikten sonra F2F’den aldığınız anahtarı buraya yapıştırın.', 'f2f-ai-chatbot' ),
				'expires_at' => null,
				'premium'    => false,
				'days_left'  => null,
			);
		}

		if ( ! self::is_in_pool( $license ) ) {
			return array(
				'ok'         => false,
				'license'    => self::mask( $license ),
				'status'     => 'invalid',
				'message'    => __( 'Bu anahtar lisans havuzunda yok.', 'f2f-ai-chatbot' ),
				'expires_at' => null,
				'premium'    => false,
				'days_left'  => null,
			);
		}

		$hash = self::hash( $license );
		if ( empty( $meta['key_hash'] ) || $meta['key_hash'] !== $hash || empty( $meta['expires_at'] ) ) {
			// Valid key in settings but not activated yet — activate now.
			$activated = self::activate( $license );
			$meta      = self::meta();
			if ( empty( $activated['ok'] ) ) {
				return array(
					'ok'         => false,
					'license'    => self::mask( $license ),
					'status'     => 'invalid',
					'message'    => $activated['message'],
					'expires_at' => null,
					'premium'    => false,
					'days_left'  => null,
				);
			}
		}

		$expires   = (int) $meta['expires_at'];
		$days_left = (int) max( 0, ceil( ( $expires - $now ) / DAY_IN_SECONDS ) );

		if ( $expires <= $now ) {
			return array(
				'ok'         => false,
				'license'    => self::mask( $license ),
				'status'     => 'expired',
				'message'    => sprintf(
					/* translators: %s: expiry date */
					__( 'Premium süresi doldu (%s). Yeni lisans için F2F Bilişim ile iletişime geçin.', 'f2f-ai-chatbot' ),
					wp_date( get_option( 'date_format' ), $expires )
				),
				'expires_at' => $expires,
				'premium'    => false,
				'days_left'  => 0,
			);
		}

		return array(
			'ok'         => true,
			'license'    => self::mask( $license ),
			'status'     => 'premium',
			'message'    => sprintf(
				/* translators: 1: expiry date, 2: days left */
				__( 'Premium aktif — %1$s tarihine kadar (%2$d gün kaldı).', 'f2f-ai-chatbot' ),
				wp_date( get_option( 'date_format' ), $expires ),
				$days_left
			),
			'expires_at' => $expires,
			'premium'    => true,
			'days_left'  => $days_left,
		);
	}

	/**
	 * @return bool
	 */
	public static function is_premium() {
		$st = self::status();
		return ! empty( $st['premium'] );
	}
}
