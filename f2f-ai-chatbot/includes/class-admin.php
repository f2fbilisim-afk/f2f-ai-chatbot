<?php
/**
 * Admin settings.
 *
 * @package F2F_AI_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings → F2F AI Chatbot
 */
class F2F_AI_Chatbot_Admin {

	/**
	 * @var self|null
	 */
	private static $instance = null;

	const OPTION = 'f2f_ai_chatbot_settings';

	/**
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar_quota' ), 100 );
		add_action( 'wp_ajax_f2f_ai_license_quota', array( $this, 'ajax_license_quota' ) );
		add_action( 'wp_ajax_f2f_ai_wizard_save', array( $this, 'ajax_wizard_save' ) );
		add_action( 'wp_ajax_f2f_ai_wizard_finish', array( $this, 'ajax_wizard_finish' ) );
		add_action( 'wp_ajax_f2f_ai_notify_save', array( $this, 'ajax_notify_save' ) );
		add_action( 'admin_notices', array( $this, 'maybe_master_key_notice' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( F2F_AI_CHATBOT_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Warn F2F/admin when OpenAI master key is missing (causes "Platform sohbet hatası").
	 */
	public function maybe_master_key_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! class_exists( 'F2F_AI_Chatbot_Gateway' ) ) {
			return;
		}
		if ( F2F_AI_Chatbot_Gateway::master_openai_key() ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return;
		}
		$ok = (
			false !== strpos( (string) $screen->id, 'f2f-ai' )
			|| 'settings_page_f2f-ai-chatbot' === $screen->id
			|| 'plugins' === $screen->id
		);
		if ( ! $ok ) {
			return;
		}
		echo '<div class="notice notice-warning"><p><strong>F2F AI Chatbot:</strong> ';
		echo esc_html__( 'Sohbet için wp-config.php içine OpenAI master anahtarı ekleyin (müşteri panelinde API alanı yoktur):', 'f2f-ai-chatbot' );
		echo ' <code>define(\'F2F_AI_MASTER_OPENAI_KEY\', \'sk-proj-...\');</code>';
		echo ' ';
		echo esc_html__( 'Bu yoksa eklenti platform.f2fbilisim.com’a düşer ve “Platform sohbet hatası” görünür.', 'f2f-ai-chatbot' );
		echo '</p></div>';
	}

	/**
	 * @param array<int, string> $links Links.
	 * @return array<int, string>
	 */
	public function action_links( $links ) {
		$extra = array(
			'<a href="' . esc_url( admin_url( 'admin.php?page=f2f-ai-conversations' ) ) . '"><strong>' . esc_html__( 'Konuşmalar', 'f2f-ai-chatbot' ) . '</strong></a>',
			'<a href="' . esc_url( admin_url( 'admin.php?page=f2f-ai-mail-notify' ) ) . '">' . esc_html__( 'E-posta', 'f2f-ai-chatbot' ) . '</a>',
			'<a href="' . esc_url( admin_url( 'options-general.php?page=f2f-ai-chatbot' ) ) . '">' . esc_html__( 'Kurulum', 'f2f-ai-chatbot' ) . '</a>',
		);
		return array_merge( $extra, $links );
	}

	public function register_menu() {
		static $registered = false;
		if ( $registered ) {
			return;
		}
		$registered = true;

		add_options_page(
			__( 'F2F AI Chatbot', 'f2f-ai-chatbot' ),
			__( 'F2F AI Chatbot', 'f2f-ai-chatbot' ),
			'manage_options',
			'f2f-ai-chatbot',
			array( $this, 'render_page' )
		);
	}

	public function register_settings() {
		register_setting(
			'f2f_ai_chatbot_group',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => f2f_ai_chatbot_default_settings(),
			)
		);
	}

	/**
	 * @param mixed $input Raw.
	 * @return array<string, mixed>
	 */
	public function sanitize( $input ) {
		$defaults = f2f_ai_chatbot_default_settings();
		$current  = f2f_ai_chatbot_get_settings();
		$input    = is_array( $input ) ? $input : array();
		$out      = $defaults;

		$text_keys = array(
			'license_key',
			'bot_name',
			'launcher_label',
			'teaser_title',
			'teaser_message',
			'avatar_url',
			'discover_headline',
			'discover_subtext',
			'input_placeholder',
			'featured_label',
			'featured_subtitle',
			'featured_icon',
			'service_1_label',
			'service_1_icon',
			'service_2_label',
			'service_2_icon',
			'service_3_label',
			'service_3_icon',
			'service_4_label',
			'service_4_icon',
			'divider_text',
			'lead_headline',
			'lead_subtext',
			'lead_btn',
			'lead_cancel',
			'whatsapp_phone',
			'whatsapp_message',
			'whatsapp_btn_label',
			'chat_welcome',
		);

		$partial = ! empty( $input['_partial'] );
		$out     = $partial ? array_merge( $defaults, $current ) : $defaults;

		if ( $partial ) {
			if ( array_key_exists( 'enabled', $input ) ) {
				$out['enabled'] = empty( $input['enabled'] ) ? '0' : '1';
			}
			if ( array_key_exists( 'show_teaser', $input ) ) {
				$out['show_teaser'] = empty( $input['show_teaser'] ) ? '0' : '1';
			}
			if ( array_key_exists( 'auto_reindex', $input ) ) {
				$out['auto_reindex'] = empty( $input['auto_reindex'] ) ? '0' : '1';
			}
			if ( array_key_exists( 'notify_on_lead', $input ) ) {
				$out['notify_on_lead'] = empty( $input['notify_on_lead'] ) ? '0' : '1';
			}
			if ( array_key_exists( 'notify_on_summary', $input ) ) {
				$out['notify_on_summary'] = empty( $input['notify_on_summary'] ) ? '0' : '1';
			}
		} else {
			$out['enabled']            = empty( $input['enabled'] ) ? '0' : '1';
			$out['show_teaser']        = empty( $input['show_teaser'] ) ? '0' : '1';
			$out['auto_reindex']       = empty( $input['auto_reindex'] ) ? '0' : '1';
			$out['notify_on_lead']     = empty( $input['notify_on_lead'] ) ? '0' : '1';
			$out['notify_on_summary']  = empty( $input['notify_on_summary'] ) ? '0' : '1';
		}

		// API key is NOT accepted from the form anymore (paid SaaS).
		$out['api_key'] = ! empty( $current['api_key'] ) ? $current['api_key'] : '';
		if ( ! empty( $input['purge_legacy_api_key'] ) ) {
			$out['api_key'] = '';
		}

		if ( isset( $input['system_prompt'] ) ) {
			$out['system_prompt'] = sanitize_textarea_field( $input['system_prompt'] );
		} elseif ( ! $partial ) {
			$out['system_prompt'] = $defaults['system_prompt'];
		}

		if ( isset( $input['business_notes'] ) ) {
			$out['business_notes'] = sanitize_textarea_field( $input['business_notes'] );
		} elseif ( ! $partial ) {
			$out['business_notes'] = '';
		}

		if ( isset( $input['position'] ) ) {
			$out['position'] = ( 'left' === $input['position'] ) ? 'left' : 'right';
		} elseif ( ! $partial ) {
			$out['position'] = 'right';
		}

		if ( isset( $input['avatar_id'] ) ) {
			$out['avatar_id'] = absint( $input['avatar_id'] );
		} elseif ( ! $partial ) {
			$out['avatar_id'] = 0;
		}

		if ( isset( $input['bottom_margin'] ) ) {
			$out['bottom_margin'] = max( 0, min( 40, (float) $input['bottom_margin'] ) );
		} elseif ( ! $partial ) {
			$out['bottom_margin'] = (float) $defaults['bottom_margin'];
		}

		if ( isset( $input['side_margin'] ) ) {
			$out['side_margin'] = max( 0, min( 20, (float) $input['side_margin'] ) );
		} elseif ( ! $partial ) {
			$out['side_margin'] = (float) $defaults['side_margin'];
		}

		$out['rate_limit']  = (int) $defaults['rate_limit'];
		$out['max_tokens']  = (int) $defaults['max_tokens'];
		$out['temperature'] = (float) $defaults['temperature'];
		$out['model']       = $defaults['model'];

		delete_transient( 'f2f_ai_license_status' );

		if ( isset( $input['primary_color'] ) ) {
			$color = sanitize_hex_color( $input['primary_color'] );
			$out['primary_color'] = $color ? $color : $defaults['primary_color'];
		} elseif ( ! $partial ) {
			$out['primary_color'] = $defaults['primary_color'];
		}

		foreach ( $text_keys as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$out[ $key ] = sanitize_text_field( $input[ $key ] );
			}
		}

		if ( isset( $input['notify_email'] ) ) {
			$email = sanitize_email( (string) $input['notify_email'] );
			$out['notify_email'] = is_email( $email ) ? $email : '';
		} elseif ( ! $partial ) {
			$out['notify_email'] = '';
		}

		foreach ( array( 'discover_subtext', 'lead_subtext', 'chat_welcome', 'whatsapp_message', 'teaser_message' ) as $ta ) {
			if ( isset( $input[ $ta ] ) ) {
				$out[ $ta ] = sanitize_textarea_field( $input[ $ta ] );
			}
		}

		if ( isset( $out['license_key'] ) && class_exists( 'F2F_AI_Chatbot_License' ) ) {
			$out['license_key'] = F2F_AI_Chatbot_License::normalize( $out['license_key'] );
			F2F_AI_Chatbot_License::activate( $out['license_key'] );
		}

		return $out;
	}

	/**
	 * @param string $hook Hook.
	 */
	public function enqueue_assets( $hook ) {
		$on_settings = ( 'settings_page_f2f-ai-chatbot' === $hook );
		if ( $on_settings ) {
			wp_enqueue_media();
			wp_enqueue_style( 'wp-color-picker' );
		}
		wp_enqueue_style(
			'f2f-ai-chatbot-admin',
			F2F_AI_CHATBOT_URL . 'assets/css/admin.css',
			array(),
			F2F_AI_CHATBOT_VERSION
		);
		if ( ! $on_settings ) {
			return;
		}
		wp_enqueue_script(
			'f2f-ai-chatbot-admin',
			F2F_AI_CHATBOT_URL . 'assets/js/admin.js',
			array( 'jquery', 'wp-color-picker' ),
			F2F_AI_CHATBOT_VERSION,
			true
		);
		wp_localize_script(
			'f2f-ai-chatbot-admin',
			'f2fAiAdmin',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'f2f_ai_admin' ),
				'setupDone'   => (bool) get_option( 'f2f_ai_setup_done', false ),
				'startStep'   => max( 1, min( 5, (int) get_option( 'f2f_ai_setup_step', 1 ) ) ),
				'homeUrl'     => home_url( '/' ),
				'i18n'        => array(
					'scanning'    => __( 'Taranıyor…', 'f2f-ai-chatbot' ),
					'done'        => __( 'Tarama tamamlandı.', 'f2f-ai-chatbot' ),
					'fail'        => __( 'Tarama başarısız.', 'f2f-ai-chatbot' ),
					'refreshing'  => __( 'Güncelleniyor…', 'f2f-ai-chatbot' ),
					'refreshed'   => __( 'Kota güncellendi.', 'f2f-ai-chatbot' ),
					'refreshFail' => __( 'Kota okunamadı.', 'f2f-ai-chatbot' ),
					'saving'      => __( 'Kaydediliyor…', 'f2f-ai-chatbot' ),
					'saved'       => __( 'Kaydedildi', 'f2f-ai-chatbot' ),
					'saveFail'    => __( 'Kayıt başarısız.', 'f2f-ai-chatbot' ),
					'needLicense' => __( 'Devam etmek için geçerli bir lisans anahtarı girin.', 'f2f-ai-chatbot' ),
				),
			)
		);
	}

	/**
	 * Partial save from setup wizard.
	 */
	public function ajax_wizard_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
		check_ajax_referer( 'f2f_ai_admin', 'nonce' );

		$raw = isset( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : array();
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}
		$raw['_partial'] = 1;
		$clean           = $this->sanitize( $raw );
		update_option( self::OPTION, $clean, false );

		$step = isset( $_POST['step'] ) ? absint( $_POST['step'] ) : 1;
		update_option( 'f2f_ai_setup_step', max( 1, min( 5, $step ) ), false );

		wp_send_json_success(
			array(
				'settings' => $clean,
				'quota'    => $this->quota_payload(),
				'step'     => $step,
			)
		);
	}

	/**
	 * Save only notify email settings (settings + conversations pages).
	 */
	public function ajax_notify_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
		check_ajax_referer( 'f2f_ai_admin', 'nonce' );

		$raw = array(
			'_partial'           => 1,
			'notify_email'       => isset( $_POST['notify_email'] ) ? wp_unslash( $_POST['notify_email'] ) : '',
			'notify_on_lead'     => isset( $_POST['notify_on_lead'] ) ? wp_unslash( $_POST['notify_on_lead'] ) : '0',
			'notify_on_summary'  => isset( $_POST['notify_on_summary'] ) ? wp_unslash( $_POST['notify_on_summary'] ) : '0',
		);
		$clean = $this->sanitize( $raw );
		update_option( self::OPTION, $clean, false );

		wp_send_json_success(
			array(
				'notify_email'      => $clean['notify_email'],
				'notify_on_lead'    => $clean['notify_on_lead'],
				'notify_on_summary' => $clean['notify_on_summary'],
				'recipient'         => class_exists( 'F2F_AI_Chatbot_Notify' ) ? F2F_AI_Chatbot_Notify::recipient() : $clean['notify_email'],
			)
		);
	}

	/**
	 * Mark wizard finished.
	 */
	public function ajax_wizard_finish() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
		check_ajax_referer( 'f2f_ai_admin', 'nonce' );
		update_option( 'f2f_ai_setup_done', 1, false );
		update_option( 'f2f_ai_setup_step', 5, false );
		wp_send_json_success(
			array(
				'quota'   => $this->quota_payload(),
				'homeUrl' => home_url( '/' ),
			)
		);
	}

	/**
	 * Live quota for admin AJAX / bar.
	 */
	public function ajax_license_quota() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
		check_ajax_referer( 'f2f_ai_admin', 'nonce' );
		wp_send_json_success( $this->quota_payload() );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function quota_payload() {
		$lic = class_exists( 'F2F_AI_Chatbot_Gateway' ) ? F2F_AI_Chatbot_Gateway::license_status() : array();
		$left  = array_key_exists( 'messages_left', $lic ) ? $lic['messages_left'] : null;
		$limit = array_key_exists( 'messages_limit', $lic ) ? $lic['messages_limit'] : null;
		$used  = array_key_exists( 'messages_used', $lic ) ? $lic['messages_used'] : null;
		$pct   = ( null !== $used && null !== $limit && (int) $limit > 0 )
			? (int) min( 100, round( ( (int) $used / (int) $limit ) * 100 ) )
			: 0;

		$expires_at = isset( $lic['expires_at'] ) && $lic['expires_at'] ? (int) $lic['expires_at'] : null;
		$now        = time();
		$sec_left   = null;
		$cd_days    = null;
		$cd_hours   = null;
		$cd_mins    = null;
		$cd_secs    = null;
		$expires_fmt = '';
		$expires_iso = '';

		if ( $expires_at ) {
			$sec_left    = max( 0, $expires_at - $now );
			$cd_days     = (int) floor( $sec_left / DAY_IN_SECONDS );
			$rem         = $sec_left % DAY_IN_SECONDS;
			$cd_hours    = (int) floor( $rem / HOUR_IN_SECONDS );
			$rem         = $rem % HOUR_IN_SECONDS;
			$cd_mins     = (int) floor( $rem / MINUTE_IN_SECONDS );
			$cd_secs     = (int) ( $rem % MINUTE_IN_SECONDS );
			$expires_fmt = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $expires_at );
			$expires_iso = gmdate( 'c', $expires_at );
		}

		$meta          = class_exists( 'F2F_AI_Chatbot_License' ) ? F2F_AI_Chatbot_License::meta() : array();
		$activated_at  = isset( $meta['activated_at'] ) ? (int) $meta['activated_at'] : null;
		$activated_fmt = $activated_at ? wp_date( get_option( 'date_format' ), $activated_at ) : '';

		$time_pct = 0;
		if ( $expires_at && $activated_at && $expires_at > $activated_at ) {
			$elapsed  = max( 0, $now - $activated_at );
			$total    = max( 1, $expires_at - $activated_at );
			$time_pct = (int) min( 100, round( ( $elapsed / $total ) * 100 ) );
		}

		return array(
			'status'            => isset( $lic['status'] ) ? (string) $lic['status'] : 'missing',
			'message'           => isset( $lic['message'] ) ? (string) $lic['message'] : '',
			'plan_label'        => isset( $lic['plan_label'] ) ? (string) $lic['plan_label'] : '',
			'premium'           => ! empty( $lic['premium'] ),
			'can_chat'          => ! empty( $lic['can_chat'] ),
			'days_left'         => isset( $lic['days_left'] ) ? $lic['days_left'] : null,
			'messages_left'     => $left,
			'messages_limit'    => $limit,
			'messages_used'     => $used,
			'percent_used'      => $pct,
			'expires_at'        => $expires_at,
			'expires_at_iso'    => $expires_iso,
			'expires_at_label'  => $expires_fmt,
			'activated_at'      => $activated_at,
			'activated_label'   => $activated_fmt,
			'seconds_left'      => $sec_left,
			'countdown_days'    => $cd_days,
			'countdown_hours'   => $cd_hours,
			'countdown_minutes' => $cd_mins,
			'countdown_seconds' => $cd_secs,
			'time_percent_used' => $time_pct,
			'updated_at'        => current_time( 'mysql' ),
			'server_now'        => $now,
		);
	}

	/**
	 * Show remaining chats in the WP admin bar.
	 *
	 * @param \WP_Admin_Bar $bar Bar.
	 */
	public function admin_bar_quota( $bar ) {
		if ( ! is_admin_bar_showing() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$lic  = class_exists( 'F2F_AI_Chatbot_Gateway' ) ? F2F_AI_Chatbot_Gateway::license_status() : array();
		$left = array_key_exists( 'messages_left', $lic ) ? $lic['messages_left'] : null;
		$limit = array_key_exists( 'messages_limit', $lic ) ? $lic['messages_limit'] : null;
		$days = array_key_exists( 'days_left', $lic ) ? $lic['days_left'] : null;
		if ( null === $left || null === $limit ) {
			$title = __( 'F2F AI: lisans yok', 'f2f-ai-chatbot' );
		} elseif ( null !== $days ) {
			$title = sprintf(
				/* translators: 1: left 2: limit 3: days */
				__( 'F2F AI: %1$d/%2$d · %3$d gün', 'f2f-ai-chatbot' ),
				(int) $left,
				(int) $limit,
				(int) $days
			);
		} else {
			$title = sprintf(
				/* translators: 1: left 2: limit */
				__( 'F2F AI: %1$d / %2$d konuşma', 'f2f-ai-chatbot' ),
				(int) $left,
				(int) $limit
			);
		}
		$bar->add_node(
			array(
				'id'    => 'f2f-ai-quota',
				'title' => esc_html( $title ),
				'href'  => admin_url( 'options-general.php?page=f2f-ai-chatbot' ),
				'meta'  => array( 'class' => 'f2f-ai-admin-bar-quota' ),
			)
		);
	}

	/**
	 * Field helper.
	 *
	 * @param string               $key Key.
	 * @param array<string, mixed> $s   Settings.
	 * @param string               $type Input type.
	 * @param array<string, mixed> $attrs Extra attrs.
	 */
	private function field( $key, $s, $type = 'text', $attrs = array() ) {
		$name  = self::OPTION . '[' . $key . ']';
		$id    = 'f2f_' . $key;
		$value = isset( $s[ $key ] ) ? $s[ $key ] : '';
		$class = isset( $attrs['class'] ) ? $attrs['class'] : 'regular-text';
		if ( 'textarea' === $type ) {
			$rows = isset( $attrs['rows'] ) ? (int) $attrs['rows'] : 3;
			printf(
				'<textarea id="%1$s" name="%2$s" rows="%3$d" class="%4$s">%5$s</textarea>',
				esc_attr( $id ),
				esc_attr( $name ),
				$rows,
				esc_attr( $class ),
				esc_textarea( (string) $value )
			);
			return;
		}
		printf(
			'<input type="%1$s" id="%2$s" name="%3$s" value="%4$s" class="%5$s" %6$s />',
			esc_attr( $type ),
			esc_attr( $id ),
			esc_attr( $name ),
			esc_attr( (string) $value ),
			esc_attr( $class ),
			isset( $attrs['extra'] ) ? $attrs['extra'] : ''
		);
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s      = f2f_ai_chatbot_get_settings();
		$opt    = self::OPTION;
		$avatar = f2f_ai_chatbot_avatar_url( $s );
		$lic    = class_exists( 'F2F_AI_Chatbot_Gateway' ) ? F2F_AI_Chatbot_Gateway::license_status() : array();
		$q      = $this->quota_payload();
		$icons  = array(
			'layout'  => 'Layout (web)',
			'sparkle' => 'Sparkle',
			'globe'   => 'Globe',
			'bag'     => 'Shopping bag',
			'code'    => 'Code',
			'server'  => 'Server',
			'chat'    => 'Chat',
			'star'    => 'Star',
		);
		include F2F_AI_CHATBOT_PATH . 'includes/admin-views/page.php';
	}

}
