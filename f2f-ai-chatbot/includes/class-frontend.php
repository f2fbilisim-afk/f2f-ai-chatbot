<?php
/**
 * Frontend enqueue.
 *
 * @package F2F_AI_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Public widget.
 */
class F2F_AI_Chatbot_Frontend {

	/**
	 * @var self|null
	 */
	private static $instance = null;

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
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_footer', array( $this, 'render_root' ), 5 );
	}

	/**
	 * @return bool
	 */
	private function should_load() {
		if ( is_admin() ) {
			return false;
		}
		$s = f2f_ai_chatbot_get_settings();
		return ( '1' === (string) $s['enabled'] );
	}

	public function enqueue() {
		if ( ! $this->should_load() ) {
			return;
		}

		wp_enqueue_style(
			'f2f-ai-chatbot-widget',
			F2F_AI_CHATBOT_URL . 'assets/css/widget.css',
			array(),
			F2F_AI_CHATBOT_VERSION
		);

		wp_enqueue_script(
			'f2f-ai-chatbot-widget',
			F2F_AI_CHATBOT_URL . 'assets/js/widget.js',
			array(),
			F2F_AI_CHATBOT_VERSION,
			true
		);

		$config = f2f_ai_chatbot_public_config();
		$config['restUrl']  = esc_url_raw( rest_url( 'f2f-ai-chatbot/v1/' ) );
		$config['nonce']    = wp_create_nonce( 'wp_rest' );
		$config['i18n']     = array(
			'firstName'  => __( 'Ad', 'f2f-ai-chatbot' ),
			'lastName'   => __( 'Soyad', 'f2f-ai-chatbot' ),
			'phone'      => __( 'Telefon', 'f2f-ai-chatbot' ),
			'email'      => __( 'E-posta', 'f2f-ai-chatbot' ),
			'phonePh'    => '05xx xxx xx xx',
			'emailPh'    => 'ornek@firma.com',
			'namePh'     => __( 'Adınız', 'f2f-ai-chatbot' ),
			'lastPh'     => __( 'Soyadınız', 'f2f-ai-chatbot' ),
			'open'       => __( 'Sohbeti aç', 'f2f-ai-chatbot' ),
			'close'      => __( 'Kapat', 'f2f-ai-chatbot' ),
			'send'       => __( 'Gönder', 'f2f-ai-chatbot' ),
			'thinking'   => __( 'Ajan yazıyor...', 'f2f-ai-chatbot' ),
			'error'      => __( 'Bir hata oluştu. Tekrar deneyin.', 'f2f-ai-chatbot' ),
			'offline'    => __( 'Bağlantı kurulamadı. Sayfayı yenileyip tekrar deneyin.', 'f2f-ai-chatbot' ),
			'serverError'=> __( 'Sunucu yanıt vermedi (zaman aşımı veya yapılandırma). F2F_AI_MASTER_OPENAI_KEY ve site REST API’sini kontrol edin.', 'f2f-ai-chatbot' ),
			'required'   => __( 'Lütfen tüm alanları doldurun.', 'f2f-ai-chatbot' ),
		);

		wp_localize_script( 'f2f-ai-chatbot-widget', 'f2fAiChatbot', $config );
	}

	public function render_root() {
		if ( ! $this->should_load() ) {
			return;
		}
		echo '<div id="f2f-ai-chatbot-root" class="f2f-ai-chatbot-root" aria-live="polite"></div>';
	}
}
