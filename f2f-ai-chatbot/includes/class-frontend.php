<?php
/**
 * Frontend widget bootstrap.
 *
 * @package F2F_AI_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueue floating chat widget on the public site.
 */
class F2F_AI_Chatbot_Frontend {

	/**
	 * Singleton.
	 *
	 * @var self|null
	 */
	private static $instance = null;

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
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_footer', array( $this, 'render_root' ), 5 );
	}

	/**
	 * Whether widget should load.
	 *
	 * @return bool
	 */
	private function should_load() {
		if ( is_admin() ) {
			return false;
		}
		$s = f2f_ai_chatbot_get_settings();
		return ( '1' === (string) $s['enabled'] );
	}

	/**
	 * Enqueue assets.
	 */
	public function enqueue() {
		if ( ! $this->should_load() ) {
			return;
		}

		$s = f2f_ai_chatbot_get_settings();

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

		wp_localize_script(
			'f2f-ai-chatbot-widget',
			'f2fAiChatbot',
			array(
				'restUrl'        => esc_url_raw( rest_url( 'f2f-ai-chatbot/v1/chat' ) ),
				'nonce'          => wp_create_nonce( 'wp_rest' ),
				'botName'        => $s['bot_name'],
				'welcomeMessage' => $s['welcome_message'],
				'primaryColor'   => $s['primary_color'],
				'position'       => $s['position'],
				'i18n'           => array(
					'placeholder'  => __( 'Mesajınızı yazın…', 'f2f-ai-chatbot' ),
					'send'         => __( 'Gönder', 'f2f-ai-chatbot' ),
					'open'         => __( 'Sohbeti aç', 'f2f-ai-chatbot' ),
					'close'        => __( 'Sohbeti kapat', 'f2f-ai-chatbot' ),
					'error'        => __( 'Bir hata oluştu. Lütfen tekrar deneyin.', 'f2f-ai-chatbot' ),
					'thinking'     => __( 'Yazıyor…', 'f2f-ai-chatbot' ),
					'offline'      => __( 'Bağlantı kurulamadı.', 'f2f-ai-chatbot' ),
				),
			)
		);
	}

	/**
	 * Mount point in footer.
	 */
	public function render_root() {
		if ( ! $this->should_load() ) {
			return;
		}
		echo '<div id="f2f-ai-chatbot-root" class="f2f-ai-chatbot-root" aria-live="polite"></div>';
	}
}
