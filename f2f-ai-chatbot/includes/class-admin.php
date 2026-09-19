<?php
/**
 * Admin settings page.
 *
 * @package F2F_AI_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin settings UI under Settings → F2F AI Chatbot.
 */
class F2F_AI_Chatbot_Admin {

	/**
	 * Singleton.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Option name.
	 *
	 * @var string
	 */
	const OPTION = 'f2f_ai_chatbot_settings';

	/**
	 * Get instance.
	 *
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( F2F_AI_CHATBOT_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Settings link on Plugins screen.
	 *
	 * @param array<int, string> $links Existing links.
	 * @return array<int, string>
	 */
	public function action_links( $links ) {
		$url = admin_url( 'options-general.php?page=f2f-ai-chatbot' );
		array_unshift(
			$links,
			'<a href="' . esc_url( $url ) . '">' . esc_html__( 'Ayarlar', 'f2f-ai-chatbot' ) . '</a>'
		);
		return $links;
	}

	/**
	 * Register submenu.
	 */
	public function register_menu() {
		add_options_page(
			__( 'F2F AI Chatbot', 'f2f-ai-chatbot' ),
			__( 'F2F AI Chatbot', 'f2f-ai-chatbot' ),
			'manage_options',
			'f2f-ai-chatbot',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register setting + sections.
	 */
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
	 * Sanitize settings payload.
	 *
	 * @param mixed $input Raw input.
	 * @return array<string, mixed>
	 */
	public function sanitize( $input ) {
		$defaults = f2f_ai_chatbot_default_settings();
		$current  = f2f_ai_chatbot_get_settings();
		$input    = is_array( $input ) ? $input : array();
		$out      = $defaults;

		$out['enabled']         = empty( $input['enabled'] ) ? '0' : '1';
		$out['api_key']         = isset( $input['api_key'] ) ? sanitize_text_field( $input['api_key'] ) : '';
		$out['model']           = isset( $input['model'] ) ? sanitize_text_field( $input['model'] ) : $defaults['model'];
		$out['system_prompt']   = isset( $input['system_prompt'] ) ? sanitize_textarea_field( $input['system_prompt'] ) : $defaults['system_prompt'];
		$out['welcome_message'] = isset( $input['welcome_message'] ) ? sanitize_textarea_field( $input['welcome_message'] ) : $defaults['welcome_message'];
		$out['bot_name']        = isset( $input['bot_name'] ) ? sanitize_text_field( $input['bot_name'] ) : $defaults['bot_name'];
		$out['position']        = ( isset( $input['position'] ) && 'left' === $input['position'] ) ? 'left' : 'right';
		$out['rate_limit']      = isset( $input['rate_limit'] ) ? max( 1, min( 200, absint( $input['rate_limit'] ) ) ) : (int) $defaults['rate_limit'];
		$out['max_tokens']      = isset( $input['max_tokens'] ) ? max( 50, min( 4000, absint( $input['max_tokens'] ) ) ) : (int) $defaults['max_tokens'];
		$out['temperature']     = isset( $input['temperature'] ) ? max( 0, min( 2, (float) $input['temperature'] ) ) : (float) $defaults['temperature'];

		$color = isset( $input['primary_color'] ) ? sanitize_hex_color( $input['primary_color'] ) : '';
		$out['primary_color'] = $color ? $color : $defaults['primary_color'];

		// Keep existing key if the password field was left blank (browser may clear it).
		if ( '' === $out['api_key'] && ! empty( $current['api_key'] ) && empty( $input['api_key_clear'] ) ) {
			$out['api_key'] = $current['api_key'];
		}

		return $out;
	}

	/**
	 * Admin CSS.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'settings_page_f2f-ai-chatbot' !== $hook ) {
			return;
		}
		wp_enqueue_style(
			'f2f-ai-chatbot-admin',
			F2F_AI_CHATBOT_URL . 'assets/css/admin.css',
			array(),
			F2F_AI_CHATBOT_VERSION
		);
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script(
			'f2f-ai-chatbot-admin',
			F2F_AI_CHATBOT_URL . 'assets/js/admin.js',
			array( 'jquery', 'wp-color-picker' ),
			F2F_AI_CHATBOT_VERSION,
			true
		);
	}

	/**
	 * Render settings page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$s     = f2f_ai_chatbot_get_settings();
		$models = array(
			'gpt-4o-mini'   => 'GPT-4o Mini (önerilen)',
			'gpt-4o'        => 'GPT-4o',
			'gpt-4.1-mini'  => 'GPT-4.1 Mini',
			'gpt-4.1'       => 'GPT-4.1',
			'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
		);
		?>
		<div class="wrap f2f-ai-admin">
			<h1><?php echo esc_html__( 'F2F AI Chatbot', 'f2f-ai-chatbot' ); ?></h1>
			<p class="description">
				<?php echo esc_html__( 'OpenAI bağlı floating chatbot widget. API anahtarınız sunucuda saklanır; ziyaretçi tarayıcısına gönderilmez.', 'f2f-ai-chatbot' ); ?>
			</p>

			<form method="post" action="options.php" class="f2f-ai-admin__form">
				<?php settings_fields( 'f2f_ai_chatbot_group' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php echo esc_html__( 'Widget aktif', 'f2f-ai-chatbot' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[enabled]" value="1" <?php checked( $s['enabled'], '1' ); ?> />
								<?php echo esc_html__( 'Sitede chatbot balonunu göster', 'f2f-ai-chatbot' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="f2f_api_key"><?php echo esc_html__( 'OpenAI API Anahtarı', 'f2f-ai-chatbot' ); ?></label></th>
						<td>
							<input
								type="password"
								class="regular-text"
								id="f2f_api_key"
								name="<?php echo esc_attr( self::OPTION ); ?>[api_key]"
								value=""
								placeholder="<?php echo esc_attr( $s['api_key'] ? '••••••••••••••••' : 'sk-...' ); ?>"
								autocomplete="off"
							/>
							<?php if ( ! empty( $s['api_key'] ) ) : ?>
								<p class="description"><?php echo esc_html__( 'Anahtar kayıtlı. Değiştirmek için yeni anahtar yazın; silmek için aşağıdaki kutuyu işaretleyin.', 'f2f-ai-chatbot' ); ?></p>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[api_key_clear]" value="1" />
									<?php echo esc_html__( 'Kayıtlı API anahtarını sil', 'f2f-ai-chatbot' ); ?>
								</label>
							<?php else : ?>
								<p class="description"><?php echo esc_html__( 'platform.openai.com üzerinden alınır. Anahtar asla frontend’e çıkmaz.', 'f2f-ai-chatbot' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="f2f_model"><?php echo esc_html__( 'Model', 'f2f-ai-chatbot' ); ?></label></th>
						<td>
							<select id="f2f_model" name="<?php echo esc_attr( self::OPTION ); ?>[model]">
								<?php foreach ( $models as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $s['model'], $value ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="f2f_system_prompt"><?php echo esc_html__( 'Sistem promptu', 'f2f-ai-chatbot' ); ?></label></th>
						<td>
							<textarea
								id="f2f_system_prompt"
								name="<?php echo esc_attr( self::OPTION ); ?>[system_prompt]"
								rows="6"
								class="large-text"
							><?php echo esc_textarea( $s['system_prompt'] ); ?></textarea>
							<p class="description"><?php echo esc_html__( 'Asistanın kimliği, dil ve sınırları. platform.f2fbilisim.com tarzı kurumsal yanıtlar için burayı özelleştirin.', 'f2f-ai-chatbot' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="f2f_welcome"><?php echo esc_html__( 'Karşılama mesajı', 'f2f-ai-chatbot' ); ?></label></th>
						<td>
							<textarea
								id="f2f_welcome"
								name="<?php echo esc_attr( self::OPTION ); ?>[welcome_message]"
								rows="2"
								class="large-text"
							><?php echo esc_textarea( $s['welcome_message'] ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="f2f_bot_name"><?php echo esc_html__( 'Bot adı', 'f2f-ai-chatbot' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="f2f_bot_name" name="<?php echo esc_attr( self::OPTION ); ?>[bot_name]" value="<?php echo esc_attr( $s['bot_name'] ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="f2f_primary_color"><?php echo esc_html__( 'Ana renk', 'f2f-ai-chatbot' ); ?></label></th>
						<td>
							<input type="text" class="f2f-color-picker" id="f2f_primary_color" name="<?php echo esc_attr( self::OPTION ); ?>[primary_color]" value="<?php echo esc_attr( $s['primary_color'] ); ?>" data-default-color="#0B6E4F" />
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Konum', 'f2f-ai-chatbot' ); ?></th>
						<td>
							<label style="margin-right:12px;">
								<input type="radio" name="<?php echo esc_attr( self::OPTION ); ?>[position]" value="right" <?php checked( $s['position'], 'right' ); ?> />
								<?php echo esc_html__( 'Sağ alt', 'f2f-ai-chatbot' ); ?>
							</label>
							<label>
								<input type="radio" name="<?php echo esc_attr( self::OPTION ); ?>[position]" value="left" <?php checked( $s['position'], 'left' ); ?> />
								<?php echo esc_html__( 'Sol alt', 'f2f-ai-chatbot' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="f2f_rate_limit"><?php echo esc_html__( 'Saatlik istek limiti (IP)', 'f2f-ai-chatbot' ); ?></label></th>
						<td>
							<input type="number" min="1" max="200" id="f2f_rate_limit" name="<?php echo esc_attr( self::OPTION ); ?>[rate_limit]" value="<?php echo esc_attr( (string) $s['rate_limit'] ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="f2f_max_tokens"><?php echo esc_html__( 'Max tokens', 'f2f-ai-chatbot' ); ?></label></th>
						<td>
							<input type="number" min="50" max="4000" id="f2f_max_tokens" name="<?php echo esc_attr( self::OPTION ); ?>[max_tokens]" value="<?php echo esc_attr( (string) $s['max_tokens'] ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="f2f_temperature"><?php echo esc_html__( 'Temperature', 'f2f-ai-chatbot' ); ?></label></th>
						<td>
							<input type="number" step="0.1" min="0" max="2" id="f2f_temperature" name="<?php echo esc_attr( self::OPTION ); ?>[temperature]" value="<?php echo esc_attr( (string) $s['temperature'] ); ?>" />
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Ayarları kaydet', 'f2f-ai-chatbot' ) ); ?>
			</form>
		</div>
		<?php
	}
}
