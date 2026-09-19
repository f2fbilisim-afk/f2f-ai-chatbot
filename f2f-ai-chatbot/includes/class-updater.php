<?php
/**
 * Self-hosted plugin updates (WP admin → Eklentiler → güncelle).
 *
 * Reads a remote JSON manifest. Host ZIP + JSON on f2fbilisim.com (or override URL).
 *
 * @package F2F_AI_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Injects update info into WordPress core update checks.
 */
class F2F_AI_Chatbot_Updater {

	const CACHE_KEY = 'f2f_ai_chatbot_update_info';
	const CACHE_TTL = HOUR_IN_SECONDS * 6;

	/**
	 * Default public manifest URL (override with F2F_AI_UPDATE_JSON).
	 *
	 * @return string
	 */
	public static function manifest_url() {
		if ( defined( 'F2F_AI_UPDATE_JSON' ) && F2F_AI_UPDATE_JSON ) {
			return (string) F2F_AI_UPDATE_JSON;
		}
		/**
		 * Filter update manifest URL.
		 *
		 * @param string $url URL.
		 */
		return (string) apply_filters(
			'f2f_ai_chatbot_update_json',
			'https://www.f2fbilisim.com/updates/f2f-ai-chatbot.json'
		);
	}

	/**
	 * @return self
	 */
	public static function instance() {
		static $inst = null;
		if ( null === $inst ) {
			$inst = new self();
		}
		return $inst;
	}

	private function __construct() {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'inject_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugins_api' ), 10, 3 );
		add_action( 'upgrader_process_complete', array( $this, 'clear_cache' ), 10, 2 );
		add_filter( 'plugin_row_meta', array( $this, 'row_meta' ), 10, 2 );
	}

	/**
	 * @param array<int, string> $links Links.
	 * @param string             $file  Plugin file.
	 * @return array<int, string>
	 */
	public function row_meta( $links, $file ) {
		if ( plugin_basename( F2F_AI_CHATBOT_FILE ) !== $file ) {
			return $links;
		}
		$links[] = '<span>' . esc_html__( 'Otomatik güncelleme: F2F sunucusu', 'f2f-ai-chatbot' ) . '</span>';
		return $links;
	}

	/**
	 * @param mixed $upgrader Upgrader.
	 * @param array $options  Options.
	 */
	public function clear_cache( $upgrader = null, $options = array() ) {
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * Fetch remote manifest (cached).
	 *
	 * @return array<string, mixed>|null
	 */
	public static function remote_info() {
		$cached = get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) && ! empty( $cached['version'] ) ) {
			return $cached;
		}

		$url = self::manifest_url();
		if ( ! $url ) {
			return null;
		}

		$res = wp_remote_get(
			$url,
			array(
				'timeout' => 12,
				'headers' => array(
					'Accept' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $res ) ) {
			return null;
		}

		$code = (int) wp_remote_retrieve_response_code( $res );
		if ( $code < 200 || $code >= 300 ) {
			return null;
		}

		$data = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( ! is_array( $data ) || empty( $data['version'] ) || empty( $data['download_url'] ) ) {
			return null;
		}

		$info = array(
			'name'           => isset( $data['name'] ) ? (string) $data['name'] : 'F2F AI Chatbot',
			'slug'           => 'f2f-ai-chatbot',
			'version'        => (string) $data['version'],
			'download_url'   => esc_url_raw( (string) $data['download_url'] ),
			'requires'       => isset( $data['requires'] ) ? (string) $data['requires'] : '6.0',
			'tested'         => isset( $data['tested'] ) ? (string) $data['tested'] : '6.7',
			'requires_php'   => isset( $data['requires_php'] ) ? (string) $data['requires_php'] : '7.4',
			'homepage'       => isset( $data['homepage'] ) ? esc_url_raw( (string) $data['homepage'] ) : 'https://www.f2fbilisim.com',
			'changelog'      => isset( $data['changelog'] ) ? wp_kses_post( (string) $data['changelog'] ) : '',
			'last_updated'   => isset( $data['last_updated'] ) ? (string) $data['last_updated'] : '',
			'icons'          => isset( $data['icons'] ) && is_array( $data['icons'] ) ? $data['icons'] : array(),
		);

		set_transient( self::CACHE_KEY, $info, self::CACHE_TTL );
		return $info;
	}

	/**
	 * @param object $transient Transient.
	 * @return object
	 */
	public function inject_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}
		if ( empty( $transient->checked ) || ! is_array( $transient->checked ) ) {
			return $transient;
		}

		$plugin = plugin_basename( F2F_AI_CHATBOT_FILE );
		$remote = self::remote_info();
		if ( ! $remote ) {
			return $transient;
		}

		$current = F2F_AI_CHATBOT_VERSION;
		if ( version_compare( $remote['version'], $current, '<=' ) ) {
			// Ensure we don't leave a stale update offer.
			if ( isset( $transient->response[ $plugin ] ) ) {
				unset( $transient->response[ $plugin ] );
			}
			$transient->no_update[ $plugin ] = (object) array(
				'id'            => $plugin,
				'slug'          => 'f2f-ai-chatbot',
				'plugin'        => $plugin,
				'new_version'   => $current,
				'url'           => $remote['homepage'],
				'package'       => '',
				'icons'         => $remote['icons'],
				'banners'       => array(),
				'banners_rtl'   => array(),
				'tested'        => $remote['tested'],
				'requires_php'  => $remote['requires_php'],
				'compatibility' => new stdClass(),
			);
			return $transient;
		}

		$transient->response[ $plugin ] = (object) array(
			'id'            => $plugin,
			'slug'          => 'f2f-ai-chatbot',
			'plugin'        => $plugin,
			'new_version'   => $remote['version'],
			'url'           => $remote['homepage'],
			'package'       => $remote['download_url'],
			'icons'         => $remote['icons'],
			'banners'       => array(),
			'banners_rtl'   => array(),
			'tested'        => $remote['tested'],
			'requires'      => $remote['requires'],
			'requires_php'  => $remote['requires_php'],
			'compatibility' => new stdClass(),
		);

		return $transient;
	}

	/**
	 * Plugin details modal in WP admin.
	 *
	 * @param mixed  $result Result.
	 * @param string $action Action.
	 * @param object $args   Args.
	 * @return mixed
	 */
	public function plugins_api( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}
		if ( empty( $args->slug ) || 'f2f-ai-chatbot' !== $args->slug ) {
			return $result;
		}

		$remote = self::remote_info();
		if ( ! $remote ) {
			return $result;
		}

		return (object) array(
			'name'           => $remote['name'],
			'slug'           => 'f2f-ai-chatbot',
			'version'        => $remote['version'],
			'author'         => '<a href="https://www.f2fbilisim.com">F2F Bilişim</a>',
			'homepage'       => $remote['homepage'],
			'requires'       => $remote['requires'],
			'tested'         => $remote['tested'],
			'requires_php'   => $remote['requires_php'],
			'download_link'  => $remote['download_url'],
			'trunk'          => $remote['download_url'],
			'last_updated'   => $remote['last_updated'],
			'sections'       => array(
				'description' => __( 'F2F lisanslı AI chatbot — keşif, lead, sohbet, WhatsApp ve e-posta bildirimi.', 'f2f-ai-chatbot' ),
				'changelog'   => $remote['changelog'] ? $remote['changelog'] : '<p>' . esc_html( $remote['version'] ) . '</p>',
			),
		);
	}
}
