<?php
/**
 * Local license pool — package quota + 1 year premium.
 *
 * @package F2F_AI_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validates keys against the shipped hash→plan map and tracks message usage.
 */
class F2F_AI_Chatbot_License {

	const META_OPTION  = 'f2f_ai_license_meta';
	const PREMIUM_DAYS = 365;
	const KEY_PATTERN  = '/^F2F-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}$/';

	/**
	 * Catalog (fallback labels; limits come from the pool entry).
	 *
	 * @return array<string, array{label:string,messages:int}>
	 */
	public static function plans() {
		return array(
			'starter'  => array(
				'label'    => 'Starter',
				'messages' => 1000,
			),
			'business' => array(
				'label'    => 'Business',
				'messages' => 5000,
			),
			'pro'      => array(
				'label'    => 'Pro',
				'messages' => 15000,
			),
		);
	}

	/**
	 * @return array<string, array{plan:string,label:string,messages:int}>
	 */
	public static function pool() {
		static $pool = null;
		if ( null !== $pool ) {
			return $pool;
		}
		$file = F2F_AI_CHATBOT_PATH . 'includes/license-pool.php';
		$list = file_exists( $file ) ? include $file : array();
		$pool = is_array( $list ) ? $list : array();
		return $pool;
	}

	/**
	 * @deprecated Use pool().
	 * @return array<int, string>
	 */
	public static function pool_hashes() {
		return array_keys( self::pool() );
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
	 * @return array{plan:string,label:string,messages:int}|null
	 */
	public static function package_for_key( $key ) {
		$key = self::normalize( $key );
		if ( ! $key || ! preg_match( self::KEY_PATTERN, $key ) ) {
			return null;
		}
		$hash = self::hash( $key );
		$pool = self::pool();
		if ( empty( $pool[ $hash ] ) || ! is_array( $pool[ $hash ] ) ) {
			return null;
		}
		$entry = $pool[ $hash ];
		$plan  = isset( $entry['plan'] ) ? (string) $entry['plan'] : 'starter';
		$plans = self::plans();
		$label = isset( $entry['label'] ) ? (string) $entry['label'] : ( isset( $plans[ $plan ]['label'] ) ? $plans[ $plan ]['label'] : $plan );
		$msgs  = isset( $entry['messages'] ) ? (int) $entry['messages'] : ( isset( $plans[ $plan ]['messages'] ) ? (int) $plans[ $plan ]['messages'] : 1000 );
		return array(
			'plan'     => $plan,
			'label'    => $label,
			'messages' => max( 1, $msgs ),
		);
	}

	/**
	 * @param string $key Key.
	 * @return bool
	 */
	public static function is_in_pool( $key ) {
		return null !== self::package_for_key( $key );
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

		$pkg = self::package_for_key( $key );
		if ( ! $pkg ) {
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

		// Same key already activated and not expired — keep expiry + usage.
		if ( ! empty( $existing['key_hash'] ) && $existing['key_hash'] === $hash && ! empty( $existing['expires_at'] ) ) {
			$expires = (int) $existing['expires_at'];
			if ( $expires > $now ) {
				// Backfill plan fields if upgrading from older meta.
				if ( empty( $existing['plan'] ) || empty( $existing['messages_limit'] ) ) {
					$existing['plan']           = $pkg['plan'];
					$existing['plan_label']     = $pkg['label'];
					$existing['messages_limit'] = $pkg['messages'];
					if ( ! isset( $existing['messages_used'] ) ) {
						$existing['messages_used'] = 0;
					}
					update_option( self::META_OPTION, $existing, false );
				}
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
			'key_hash'        => $hash,
			'key_masked'      => self::mask( $key ),
			'activated_at'    => $now,
			'expires_at'      => $expires,
			'premium_days'    => self::PREMIUM_DAYS,
			'site_url'        => home_url( '/' ),
			'plan'            => $pkg['plan'],
			'plan_label'      => $pkg['label'],
			'messages_limit'  => $pkg['messages'],
			'messages_used'   => 0,
		);
		update_option( self::META_OPTION, $meta, false );

		return array(
			'ok'         => true,
			'status'     => 'premium',
			'message'    => sprintf(
				/* translators: 1: plan label, 2: message quota, 3: expiry date */
				__( '%1$s paketi aktif — %2$d konuşma hakkı, %3$s tarihine kadar (1 yıl).', 'f2f-ai-chatbot' ),
				$pkg['label'],
				$pkg['messages'],
				wp_date( get_option( 'date_format' ), $expires )
			),
			'expires_at' => $expires,
		);
	}

	/**
	 * @return bool
	 */
	public static function consume_message() {
		$meta = self::meta();
		if ( empty( $meta['key_hash'] ) ) {
			return false;
		}
		$limit = isset( $meta['messages_limit'] ) ? (int) $meta['messages_limit'] : 0;
		$used  = isset( $meta['messages_used'] ) ? (int) $meta['messages_used'] : 0;
		if ( $limit > 0 && $used >= $limit ) {
			return false;
		}
		$meta['messages_used'] = $used + 1;
		update_option( self::META_OPTION, $meta, false );
		return true;
	}

	/**
	 * Current license / premium / quota status.
	 *
	 * @return array<string, mixed>
	 */
	public static function status() {
		$s       = f2f_ai_chatbot_get_settings();
		$license = isset( $s['license_key'] ) ? self::normalize( $s['license_key'] ) : '';
		$meta    = self::meta();
		$now     = time();
		$empty   = array(
			'ok'             => false,
			'license'        => '',
			'status'         => 'missing',
			'message'        => __( 'Lisans anahtarı girilmemiş. Eklenti indirildikten sonra F2F’den aldığınız anahtarı buraya yapıştırın.', 'f2f-ai-chatbot' ),
			'expires_at'     => null,
			'premium'        => false,
			'can_chat'       => false,
			'days_left'      => null,
			'plan'           => '',
			'plan_label'     => '',
			'messages_limit' => null,
			'messages_used'  => null,
			'messages_left'  => null,
		);

		if ( '' === $license ) {
			return $empty;
		}

		$pkg = self::package_for_key( $license );
		if ( ! $pkg ) {
			$empty['license'] = self::mask( $license );
			$empty['status']  = 'invalid';
			$empty['message'] = __( 'Bu anahtar lisans havuzunda yok.', 'f2f-ai-chatbot' );
			return $empty;
		}

		$hash = self::hash( $license );
		if ( empty( $meta['key_hash'] ) || $meta['key_hash'] !== $hash || empty( $meta['expires_at'] ) ) {
			$activated = self::activate( $license );
			$meta      = self::meta();
			if ( empty( $activated['ok'] ) ) {
				$empty['license'] = self::mask( $license );
				$empty['status']  = 'invalid';
				$empty['message'] = $activated['message'];
				return $empty;
			}
		}

		$expires   = (int) $meta['expires_at'];
		$days_left = (int) max( 0, ceil( ( $expires - $now ) / DAY_IN_SECONDS ) );
		$limit     = isset( $meta['messages_limit'] ) ? (int) $meta['messages_limit'] : (int) $pkg['messages'];
		$used      = isset( $meta['messages_used'] ) ? (int) $meta['messages_used'] : 0;
		$left      = max( 0, $limit - $used );
		$plan      = isset( $meta['plan'] ) ? (string) $meta['plan'] : $pkg['plan'];
		$label     = isset( $meta['plan_label'] ) ? (string) $meta['plan_label'] : $pkg['label'];

		if ( $expires <= $now ) {
			return array(
				'ok'             => false,
				'license'        => self::mask( $license ),
				'status'         => 'expired',
				'message'        => sprintf(
					/* translators: %s: expiry date */
					__( 'Premium süresi doldu (%s). Yeni lisans için F2F Bilişim ile iletişime geçin.', 'f2f-ai-chatbot' ),
					wp_date( get_option( 'date_format' ), $expires )
				),
				'expires_at'     => $expires,
				'premium'        => false,
				'can_chat'       => false,
				'days_left'      => 0,
				'plan'           => $plan,
				'plan_label'     => $label,
				'messages_limit' => $limit,
				'messages_used'  => $used,
				'messages_left'  => $left,
			);
		}

		if ( $left <= 0 ) {
			return array(
				'ok'             => false,
				'license'        => self::mask( $license ),
				'status'         => 'exhausted',
				'message'        => sprintf(
					/* translators: 1: plan, 2: used, 3: limit */
					__( '%1$s paket kotası doldu (%2$d / %3$d konuşma). Üst paket veya ek kontör için F2F ile iletişime geçin.', 'f2f-ai-chatbot' ),
					$label,
					$used,
					$limit
				),
				'expires_at'     => $expires,
				'premium'        => true,
				'can_chat'       => false,
				'days_left'      => $days_left,
				'plan'           => $plan,
				'plan_label'     => $label,
				'messages_limit' => $limit,
				'messages_used'  => $used,
				'messages_left'  => 0,
			);
		}

		return array(
			'ok'             => true,
			'license'        => self::mask( $license ),
			'status'         => 'premium',
			'message'        => sprintf(
				/* translators: 1: plan, 2: left, 3: limit, 4: expiry, 5: days */
				__( '%1$s — kalan konuşma %2$d / %3$d · süre %4$s (%5$d gün).', 'f2f-ai-chatbot' ),
				$label,
				$left,
				$limit,
				wp_date( get_option( 'date_format' ), $expires ),
				$days_left
			),
			'expires_at'     => $expires,
			'premium'        => true,
			'can_chat'       => true,
			'days_left'      => $days_left,
			'plan'           => $plan,
			'plan_label'     => $label,
			'messages_limit' => $limit,
			'messages_used'  => $used,
			'messages_left'  => $left,
			'credits'        => $left,
		);
	}

	/**
	 * @return bool
	 */
	public static function is_premium() {
		$st = self::status();
		return ! empty( $st['premium'] );
	}

	/**
	 * @return bool
	 */
	public static function can_chat() {
		$st = self::status();
		return ! empty( $st['can_chat'] );
	}
}
