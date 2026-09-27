<?php
/**
 * Self-hosted / GitHub plugin updates (WP admin → Eklentiler → güncelle).
 *
 * Manifest URL resolution order:
 * 1. F2F_AI_UPDATE_JSON constant
 * 2. F2F_AI_GITHUB_REPO constant → raw / jsDelivr
 * 3. Default: jsDelivr then raw.githubusercontent.com
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
	const CACHE_TTL = HOUR_IN_SECONDS * 2;
	const LAST_ERR  = 'f2f_ai_chatbot_update_last_error';

	/**
	 * Candidate manifest URLs (first success wins).
	 *
	 * @return array<int, string>
	 */
	public static function manifest_urls() {
		if ( defined( 'F2F_AI_UPDATE_JSON' ) && F2F_AI_UPDATE_JSON ) {
			return array( (string) F2F_AI_UPDATE_JSON );
		}

		$repo = 'f2fbilisim-afk/f2f-ai-chatbot';
		$branch = 'main';
		if ( defined( 'F2F_AI_GITHUB_REPO' ) && F2F_AI_GITHUB_REPO ) {
			$repo = trim( (string) F2F_AI_GITHUB_REPO, '/' );
		}
		if ( defined( 'F2F_AI_GITHUB_BRANCH' ) && F2F_AI_GITHUB_BRANCH ) {
			$branch = (string) F2F_AI_GITHUB_BRANCH;
		}

		$urls = array(
			'https://cdn.jsdelivr.net/gh/' . $repo . '@' . rawurlencode( $branch ) . '/updates/f2f-ai-chatbot.json',
			'https://raw.githubusercontent.com/' . $repo . '/' . rawurlencode( $branch ) . '/updates/f2f-ai-chatbot.json',
		);

		/**
		 * Filter update manifest URL list.
		 *
		 * @param array<int, string> $urls URLs.
		 */
		$filtered = apply_filters( 'f2f_ai_chatbot_update_json_urls', $urls );
		return is_array( $filtered ) ? array_values( array_filter( array_map( 'strval', $filtered ) ) ) : $urls;
	}

	/**
	 * Primary URL (for display).
	 *
	 * @return string
	 */
	public static function manifest_url() {
		$urls = self::manifest_urls();
		return isset( $urls[0] ) ? $urls[0] : '';
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
		add_filter( 'site_transient_update_plugins', array( $this, 'inject_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugins_api' ), 10, 3 );
		add_action( 'upgrader_process_complete', array( $this, 'clear_cache' ), 10, 2 );
		add_filter( 'plugin_row_meta', array( $this, 'row_meta' ), 10, 2 );
		add_action( 'after_plugin_row_' . plugin_basename( F2F_AI_CHATBOT_FILE ), array( $this, 'plugin_row_notice' ), 10, 2 );
		add_action( 'wp_ajax_f2f_ai_check_update', array( $this, 'ajax_check_update' ) );
		add_action( 'admin_notices', array( $this, 'admin_notice_update' ) );
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
		$links[] = '<span>' . esc_html__( 'Otomatik güncelleme: GitHub / F2F', 'f2f-ai-chatbot' ) . '</span>';
		return $links;
	}

	/**
	 * Extra row under plugin with force-check control.
	 *
	 * @param string               $file Plugin file.
	 * @param array<string, mixed> $data Plugin data.
	 */
	public function plugin_row_notice( $file, $data ) {
		if ( ! current_user_can( 'update_plugins' ) ) {
			return;
		}
		$remote  = self::remote_info();
		$current = F2F_AI_CHATBOT_VERSION;
		$err     = get_transient( self::LAST_ERR );
		$msg     = '';
		if ( $remote && version_compare( $remote['version'], $current, '>' ) ) {
			$msg = sprintf(
				/* translators: 1: current 2: remote */
				__( 'Yeni sürüm hazır: %1$s → %2$s. Yukarıdaki güncelleme bağlantısını kullanın veya “Kontrol et”e basın.', 'f2f-ai-chatbot' ),
				$current,
				$remote['version']
			);
		} elseif ( $remote ) {
			$msg = sprintf(
				/* translators: 1: current 2: remote */
				__( 'Güncel (%1$s). Uzak sürüm: %2$s.', 'f2f-ai-chatbot' ),
				$current,
				$remote['version']
			);
		} elseif ( $err ) {
			$msg = sprintf(
				/* translators: %s: error */
				__( 'Güncelleme kontrolü başarısız: %s', 'f2f-ai-chatbot' ),
				(string) $err
			);
		} else {
			$msg = __( 'Uzak sürüm bilgisi alınamadı. “Kontrol et” ile tekrar deneyin.', 'f2f-ai-chatbot' );
		}

		$nonce = wp_create_nonce( 'f2f_ai_check_update' );
		echo '<tr class="plugin-update-tr"><td colspan="4" class="plugin-update colspanchange"><div class="update-message notice inline notice-warning notice-alt" style="margin:0.5em 0;">';
		echo '<p style="margin:0.4em 0;"><strong>F2F AI Chatbot</strong> — ' . esc_html( $msg ) . ' ';
		echo '<button type="button" class="button button-small" id="f2f-check-update-btn" data-nonce="' . esc_attr( $nonce ) . '">' . esc_html__( 'Şimdi kontrol et', 'f2f-ai-chatbot' ) . '</button> ';
		echo '<span id="f2f-check-update-status"></span></p></div></td></tr>';
		echo '<script>(function(){var b=document.getElementById("f2f-check-update-btn");if(!b||b._f2f)return;b._f2f=1;b.addEventListener("click",function(){var s=document.getElementById("f2f-check-update-status");s.textContent="…";var fd=new FormData();fd.append("action","f2f_ai_check_update");fd.append("nonce",b.getAttribute("data-nonce"));fetch(ajaxurl,{method:"POST",credentials:"same-origin",body:fd}).then(function(r){return r.json()}).then(function(d){if(d&&d.success){s.textContent=d.data&&d.data.message?d.data.message:"OK";if(d.data&&d.data.reload){location.reload()}}else{s.textContent=(d&&d.data&&d.data.message)||"Hata"}}).catch(function(){s.textContent="İstek başarısız"})})}());</script>';
	}

	/**
	 * Dashboard notice when update available.
	 */
	public function admin_notice_update() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! in_array( $screen->id, array( 'plugins', 'plugins-network', 'dashboard', 'update-core' ), true ) ) {
			return;
		}
		$remote = self::remote_info();
		if ( ! $remote || version_compare( $remote['version'], F2F_AI_CHATBOT_VERSION, '<=' ) ) {
			return;
		}
		$url = self_admin_url( 'update-core.php' );
		echo '<div class="notice notice-info"><p><strong>F2F AI Chatbot</strong> ';
		echo esc_html(
			sprintf(
				/* translators: 1: current 2: new */
				__( '%1$s → %2$s güncellemesi hazır.', 'f2f-ai-chatbot' ),
				F2F_AI_CHATBOT_VERSION,
				$remote['version']
			)
		);
		echo ' <a href="' . esc_url( $url ) . '">' . esc_html__( 'Güncellemelere git', 'f2f-ai-chatbot' ) . '</a></p></div>';
	}

	/**
	 * AJAX: clear cache, fetch remote, force WP plugin update check.
	 */
	public function ajax_check_update() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
		check_ajax_referer( 'f2f_ai_check_update', 'nonce' );

		self::clear_cache_static();
		$remote = self::remote_info( true );
		if ( ! $remote ) {
			$err = get_transient( self::LAST_ERR );
			wp_send_json_error(
				array(
					'message' => $err
						? (string) $err
						: __( 'Manifest okunamadı (GitHub / jsDelivr engelli olabilir).', 'f2f-ai-chatbot' ),
				)
			);
		}

		delete_site_transient( 'update_plugins' );
		if ( function_exists( 'wp_update_plugins' ) ) {
			wp_update_plugins();
		}

		$available = version_compare( $remote['version'], F2F_AI_CHATBOT_VERSION, '>' );
		wp_send_json_success(
			array(
				'current'  => F2F_AI_CHATBOT_VERSION,
				'remote'   => $remote['version'],
				'available'=> $available,
				'reload'   => true,
				'message'  => $available
					? sprintf(
						/* translators: %s: version */
						__( 'Yeni sürüm bulundu: %s — sayfa yenileniyor…', 'f2f-ai-chatbot' ),
						$remote['version']
					)
					: sprintf(
						/* translators: %s: version */
						__( 'Zaten güncel (uzak: %s).', 'f2f-ai-chatbot' ),
						$remote['version']
					),
			)
		);
	}

	/**
	 * @param mixed $upgrader Upgrader.
	 * @param array $options  Options.
	 */
	public function clear_cache( $upgrader = null, $options = array() ) {
		self::clear_cache_static();
	}

	/**
	 * @return void
	 */
	public static function clear_cache_static() {
		delete_transient( self::CACHE_KEY );
		delete_transient( self::CACHE_KEY . '_fail' );
		delete_transient( self::LAST_ERR );
	}

	/**
	 * Fetch remote manifest (cached).
	 *
	 * @param bool $force Bypass cache.
	 * @return array<string, mixed>|null
	 */
	public static function remote_info( $force = false ) {
		if ( ! $force ) {
			$cached = get_transient( self::CACHE_KEY );
			if ( is_array( $cached ) && ! empty( $cached['version'] ) ) {
				return $cached;
			}
			if ( get_transient( self::CACHE_KEY . '_fail' ) ) {
				return null;
			}
		}

		$urls = self::manifest_urls();
		if ( ! $urls ) {
			return null;
		}

		$last_err = '';
		foreach ( $urls as $url ) {
			$res = wp_remote_get(
				$url,
				array(
					'timeout'     => 8,
					'redirection' => 3,
					'headers'     => array(
						'Accept'     => 'application/json',
						'User-Agent' => 'F2F-AI-Chatbot/' . F2F_AI_CHATBOT_VERSION . '; WordPress/' . get_bloginfo( 'version' ),
					),
				)
			);

			if ( is_wp_error( $res ) ) {
				$last_err = $url . ' → ' . $res->get_error_message();
				continue;
			}

			$code = (int) wp_remote_retrieve_response_code( $res );
			if ( $code < 200 || $code >= 300 ) {
				$last_err = $url . ' → HTTP ' . $code;
				continue;
			}

			$data = json_decode( (string) wp_remote_retrieve_body( $res ), true );
			if ( ! is_array( $data ) || empty( $data['version'] ) || empty( $data['download_url'] ) ) {
				$last_err = $url . ' → geçersiz JSON';
				continue;
			}

			$info = array(
				'name'         => isset( $data['name'] ) ? (string) $data['name'] : 'F2F AI Chatbot',
				'slug'         => 'f2f-ai-chatbot',
				'version'      => (string) $data['version'],
				'download_url' => esc_url_raw( (string) $data['download_url'] ),
				'requires'     => isset( $data['requires'] ) ? (string) $data['requires'] : '6.0',
				'tested'       => isset( $data['tested'] ) ? (string) $data['tested'] : '6.7',
				'requires_php' => isset( $data['requires_php'] ) ? (string) $data['requires_php'] : '7.4',
				'homepage'     => isset( $data['homepage'] ) ? esc_url_raw( (string) $data['homepage'] ) : 'https://www.f2fbilisim.com',
				'changelog'    => isset( $data['changelog'] ) ? wp_kses_post( (string) $data['changelog'] ) : '',
				'last_updated' => isset( $data['last_updated'] ) ? (string) $data['last_updated'] : '',
				'icons'        => isset( $data['icons'] ) && is_array( $data['icons'] ) ? $data['icons'] : array(),
				'source'       => $url,
			);

			set_transient( self::CACHE_KEY, $info, self::CACHE_TTL );
			delete_transient( self::CACHE_KEY . '_fail' );
			delete_transient( self::LAST_ERR );
			return $info;
		}

		if ( $last_err ) {
			set_transient( self::LAST_ERR, $last_err, HOUR_IN_SECONDS );
		}
		set_transient( self::CACHE_KEY . '_fail', 1, 15 * MINUTE_IN_SECONDS );
		return null;
	}

	/**
	 * @param object $transient Transient.
	 * @return object
	 */
	public function inject_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		$plugin = plugin_basename( F2F_AI_CHATBOT_FILE );
		$remote = self::remote_info();
		if ( ! $remote ) {
			return $transient;
		}

		$current = F2F_AI_CHATBOT_VERSION;
		if ( ! empty( $transient->checked ) && is_array( $transient->checked ) && isset( $transient->checked[ $plugin ] ) ) {
			$current = (string) $transient->checked[ $plugin ];
		}

		if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
			$transient->response = array();
		}
		if ( ! isset( $transient->no_update ) || ! is_array( $transient->no_update ) ) {
			$transient->no_update = array();
		}

		if ( version_compare( $remote['version'], $current, '<=' ) ) {
			unset( $transient->response[ $plugin ] );
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
			'name'          => $remote['name'],
			'slug'          => 'f2f-ai-chatbot',
			'version'       => $remote['version'],
			'author'        => '<a href="https://www.f2fbilisim.com">F2F Bilişim</a>',
			'homepage'      => $remote['homepage'],
			'requires'      => $remote['requires'],
			'tested'        => $remote['tested'],
			'requires_php'  => $remote['requires_php'],
			'download_link' => $remote['download_url'],
			'trunk'         => $remote['download_url'],
			'last_updated'  => $remote['last_updated'],
			'sections'      => array(
				'description' => __( 'F2F lisanslı AI chatbot — keşif, lead, sohbet, WhatsApp ve e-posta bildirimi.', 'f2f-ai-chatbot' ),
				'changelog'   => $remote['changelog'] ? $remote['changelog'] : '<p>' . esc_html( $remote['version'] ) . '</p>',
			),
		);
	}
}
