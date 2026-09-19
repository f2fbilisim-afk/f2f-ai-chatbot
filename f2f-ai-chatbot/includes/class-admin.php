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
		add_filter( 'plugin_action_links_' . plugin_basename( F2F_AI_CHATBOT_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * @param array<int, string> $links Links.
	 * @return array<int, string>
	 */
	public function action_links( $links ) {
		$url = admin_url( 'options-general.php?page=f2f-ai-chatbot' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Ayarlar', 'f2f-ai-chatbot' ) . '</a>' );
		return $links;
	}

	public function register_menu() {
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

		$out['enabled'] = empty( $input['enabled'] ) ? '0' : '1';
		// API key is NOT accepted from the form anymore (paid SaaS).
		// Preserve any legacy key in DB only for migration; customers cannot set it.
		$out['api_key'] = ! empty( $current['api_key'] ) ? $current['api_key'] : '';
		if ( ! empty( $input['purge_legacy_api_key'] ) ) {
			$out['api_key'] = '';
		}

		$out['system_prompt']  = isset( $input['system_prompt'] ) ? sanitize_textarea_field( $input['system_prompt'] ) : $defaults['system_prompt'];
		$out['business_notes'] = isset( $input['business_notes'] ) ? sanitize_textarea_field( $input['business_notes'] ) : '';
		$out['position']       = ( isset( $input['position'] ) && 'left' === $input['position'] ) ? 'left' : 'right';
		$out['show_teaser']    = empty( $input['show_teaser'] ) ? '0' : '1';
		$out['auto_reindex']   = empty( $input['auto_reindex'] ) ? '0' : '1';
		$out['avatar_id']      = isset( $input['avatar_id'] ) ? absint( $input['avatar_id'] ) : 0;
		$out['bottom_margin']  = isset( $input['bottom_margin'] ) ? max( 0, min( 40, (float) $input['bottom_margin'] ) ) : (float) $defaults['bottom_margin'];
		$out['side_margin']    = isset( $input['side_margin'] ) ? max( 0, min( 20, (float) $input['side_margin'] ) ) : (float) $defaults['side_margin'];
		// Token/model limits are F2F-controlled defaults — not customer inputs.
		$out['rate_limit']  = (int) $defaults['rate_limit'];
		$out['max_tokens']  = (int) $defaults['max_tokens'];
		$out['temperature'] = (float) $defaults['temperature'];
		$out['model']       = $defaults['model'];

		delete_transient( 'f2f_ai_license_status' );

		$color = isset( $input['primary_color'] ) ? sanitize_hex_color( $input['primary_color'] ) : '';
		$out['primary_color'] = $color ? $color : $defaults['primary_color'];

		foreach ( $text_keys as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$out[ $key ] = sanitize_text_field( $input[ $key ] );
			}
		}

		// Longer text fields already handled except discover_subtext / lead_subtext / chat_welcome / whatsapp_message.
		foreach ( array( 'discover_subtext', 'lead_subtext', 'chat_welcome', 'whatsapp_message', 'teaser_message' ) as $ta ) {
			if ( isset( $input[ $ta ] ) ) {
				$out[ $ta ] = sanitize_textarea_field( $input[ $ta ] );
			}
		}

		// Normalize + activate premium (1 year) when a pool key is saved.
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
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'f2f_ai_admin' ),
				'i18n'    => array(
					'scanning'   => __( 'Taranıyor…', 'f2f-ai-chatbot' ),
					'done'       => __( 'Tarama tamamlandı.', 'f2f-ai-chatbot' ),
					'fail'       => __( 'Tarama başarısız.', 'f2f-ai-chatbot' ),
					'refreshing' => __( 'Güncelleniyor…', 'f2f-ai-chatbot' ),
					'refreshed'  => __( 'Kota güncellendi.', 'f2f-ai-chatbot' ),
					'refreshFail'=> __( 'Kota okunamadı.', 'f2f-ai-chatbot' ),
				),
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

		return array(
			'status'         => isset( $lic['status'] ) ? (string) $lic['status'] : 'missing',
			'message'        => isset( $lic['message'] ) ? (string) $lic['message'] : '',
			'plan_label'     => isset( $lic['plan_label'] ) ? (string) $lic['plan_label'] : '',
			'premium'        => ! empty( $lic['premium'] ),
			'can_chat'       => ! empty( $lic['can_chat'] ),
			'days_left'      => isset( $lic['days_left'] ) ? $lic['days_left'] : null,
			'messages_left'  => $left,
			'messages_limit' => $limit,
			'messages_used'  => $used,
			'percent_used'   => $pct,
			'updated_at'     => current_time( 'mysql' ),
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
		if ( null === $left || null === $limit ) {
			$title = __( 'F2F AI: lisans yok', 'f2f-ai-chatbot' );
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
		?>
		<div class="wrap f2f-ai-admin">
			<h1><?php echo esc_html__( 'F2F AI Chatbot', 'f2f-ai-chatbot' ); ?></h1>
			<p class="description"><?php echo esc_html__( 'Lisans anahtarı boş gelir. Anahtar paketi (Starter / Business / Pro) konuşma kotasını ve 1 yıllık süreyi açar. OpenAI API anahtarı bu panelde yoktur.', 'f2f-ai-chatbot' ); ?></p>

			<?php
			$q         = $this->quota_payload();
			$q_left    = $q['messages_left'];
			$q_limit   = $q['messages_limit'];
			$q_used    = $q['messages_used'];
			$q_pct     = (int) $q['percent_used'];
			$q_can     = ! empty( $q['can_chat'] );
			$q_plan    = (string) $q['plan_label'];
			$q_days    = $q['days_left'];
			$q_status  = (string) $q['status'];
			$q_msg     = (string) $q['message'];
			$card_mod  = $q_can ? 'is-ok' : ( 'exhausted' === $q_status ? 'is-warn' : 'is-bad' );
			?>
			<div class="f2f-quota-card <?php echo esc_attr( $card_mod ); ?>" id="f2f_quota_card" data-status="<?php echo esc_attr( $q_status ); ?>">
				<div class="f2f-quota-card__head">
					<div>
						<p class="f2f-quota-card__eyebrow"><?php echo esc_html__( 'Güncel konuşma kotası', 'f2f-ai-chatbot' ); ?></p>
						<p class="f2f-quota-card__value" id="f2f_quota_value">
							<?php if ( null !== $q_left && null !== $q_limit ) : ?>
								<strong id="f2f_quota_left"><?php echo esc_html( (string) (int) $q_left ); ?></strong>
								<span id="f2f_quota_slash"> / </span>
								<span id="f2f_quota_limit"><?php echo esc_html( (string) (int) $q_limit ); ?></span>
								<span class="f2f-quota-card__unit" id="f2f_quota_unit"><?php echo esc_html__( 'konuşma kaldı', 'f2f-ai-chatbot' ); ?></span>
							<?php else : ?>
								<strong id="f2f_quota_left">—</strong>
								<span id="f2f_quota_slash" hidden> / </span>
								<span id="f2f_quota_limit" hidden></span>
								<span class="f2f-quota-card__unit" id="f2f_quota_unit"><?php echo esc_html__( 'lisans girilmedi', 'f2f-ai-chatbot' ); ?></span>
							<?php endif; ?>
						</p>
						<p class="f2f-quota-card__meta" id="f2f_quota_meta">
							<?php
							$bits = array();
							if ( $q_plan ) {
								$bits[] = $q_plan;
							}
							if ( null !== $q_used && null !== $q_limit ) {
								$bits[] = sprintf(
									/* translators: 1: used 2: limit */
									__( 'Kullanılan: %1$d / %2$d', 'f2f-ai-chatbot' ),
									(int) $q_used,
									(int) $q_limit
								);
							}
							if ( null !== $q_days && ! empty( $q['premium'] ) ) {
								$bits[] = sprintf(
									/* translators: %d days */
									__( '%d gün kaldı', 'f2f-ai-chatbot' ),
									(int) $q_days
								);
							}
							echo esc_html( $bits ? implode( ' · ', $bits ) : $q_msg );
							?>
						</p>
					</div>
					<button type="button" class="button" id="f2f_quota_refresh"><?php echo esc_html__( 'Kotayı yenile', 'f2f-ai-chatbot' ); ?></button>
				</div>
				<div class="f2f-quota-card__bar" <?php echo ( null === $q_limit ) ? 'hidden' : ''; ?> id="f2f_quota_bar_wrap">
					<div class="f2f-quota-card__bar-fill" id="f2f_quota_bar" style="width:<?php echo esc_attr( (string) $q_pct ); ?>%;"></div>
				</div>
				<p class="f2f-quota-card__hint" id="f2f_quota_hint"><?php echo esc_html( $q_msg ); ?></p>
				<p class="f2f-quota-card__updated" id="f2f_quota_updated">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s datetime */
							__( 'Son güncelleme: %s', 'f2f-ai-chatbot' ),
							(string) $q['updated_at']
						)
					);
					?>
				</p>
			</div>

			<form method="post" action="options.php" class="f2f-ai-admin__form">
				<?php settings_fields( 'f2f_ai_chatbot_group' ); ?>

				<h2><?php echo esc_html__( 'Genel', 'f2f-ai-chatbot' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th><?php echo esc_html__( 'Widget aktif', 'f2f-ai-chatbot' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[enabled]" value="1" <?php checked( $s['enabled'], '1' ); ?> />
								<?php echo esc_html__( 'Sitede göster', 'f2f-ai-chatbot' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th><label for="f2f_bot_name"><?php echo esc_html__( 'Chatbot başlığı', 'f2f-ai-chatbot' ); ?></label></th>
						<td><?php $this->field( 'bot_name', $s ); ?></td>
					</tr>
					<tr>
						<th><?php echo esc_html__( 'Profil fotoğrafı', 'f2f-ai-chatbot' ); ?></th>
						<td>
							<div class="f2f-avatar-picker">
								<img src="<?php echo esc_url( $avatar ); ?>" alt="" class="f2f-avatar-preview" id="f2f_avatar_preview" width="48" height="48" />
								<input type="hidden" name="<?php echo esc_attr( $opt ); ?>[avatar_id]" id="f2f_avatar_id" value="<?php echo esc_attr( (string) $s['avatar_id'] ); ?>" />
								<button type="button" class="button" id="f2f_avatar_upload"><?php echo esc_html__( 'Medya kütüphanesinden seç', 'f2f-ai-chatbot' ); ?></button>
								<button type="button" class="button" id="f2f_avatar_clear"><?php echo esc_html__( 'Varsayılana dön', 'f2f-ai-chatbot' ); ?></button>
							</div>
							<p class="description"><?php echo esc_html__( 'Veya doğrudan URL:', 'f2f-ai-chatbot' ); ?></p>
							<?php $this->field( 'avatar_url', $s, 'url', array( 'class' => 'large-text' ) ); ?>
						</td>
					</tr>
					<tr>
						<th><label for="f2f_primary_color"><?php echo esc_html__( 'Ana renk', 'f2f-ai-chatbot' ); ?></label></th>
						<td><input type="text" class="f2f-color-picker" id="f2f_primary_color" name="<?php echo esc_attr( $opt ); ?>[primary_color]" value="<?php echo esc_attr( $s['primary_color'] ); ?>" data-default-color="#22C55E" /></td>
					</tr>
					<tr>
						<th><?php echo esc_html__( 'Konum (sağ / sol)', 'f2f-ai-chatbot' ); ?></th>
						<td>
							<label style="margin-right:12px;"><input type="radio" name="<?php echo esc_attr( $opt ); ?>[position]" value="right" <?php checked( $s['position'], 'right' ); ?> /> <?php echo esc_html__( 'Sağ', 'f2f-ai-chatbot' ); ?></label>
							<label><input type="radio" name="<?php echo esc_attr( $opt ); ?>[position]" value="left" <?php checked( $s['position'], 'left' ); ?> /> <?php echo esc_html__( 'Sol', 'f2f-ai-chatbot' ); ?></label>
						</td>
					</tr>
					<tr>
						<th><label for="f2f_bottom_margin"><?php echo esc_html__( 'Alt boşluk (bottom %)', 'f2f-ai-chatbot' ); ?></label></th>
						<td>
							<input type="number" min="0" max="40" step="0.5" id="f2f_bottom_margin" name="<?php echo esc_attr( $opt ); ?>[bottom_margin]" value="<?php echo esc_attr( (string) $s['bottom_margin'] ); ?>" class="small-text" /> %
							<p class="description"><?php echo esc_html__( 'Ekranın altından yüzde olarak mesafe. Örn: 4 = %4.', 'f2f-ai-chatbot' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="f2f_side_margin"><?php echo esc_html__( 'Yan boşluk (side %)', 'f2f-ai-chatbot' ); ?></label></th>
						<td>
							<input type="number" min="0" max="20" step="0.5" id="f2f_side_margin" name="<?php echo esc_attr( $opt ); ?>[side_margin]" value="<?php echo esc_attr( (string) $s['side_margin'] ); ?>" class="small-text" /> %
						</td>
					</tr>
					<tr>
						<th><label for="f2f_launcher_label"><?php echo esc_html__( 'Yuvarlak ikon yazısı', 'f2f-ai-chatbot' ); ?></label></th>
						<td><?php $this->field( 'launcher_label', $s ); ?></td>
					</tr>
					<tr>
						<th><?php echo esc_html__( 'Karşılama balonu', 'f2f-ai-chatbot' ); ?></th>
						<td>
							<label style="display:block;margin-bottom:8px;">
								<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[show_teaser]" value="1" <?php checked( $s['show_teaser'], '1' ); ?> />
								<?php echo esc_html__( 'Kapalıyken balonu göster', 'f2f-ai-chatbot' ); ?>
							</label>
							<label style="display:block;margin-bottom:6px;"><?php echo esc_html__( 'Üst başlık (yeşil)', 'f2f-ai-chatbot' ); ?><br /><?php $this->field( 'teaser_title', $s ); ?></label>
							<label style="display:block;"><?php echo esc_html__( 'Mesaj', 'f2f-ai-chatbot' ); ?><br /><?php $this->field( 'teaser_message', $s, 'textarea', array( 'rows' => 2, 'class' => 'large-text' ) ); ?></label>
						</td>
					</tr>
				</table>

				<h2><?php echo esc_html__( 'Keşif ekranı', 'f2f-ai-chatbot' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="f2f_discover_headline"><?php echo esc_html__( 'Başlık', 'f2f-ai-chatbot' ); ?></label></th>
						<td><?php $this->field( 'discover_headline', $s, 'text', array( 'class' => 'large-text' ) ); ?></td>
					</tr>
					<tr>
						<th><label for="f2f_discover_subtext"><?php echo esc_html__( 'Alt metin', 'f2f-ai-chatbot' ); ?></label></th>
						<td><?php $this->field( 'discover_subtext', $s, 'textarea', array( 'rows' => 3, 'class' => 'large-text' ) ); ?></td>
					</tr>
					<tr>
						<th><label for="f2f_input_placeholder"><?php echo esc_html__( 'Yazma alanı placeholder', 'f2f-ai-chatbot' ); ?></label></th>
						<td><?php $this->field( 'input_placeholder', $s, 'text', array( 'class' => 'large-text' ) ); ?></td>
					</tr>
					<tr>
						<th><label for="f2f_divider_text"><?php echo esc_html__( 'Ayırıcı metin', 'f2f-ai-chatbot' ); ?></label></th>
						<td><?php $this->field( 'divider_text', $s ); ?></td>
					</tr>
					<tr>
						<th><?php echo esc_html__( 'Öne çıkan kutu', 'f2f-ai-chatbot' ); ?></th>
						<td class="f2f-service-row">
							<label><?php echo esc_html__( 'Başlık', 'f2f-ai-chatbot' ); ?> <?php $this->field( 'featured_label', $s ); ?></label>
							<label><?php echo esc_html__( 'Alt yazı', 'f2f-ai-chatbot' ); ?> <?php $this->field( 'featured_subtitle', $s ); ?></label>
							<label><?php echo esc_html__( 'İkon', 'f2f-ai-chatbot' ); ?>
								<select name="<?php echo esc_attr( $opt ); ?>[featured_icon]">
									<?php foreach ( $icons as $val => $lab ) : ?>
										<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $s['featured_icon'], $val ); ?>><?php echo esc_html( $lab ); ?></option>
									<?php endforeach; ?>
								</select>
							</label>
						</td>
					</tr>
					<?php for ( $i = 1; $i <= 4; $i++ ) : ?>
						<tr>
							<th><?php echo esc_html( sprintf( /* translators: %d service number */ __( 'Hizmet kutusu %d', 'f2f-ai-chatbot' ), $i ) ); ?></th>
							<td class="f2f-service-row">
								<label><?php echo esc_html__( 'Başlık', 'f2f-ai-chatbot' ); ?> <?php $this->field( 'service_' . $i . '_label', $s ); ?></label>
								<label><?php echo esc_html__( 'İkon', 'f2f-ai-chatbot' ); ?>
									<select name="<?php echo esc_attr( $opt ); ?>[service_<?php echo esc_attr( (string) $i ); ?>_icon]">
										<?php foreach ( $icons as $val => $lab ) : ?>
											<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $s[ 'service_' . $i . '_icon' ], $val ); ?>><?php echo esc_html( $lab ); ?></option>
										<?php endforeach; ?>
									</select>
								</label>
							</td>
						</tr>
					<?php endfor; ?>
				</table>

				<h2><?php echo esc_html__( 'İletişim formu (lead)', 'f2f-ai-chatbot' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="f2f_lead_headline"><?php echo esc_html__( 'Başlık', 'f2f-ai-chatbot' ); ?></label></th>
						<td><?php $this->field( 'lead_headline', $s ); ?></td>
					</tr>
					<tr>
						<th><label for="f2f_lead_subtext"><?php echo esc_html__( 'Açıklama', 'f2f-ai-chatbot' ); ?></label></th>
						<td><?php $this->field( 'lead_subtext', $s, 'textarea', array( 'rows' => 3, 'class' => 'large-text' ) ); ?></td>
					</tr>
					<tr>
						<th><label for="f2f_lead_btn"><?php echo esc_html__( 'Devam butonu', 'f2f-ai-chatbot' ); ?></label></th>
						<td><?php $this->field( 'lead_btn', $s ); ?></td>
					</tr>
					<tr>
						<th><label for="f2f_lead_cancel"><?php echo esc_html__( 'Vazgeç metni', 'f2f-ai-chatbot' ); ?></label></th>
						<td><?php $this->field( 'lead_cancel', $s ); ?></td>
					</tr>
					<tr>
						<th><label for="f2f_chat_welcome"><?php echo esc_html__( 'Sohbet karşılama', 'f2f-ai-chatbot' ); ?></label></th>
						<td>
							<?php $this->field( 'chat_welcome', $s, 'textarea', array( 'rows' => 2, 'class' => 'large-text' ) ); ?>
							<p class="description"><?php echo esc_html__( 'Değişkenler: {ad} {soyad} {hizmet}', 'f2f-ai-chatbot' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php echo esc_html__( 'WhatsApp — Canlı Görüşme', 'f2f-ai-chatbot' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="f2f_whatsapp_phone"><?php echo esc_html__( 'Telefon (ülke kodu ile)', 'f2f-ai-chatbot' ); ?></label></th>
						<td>
							<?php $this->field( 'whatsapp_phone', $s ); ?>
							<p class="description"><?php echo esc_html__( 'Örn: 905499009310 — sohbet ekranındaki buton bu numaraya yönlendirir.', 'f2f-ai-chatbot' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="f2f_whatsapp_btn_label"><?php echo esc_html__( 'Buton yazısı', 'f2f-ai-chatbot' ); ?></label></th>
						<td><?php $this->field( 'whatsapp_btn_label', $s ); ?></td>
					</tr>
					<tr>
						<th><label for="f2f_whatsapp_message"><?php echo esc_html__( 'Önceden dolu mesaj', 'f2f-ai-chatbot' ); ?></label></th>
						<td><?php $this->field( 'whatsapp_message', $s, 'textarea', array( 'rows' => 2, 'class' => 'large-text' ) ); ?></td>
					</tr>
				</table>

				<?php
				$kb = F2F_AI_Chatbot_Knowledge::get();
				$kb_count = isset( $kb['count'] ) ? (int) $kb['count'] : 0;
				$kb_updated = isset( $kb['updated'] ) ? $kb['updated'] : '';
				?>
				<h2><?php echo esc_html__( 'Site bilgisi (sektör / tarama)', 'f2f-ai-chatbot' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="f2f_business_notes"><?php echo esc_html__( 'İşletme / sektör notları', 'f2f-ai-chatbot' ); ?></label></th>
						<td>
							<?php $this->field( 'business_notes', $s, 'textarea', array( 'rows' => 4, 'class' => 'large-text' ) ); ?>
							<p class="description"><?php echo esc_html__( 'Örn: “CNC torna ve freze üretiyoruz. Gıda veya hosting satmıyoruz.” Bu metin her sohbete eklenir.', 'f2f-ai-chatbot' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php echo esc_html__( 'Site içerik taraması', 'f2f-ai-chatbot' ); ?></th>
						<td>
							<p>
								<?php
								echo esc_html(
									$kb_count
										? sprintf(
											/* translators: 1: doc count 2: datetime */
											__( 'Son tarama: %1$d içerik — %2$s', 'f2f-ai-chatbot' ),
											$kb_count,
											$kb_updated
										)
										: __( 'Henüz tarama yok. Butona basınca yayınlanmış sayfa, yazı (ve WooCommerce ürünleri) indekslenir.', 'f2f-ai-chatbot' )
								);
								?>
							</p>
							<button type="button" class="button button-secondary" id="f2f_reindex_btn"><?php echo esc_html__( 'Siteyi şimdi tara', 'f2f-ai-chatbot' ); ?></button>
							<span id="f2f_reindex_status" style="margin-left:8px;"></span>
							<label style="display:block;margin-top:10px;">
								<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[auto_reindex]" value="1" <?php checked( $s['auto_reindex'], '1' ); ?> />
								<?php echo esc_html__( 'İçerik kaydedilince otomatik yeniden tara', 'f2f-ai-chatbot' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<h2><?php echo esc_html__( 'F2F AI Chatbot Lisans', 'f2f-ai-chatbot' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="f2f_license_key"><?php echo esc_html__( 'Lisans anahtarı', 'f2f-ai-chatbot' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="f2f_license_key" name="<?php echo esc_attr( $opt ); ?>[license_key]" value="<?php echo esc_attr( (string) $s['license_key'] ); ?>" autocomplete="off" placeholder="F2F-XXXX-XXXX-XXXX-XXXX" spellcheck="false" />
							<p class="description">
								<?php echo esc_html__( 'Boş gelir. Anahtar paketi belirler: Starter 1.000 / Business 5.000 / Pro 15.000 konuşma + 1 yıl.', 'f2f-ai-chatbot' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th><?php echo esc_html__( 'Durum', 'f2f-ai-chatbot' ); ?></th>
						<td>
							<?php
							$status    = isset( $lic['status'] ) ? (string) $lic['status'] : 'missing';
							$msg       = isset( $lic['message'] ) ? (string) $lic['message'] : '';
							$days_left = isset( $lic['days_left'] ) ? $lic['days_left'] : null;
							$premium   = ! empty( $lic['premium'] );
							$can_chat  = ! empty( $lic['can_chat'] );
							$plan_lbl  = isset( $lic['plan_label'] ) ? (string) $lic['plan_label'] : '';
							$left      = isset( $lic['messages_left'] ) ? $lic['messages_left'] : null;
							$limit     = isset( $lic['messages_limit'] ) ? $lic['messages_limit'] : null;
							$used      = isset( $lic['messages_used'] ) ? $lic['messages_used'] : null;
							$badge     = $can_chat ? 'PREMIUM' : strtoupper( $status );
							$color     = $can_chat ? '#0b6e4f' : ( 'exhausted' === $status ? '#b45309' : '#9b1c1c' );
							?>
							<p style="margin:0 0 6px;">
								<strong style="color:<?php echo esc_attr( $color ); ?>;">
									<?php echo esc_html( $badge ); ?>
								</strong>
								<?php if ( $plan_lbl ) : ?>
									· <?php echo esc_html( $plan_lbl ); ?>
								<?php endif; ?>
								<?php if ( null !== $left && null !== $limit ) : ?>
									— <?php echo esc_html( sprintf( /* translators: 1: left 2: limit */ __( 'Kalan konuşma: %1$d / %2$d', 'f2f-ai-chatbot' ), (int) $left, (int) $limit ) ); ?>
								<?php endif; ?>
								<?php if ( null !== $days_left && $premium ) : ?>
									· <?php echo esc_html( sprintf( /* translators: %d days */ __( '%d gün', 'f2f-ai-chatbot' ), (int) $days_left ) ); ?>
								<?php endif; ?>
							</p>
							<?php if ( null !== $used && null !== $limit && $limit > 0 ) : ?>
								<div style="max-width:320px;height:8px;background:#e5e7eb;border-radius:999px;overflow:hidden;margin:8px 0;">
									<div style="height:100%;width:<?php echo esc_attr( (string) min( 100, round( ( $used / $limit ) * 100 ) ) ); ?>%;background:<?php echo esc_attr( $can_chat ? '#22c55e' : '#f59e0b' ); ?>;"></div>
								</div>
							<?php endif; ?>
							<p class="description" style="margin:0;"><?php echo esc_html( $msg ); ?></p>
							<?php if ( ! empty( $s['api_key'] ) ) : ?>
								<p class="description" style="color:#b45309;">
									<?php echo esc_html__( 'Eski sürümden kalma OpenAI anahtarı veritabanında duruyor (panelde gösterilmez). Lisansa geçtikten sonra temizleyebilirsiniz.', 'f2f-ai-chatbot' ); ?>
								</p>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[purge_legacy_api_key]" value="1" />
									<?php echo esc_html__( 'Eski API anahtarını sil', 'f2f-ai-chatbot' ); ?>
								</label>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th><label for="f2f_system_prompt"><?php echo esc_html__( 'Asistan talimatı (sektör)', 'f2f-ai-chatbot' ); ?></label></th>
						<td>
							<?php $this->field( 'system_prompt', $s, 'textarea', array( 'rows' => 5, 'class' => 'large-text' ) ); ?>
							<p class="description"><?php echo esc_html__( 'Değişkenler: {site_name} {site_description} {business_notes} {services}. Model / token F2F tarafından yönetilir.', 'f2f-ai-chatbot' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Ayarları kaydet', 'f2f-ai-chatbot' ) ); ?>
			</form>
		</div>
		<?php
	}
}
