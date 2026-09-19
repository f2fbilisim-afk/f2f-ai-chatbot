<?php
/**
 * Admin: AI Chat Bot konuşmalar kayıtları.
 *
 * @package F2F_AI_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Top-level menu + conversations table (mockup).
 */
class F2F_AI_Chatbot_Conversations_Admin {

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
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function register_menu() {
		add_menu_page(
			__( 'AI Chat Bot konuşmalar', 'f2f-ai-chatbot' ),
			__( 'AI Chat Bot', 'f2f-ai-chatbot' ),
			'manage_options',
			'f2f-ai-conversations',
			array( $this, 'render_page' ),
			'dashicons-format-chat',
			58
		);

		add_submenu_page(
			'f2f-ai-conversations',
			__( 'Konuşmalar', 'f2f-ai-chatbot' ),
			__( 'Konuşmalar', 'f2f-ai-chatbot' ),
			'manage_options',
			'f2f-ai-conversations',
			array( $this, 'render_page' )
		);

		add_submenu_page(
			'f2f-ai-conversations',
			__( 'Ayarlar', 'f2f-ai-chatbot' ),
			__( 'Ayarlar', 'f2f-ai-chatbot' ),
			'manage_options',
			'f2f-ai-chatbot-settings',
			array( $this, 'redirect_settings' )
		);
	}

	/**
	 * Bridge to existing settings page.
	 */
	public function redirect_settings() {
		wp_safe_redirect( admin_url( 'options-general.php?page=f2f-ai-chatbot' ) );
		exit;
	}

	/**
	 * @param string $hook Hook.
	 */
	public function enqueue( $hook ) {
		if ( 'toplevel_page_f2f-ai-conversations' !== $hook ) {
			return;
		}
		wp_enqueue_style(
			'f2f-ai-conversations',
			F2F_AI_CHATBOT_URL . 'assets/css/conversations.css',
			array(),
			F2F_AI_CHATBOT_VERSION
		);
		wp_enqueue_script(
			'f2f-ai-conversations',
			F2F_AI_CHATBOT_URL . 'assets/js/conversations.js',
			array( 'jquery' ),
			F2F_AI_CHATBOT_VERSION,
			true
		);
		wp_localize_script(
			'f2f-ai-conversations',
			'f2fAiConv',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'f2f_ai_lead_status' ),
			)
		);
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$s     = f2f_ai_chatbot_get_settings();
		$title = ! empty( $s['bot_name'] ) ? $s['bot_name'] . ' ' . __( 'kayıtları', 'f2f-ai-chatbot' ) : __( 'AI Chat Bot konuşmalar', 'f2f-ai-chatbot' );
		$rows  = F2F_AI_Chatbot_Leads::list_records( 150 );
		$statuses = F2F_AI_Chatbot_Leads::statuses();
		?>
		<div class="wrap f2f-conv-wrap">
			<div class="f2f-conv">
				<header class="f2f-conv__head">
					<h1><?php echo esc_html( $title ); ?></h1>
					<p><?php echo esc_html__( 'Müşteri ajanla konuştuğunda iletişim bilgileri ve konuşma özeti burada toplanır. Satış ekibi bu kayıtlardan dönüş yapsın. Özet, son mesajdan 2 dakika sonra otomatik oluşur.', 'f2f-ai-chatbot' ); ?></p>
				</header>

				<?php if ( ! $rows ) : ?>
					<div class="f2f-conv__empty">
						<?php echo esc_html__( 'Henüz kayıt yok. Widget üzerinden bir lead formu doldurulduğunda burada görünecek.', 'f2f-ai-chatbot' ); ?>
					</div>
				<?php else : ?>
					<div class="f2f-conv__table-wrap">
						<table class="f2f-conv__table">
							<thead>
								<tr>
									<th><?php echo esc_html__( 'Müşteri', 'f2f-ai-chatbot' ); ?></th>
									<th><?php echo esc_html__( 'İletişim', 'f2f-ai-chatbot' ); ?></th>
									<th><?php echo esc_html__( 'İlgi', 'f2f-ai-chatbot' ); ?></th>
									<th><?php echo esc_html__( 'Özet', 'f2f-ai-chatbot' ); ?></th>
									<th><?php echo esc_html__( 'Durum', 'f2f-ai-chatbot' ); ?></th>
									<th><?php echo esc_html__( 'Tarih', 'f2f-ai-chatbot' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $rows as $row ) : ?>
									<tr data-id="<?php echo esc_attr( (string) $row['id'] ); ?>">
										<td>
											<strong><?php echo esc_html( $row['name'] ); ?></strong>
											<span class="f2f-conv__meta">
												<?php
												echo esc_html(
													sprintf(
														/* translators: %d message count */
														_n( '%d mesaj', '%d mesaj', max( 0, (int) $row['message_count'] ), 'f2f-ai-chatbot' ),
														(int) $row['message_count']
													)
												);
												?>
												<?php if ( empty( $row['summarized'] ) ) : ?>
													· <?php echo esc_html__( 'özet bekleniyor…', 'f2f-ai-chatbot' ); ?>
												<?php endif; ?>
											</span>
										</td>
										<td class="f2f-conv__contact">
											<span><?php echo esc_html( $row['phone'] ); ?></span>
											<span><?php echo esc_html( $row['email'] ); ?></span>
										</td>
										<td><?php echo esc_html( $row['interest'] ? $row['interest'] : '—' ); ?></td>
										<td class="f2f-conv__summary">
											<?php
											if ( $row['summary'] ) {
												echo nl2br( esc_html( $row['summary'] ) );
											} else {
												echo '<em>' . esc_html__( 'Konuşma bitince (2 dk) özetlenecek…', 'f2f-ai-chatbot' ) . '</em>';
											}
											?>
										</td>
										<td>
											<select class="f2f-conv__status" data-id="<?php echo esc_attr( (string) $row['id'] ); ?>">
												<?php foreach ( $statuses as $val => $lab ) : ?>
													<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $row['status'], $val ); ?>><?php echo esc_html( $lab ); ?></option>
												<?php endforeach; ?>
											</select>
										</td>
										<td class="f2f-conv__date"><?php echo esc_html( $row['date'] ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}
