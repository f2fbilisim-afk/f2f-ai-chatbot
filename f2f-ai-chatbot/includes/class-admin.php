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
			'model',
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
		$out['api_key'] = isset( $input['api_key'] ) ? sanitize_text_field( $input['api_key'] ) : '';
		if ( '' === $out['api_key'] && ! empty( $current['api_key'] ) && empty( $input['api_key_clear'] ) ) {
			$out['api_key'] = $current['api_key'];
		}

		$out['system_prompt'] = isset( $input['system_prompt'] ) ? sanitize_textarea_field( $input['system_prompt'] ) : $defaults['system_prompt'];
		$out['position']      = ( isset( $input['position'] ) && 'left' === $input['position'] ) ? 'left' : 'right';
		$out['show_teaser']   = empty( $input['show_teaser'] ) ? '0' : '1';
		$out['avatar_id']     = isset( $input['avatar_id'] ) ? absint( $input['avatar_id'] ) : 0;
		$out['bottom_margin'] = isset( $input['bottom_margin'] ) ? max( 0, min( 40, (float) $input['bottom_margin'] ) ) : (float) $defaults['bottom_margin'];
		$out['side_margin']   = isset( $input['side_margin'] ) ? max( 0, min( 20, (float) $input['side_margin'] ) ) : (float) $defaults['side_margin'];
		$out['rate_limit']    = isset( $input['rate_limit'] ) ? max( 1, min( 200, absint( $input['rate_limit'] ) ) ) : (int) $defaults['rate_limit'];
		$out['max_tokens']    = isset( $input['max_tokens'] ) ? max( 50, min( 4000, absint( $input['max_tokens'] ) ) ) : (int) $defaults['max_tokens'];
		$out['temperature']   = isset( $input['temperature'] ) ? max( 0, min( 2, (float) $input['temperature'] ) ) : (float) $defaults['temperature'];

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

		return $out;
	}

	/**
	 * @param string $hook Hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'settings_page_f2f-ai-chatbot' !== $hook ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style(
			'f2f-ai-chatbot-admin',
			F2F_AI_CHATBOT_URL . 'assets/css/admin.css',
			array(),
			F2F_AI_CHATBOT_VERSION
		);
		wp_enqueue_script(
			'f2f-ai-chatbot-admin',
			F2F_AI_CHATBOT_URL . 'assets/js/admin.js',
			array( 'jquery', 'wp-color-picker' ),
			F2F_AI_CHATBOT_VERSION,
			true
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
		$models = array(
			'gpt-4o-mini'   => 'GPT-4o Mini',
			'gpt-4o'        => 'GPT-4o',
			'gpt-4.1-mini'  => 'GPT-4.1 Mini',
			'gpt-4.1'       => 'GPT-4.1',
			'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
		);
		$icons = array(
			'layout'  => 'Layout (web)',
			'sparkle' => 'Sparkle',
			'globe'   => 'Globe',
			'bag'     => 'Shopping bag',
			'code'    => 'Code',
			'server'  => 'Server',
			'chat'    => 'Chat',
			'star'    => 'Star',
		);
		$avatar = f2f_ai_chatbot_avatar_url( $s );
		?>
		<div class="wrap f2f-ai-admin">
			<h1><?php echo esc_html__( 'F2F AI Chatbot — AI Proje Ajanı', 'f2f-ai-chatbot' ); ?></h1>
			<p class="description"><?php echo esc_html__( 'Keşif ekranı → iletişim formu → OpenAI sohbeti. Profil, başlık ve 4 hizmet kutusu buradan yönetilir.', 'f2f-ai-chatbot' ); ?></p>

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

				<h2><?php echo esc_html__( 'OpenAI', 'f2f-ai-chatbot' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="f2f_api_key"><?php echo esc_html__( 'API Anahtarı', 'f2f-ai-chatbot' ); ?></label></th>
						<td>
							<input type="password" class="regular-text" id="f2f_api_key" name="<?php echo esc_attr( $opt ); ?>[api_key]" value="" placeholder="<?php echo esc_attr( $s['api_key'] ? '••••••••••••••••' : 'sk-...' ); ?>" autocomplete="off" />
							<?php if ( ! empty( $s['api_key'] ) ) : ?>
								<p class="description"><?php echo esc_html__( 'Anahtar kayıtlı. Değiştirmek için yeni yazın.', 'f2f-ai-chatbot' ); ?></p>
								<label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[api_key_clear]" value="1" /> <?php echo esc_html__( 'Anahtarı sil', 'f2f-ai-chatbot' ); ?></label>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th><label for="f2f_model"><?php echo esc_html__( 'Model', 'f2f-ai-chatbot' ); ?></label></th>
						<td>
							<select id="f2f_model" name="<?php echo esc_attr( $opt ); ?>[model]">
								<?php foreach ( $models as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $s['model'], $value ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="f2f_system_prompt"><?php echo esc_html__( 'Sistem promptu', 'f2f-ai-chatbot' ); ?></label></th>
						<td><?php $this->field( 'system_prompt', $s, 'textarea', array( 'rows' => 6, 'class' => 'large-text' ) ); ?></td>
					</tr>
					<tr>
						<th><label for="f2f_rate_limit"><?php echo esc_html__( 'Saatlik istek limiti', 'f2f-ai-chatbot' ); ?></label></th>
						<td><?php $this->field( 'rate_limit', $s, 'number', array( 'extra' => 'min="1" max="200"' ) ); ?></td>
					</tr>
					<tr>
						<th><label for="f2f_max_tokens"><?php echo esc_html__( 'Max tokens', 'f2f-ai-chatbot' ); ?></label></th>
						<td><?php $this->field( 'max_tokens', $s, 'number', array( 'extra' => 'min="50" max="4000"' ) ); ?></td>
					</tr>
					<tr>
						<th><label for="f2f_temperature"><?php echo esc_html__( 'Temperature', 'f2f-ai-chatbot' ); ?></label></th>
						<td><?php $this->field( 'temperature', $s, 'number', array( 'extra' => 'step="0.1" min="0" max="2"' ) ); ?></td>
					</tr>
				</table>

				<?php submit_button( __( 'Ayarları kaydet', 'f2f-ai-chatbot' ) ); ?>
			</form>
		</div>
		<?php
	}
}
