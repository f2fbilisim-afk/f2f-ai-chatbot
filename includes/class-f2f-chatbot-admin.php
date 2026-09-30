<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class F2F_Chatbot_Admin {

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
	}

	public static function menu(): void {
		add_options_page(
			__( 'F2F AI Chatbot', 'f2f-ai-chatbot' ),
			__( 'F2F AI Chatbot', 'f2f-ai-chatbot' ),
			'manage_options',
			'f2f-ai-chatbot',
			array( __CLASS__, 'render' )
		);
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s      = F2F_Chatbot_Settings::get();
		$client = F2F_Chatbot_Settings::client();
		$check  = $client->is_configured() ? $client->validate_license() : null;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'F2F AI Chatbot', 'f2f-ai-chatbot' ); ?></h1>
			<p><?php esc_html_e( 'Sohbet istekleri api.f2fbilisim.com üzerinden gider. Lisans: ai-chatbot veya bundle paketi.', 'f2f-ai-chatbot' ); ?></p>

			<?php if ( is_array( $check ) && ! empty( $check['valid'] ) ) : ?>
				<div class="notice notice-success"><p>
					<?php
					printf(
						esc_html__( 'Lisans geçerli — %1$s, kalan token: %2$s', 'f2f-ai-chatbot' ),
						esc_html( $check['package']['name'] ?? '' ),
						esc_html( number_format_i18n( (int) ( $check['usage']['tokens_remaining'] ?? 0 ) ) )
					);
					?>
				</p></div>
			<?php elseif ( is_wp_error( $check ) ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $check->get_error_message() ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'f2f_ai_chatbot_settings_group' ); ?>
				<table class="form-table">
					<tr>
						<th><label for="saas_api_base"><?php esc_html_e( 'API adresi', 'f2f-ai-chatbot' ); ?></label></th>
						<td><input type="url" class="regular-text" id="saas_api_base" name="<?php echo esc_attr( F2F_Chatbot_Settings::OPTION_KEY ); ?>[saas_api_base]" value="<?php echo esc_attr( $s['saas_api_base'] ); ?>" /></td>
					</tr>
					<tr>
						<th><label for="license_key"><?php esc_html_e( 'Lisans anahtarı', 'f2f-ai-chatbot' ); ?></label></th>
						<td><input type="text" class="regular-text code" id="license_key" name="<?php echo esc_attr( F2F_Chatbot_Settings::OPTION_KEY ); ?>[license_key]" value="<?php echo esc_attr( $s['license_key'] ); ?>" /></td>
					</tr>
					<tr>
						<th><label for="bot_name"><?php esc_html_e( 'Bot adı', 'f2f-ai-chatbot' ); ?></label></th>
						<td><input type="text" class="regular-text" id="bot_name" name="<?php echo esc_attr( F2F_Chatbot_Settings::OPTION_KEY ); ?>[bot_name]" value="<?php echo esc_attr( $s['bot_name'] ); ?>" /></td>
					</tr>
					<tr>
						<th><label for="welcome"><?php esc_html_e( 'Karşılama', 'f2f-ai-chatbot' ); ?></label></th>
						<td><input type="text" class="large-text" id="welcome" name="<?php echo esc_attr( F2F_Chatbot_Settings::OPTION_KEY ); ?>[welcome]" value="<?php echo esc_attr( $s['welcome'] ); ?>" /></td>
					</tr>
					<tr>
						<th><label for="system_prompt"><?php esc_html_e( 'Sistem prompt', 'f2f-ai-chatbot' ); ?></label></th>
						<td><textarea class="large-text" rows="5" id="system_prompt" name="<?php echo esc_attr( F2F_Chatbot_Settings::OPTION_KEY ); ?>[system_prompt]"><?php echo esc_textarea( $s['system_prompt'] ); ?></textarea></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Widget', 'f2f-ai-chatbot' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( F2F_Chatbot_Settings::OPTION_KEY ); ?>[enabled]" value="1" <?php checked( ! empty( $s['enabled'] ) ); ?> /> <?php esc_html_e( 'Sitede göster', 'f2f-ai-chatbot' ); ?></label></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
