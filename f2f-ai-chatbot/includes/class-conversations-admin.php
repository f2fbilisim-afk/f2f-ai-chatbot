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
 * Top-level menu + modern conversations board.
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

		add_submenu_page(
			'f2f-ai-conversations',
			__( 'E-posta bildirimi', 'f2f-ai-chatbot' ),
			__( 'E-posta bildirimi', 'f2f-ai-chatbot' ),
			'manage_options',
			'f2f-ai-mail-notify',
			array( $this, 'render_mail_page' )
		);
	}

	/**
	 * Dedicated mail settings page (high visibility).
	 */
	public function render_mail_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s = f2f_ai_chatbot_get_settings();
		?>
		<div class="wrap f2f-conv-wrap">
			<div class="f2f-conv" style="max-width:720px;">
				<header class="f2f-conv__hero">
					<div>
						<p class="f2f-conv__eyebrow"><?php echo esc_html__( 'Bildirimler', 'f2f-ai-chatbot' ); ?></p>
						<h1><?php echo esc_html__( 'Lead e-posta ayarı', 'f2f-ai-chatbot' ); ?></h1>
						<p class="f2f-conv__lede"><?php echo esc_html__( 'Her yeni lead ve AI özeti, girdiğiniz adrese noreply@f2fbilisim.com üzerinden gönderilir. WordPress paneline girmenize gerek kalmaz.', 'f2f-ai-chatbot' ); ?></p>
					</div>
				</header>
				<?php
				$notify_card_id = 'f2f_mail';
				include F2F_AI_CHATBOT_PATH . 'includes/admin-views/notify-card.php';
				?>
				<p style="margin-top:16px;">
					<a class="f2f-conv__btn" href="<?php echo esc_url( admin_url( 'admin.php?page=f2f-ai-conversations' ) ); ?>"><?php echo esc_html__( '← Konuşmalara dön', 'f2f-ai-chatbot' ); ?></a>
				</p>
			</div>
		</div>
		<?php
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
		$hook = (string) $hook;
		$ok   = (
			'toplevel_page_f2f-ai-conversations' === $hook
			|| false !== strpos( $hook, 'f2f-ai-mail-notify' )
			|| false !== strpos( $hook, 'f2f-ai-conversations' )
		);
		if ( ! $ok ) {
			return;
		}
		wp_enqueue_style(
			'f2f-ai-conversations',
			F2F_AI_CHATBOT_URL . 'assets/css/conversations.css',
			array(),
			F2F_AI_CHATBOT_VERSION
		);
		wp_enqueue_style(
			'f2f-ai-chatbot-admin',
			F2F_AI_CHATBOT_URL . 'assets/css/admin.css',
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
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'f2f_ai_lead_status' ),
				'notifyNonce' => wp_create_nonce( 'f2f_ai_admin' ),
				'i18n'        => array(
					'saved'      => __( 'Durum güncellendi', 'f2f-ai-chatbot' ),
					'fail'       => __( 'Güncellenemedi', 'f2f-ai-chatbot' ),
					'empty'      => __( 'Bu filtrede kayıt yok.', 'f2f-ai-chatbot' ),
					'mailSaving' => __( 'Kaydediliyor…', 'f2f-ai-chatbot' ),
					'mailSaved'  => __( 'E-posta ayarı kaydedildi', 'f2f-ai-chatbot' ),
					'mailFail'   => __( 'E-posta ayarı kaydedilemedi', 'f2f-ai-chatbot' ),
				),
			)
		);
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$s        = f2f_ai_chatbot_get_settings();
		$bot      = ! empty( $s['bot_name'] ) ? (string) $s['bot_name'] : 'F2F AI';
		$rows     = F2F_AI_Chatbot_Leads::list_records( 150 );
		$statuses = F2F_AI_Chatbot_Leads::statuses();

		$counts = array(
			'all' => count( $rows ),
		);
		foreach ( array_keys( $statuses ) as $st ) {
			$counts[ $st ] = 0;
		}
		$pending_summary = 0;
		foreach ( $rows as $row ) {
			$st = isset( $row['status'] ) ? (string) $row['status'] : 'yeni';
			if ( isset( $counts[ $st ] ) ) {
				$counts[ $st ]++;
			}
			if ( empty( $row['summarized'] ) ) {
				$pending_summary++;
			}
		}
		?>
		<div class="wrap f2f-conv-wrap">
			<div class="f2f-conv" id="f2f_conv_root">
				<header class="f2f-conv__hero">
					<div>
						<p class="f2f-conv__eyebrow"><?php echo esc_html__( 'Satış takip paneli', 'f2f-ai-chatbot' ); ?></p>
						<h1><?php echo esc_html( sprintf( /* translators: %s bot name */ __( '%s kayıtları', 'f2f-ai-chatbot' ), $bot ) ); ?></h1>
						<p class="f2f-conv__lede"><?php echo esc_html__( 'Lead bilgileri ve AI özeti burada. Özet, son mesajdan 2 dakika sonra otomatik oluşur — satış ekibi buradan dönüş yapsın.', 'f2f-ai-chatbot' ); ?></p>
					</div>
					<div class="f2f-conv__hero-actions">
						<a class="f2f-conv__btn" href="<?php echo esc_url( admin_url( 'admin.php?page=f2f-ai-mail-notify' ) ); ?>"><?php echo esc_html__( 'E-posta bildirimi', 'f2f-ai-chatbot' ); ?></a>
						<a class="f2f-conv__btn" href="<?php echo esc_url( admin_url( 'options-general.php?page=f2f-ai-chatbot' ) ); ?>"><?php echo esc_html__( 'Kurulum / ayarlar', 'f2f-ai-chatbot' ); ?></a>
						<a class="f2f-conv__btn f2f-conv__btn--primary" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Siteyi aç', 'f2f-ai-chatbot' ); ?></a>
					</div>
				</header>

				<div class="f2f-conv__stats">
					<div class="f2f-conv__stat">
						<strong><?php echo esc_html( (string) $counts['all'] ); ?></strong>
						<span><?php echo esc_html__( 'Toplam lead', 'f2f-ai-chatbot' ); ?></span>
					</div>
					<div class="f2f-conv__stat">
						<strong><?php echo esc_html( (string) ( isset( $counts['yeni'] ) ? $counts['yeni'] : 0 ) ); ?></strong>
						<span><?php echo esc_html__( 'Yeni', 'f2f-ai-chatbot' ); ?></span>
					</div>
					<div class="f2f-conv__stat">
						<strong><?php echo esc_html( (string) ( isset( $counts['iletisimde'] ) ? $counts['iletisimde'] : 0 ) ); ?></strong>
						<span><?php echo esc_html__( 'İletişimde', 'f2f-ai-chatbot' ); ?></span>
					</div>
					<div class="f2f-conv__stat">
						<strong><?php echo esc_html( (string) $pending_summary ); ?></strong>
						<span><?php echo esc_html__( 'Özet bekleyen', 'f2f-ai-chatbot' ); ?></span>
					</div>
				</div>

				<?php
				$notify_card_id = 'f2f_mail';
				include F2F_AI_CHATBOT_PATH . 'includes/admin-views/notify-card.php';
				?>

				<div class="f2f-conv__toolbar">
					<label class="f2f-conv__search">
						<span class="screen-reader-text"><?php echo esc_html__( 'Ara', 'f2f-ai-chatbot' ); ?></span>
						<input type="search" id="f2f_conv_search" placeholder="<?php echo esc_attr__( 'İsim, telefon, e-posta veya ilgi ara…', 'f2f-ai-chatbot' ); ?>" />
					</label>
					<div class="f2f-conv__filters" role="tablist" aria-label="<?php echo esc_attr__( 'Durum filtresi', 'f2f-ai-chatbot' ); ?>">
						<button type="button" class="f2f-conv__chip is-active" data-filter="all"><?php echo esc_html__( 'Tümü', 'f2f-ai-chatbot' ); ?> <em><?php echo esc_html( (string) $counts['all'] ); ?></em></button>
						<?php foreach ( $statuses as $val => $lab ) : ?>
							<button type="button" class="f2f-conv__chip" data-filter="<?php echo esc_attr( $val ); ?>">
								<?php echo esc_html( $lab ); ?>
								<em><?php echo esc_html( (string) ( isset( $counts[ $val ] ) ? $counts[ $val ] : 0 ) ); ?></em>
							</button>
						<?php endforeach; ?>
					</div>
				</div>

				<p class="f2f-conv__toast" id="f2f_conv_toast" hidden></p>

				<?php if ( ! $rows ) : ?>
					<div class="f2f-conv__empty">
						<strong><?php echo esc_html__( 'Henüz kayıt yok', 'f2f-ai-chatbot' ); ?></strong>
						<p><?php echo esc_html__( 'Widget üzerinden bir lead formu doldurulduğunda burada kartlar halinde görünecek.', 'f2f-ai-chatbot' ); ?></p>
					</div>
				<?php else : ?>
					<div class="f2f-conv__list" id="f2f_conv_list">
						<?php foreach ( $rows as $row ) : ?>
							<?php
							$status_key = isset( $row['status'] ) ? (string) $row['status'] : 'yeni';
							$status_lab = isset( $statuses[ $status_key ] ) ? $statuses[ $status_key ] : $status_key;
							$phone      = isset( $row['phone'] ) ? (string) $row['phone'] : '';
							$email      = isset( $row['email'] ) ? (string) $row['email'] : '';
							$phone_href = preg_replace( '/\D+/', '', $phone );
							$wa_href    = $phone_href;
							if ( $wa_href && 0 === strpos( $wa_href, '0' ) ) {
								$wa_href = '90' . substr( $wa_href, 1 );
							}
							$search_blob = strtolower( trim( $row['name'] . ' ' . $phone . ' ' . $email . ' ' . $row['interest'] . ' ' . $row['summary'] ) );
							$initials    = '';
							$parts       = preg_split( '/\s+/', trim( (string) $row['name'] ) );
							if ( $parts ) {
								$initials = strtoupper( mb_substr( $parts[0], 0, 1 ) );
								if ( isset( $parts[1] ) ) {
									$initials .= strtoupper( mb_substr( $parts[1], 0, 1 ) );
								}
							}
							?>
							<article
								class="f2f-lead-card"
								data-id="<?php echo esc_attr( (string) $row['id'] ); ?>"
								data-status="<?php echo esc_attr( $status_key ); ?>"
								data-search="<?php echo esc_attr( $search_blob ); ?>"
							>
								<div class="f2f-lead-card__top">
									<div class="f2f-lead-card__who">
										<span class="f2f-lead-card__avatar" aria-hidden="true"><?php echo esc_html( $initials ? $initials : '?' ); ?></span>
										<div>
											<strong class="f2f-lead-card__name"><?php echo esc_html( $row['name'] ); ?></strong>
											<span class="f2f-lead-card__meta">
												<?php
												echo esc_html(
													sprintf(
														_n( '%d mesaj', '%d mesaj', max( 0, (int) $row['message_count'] ), 'f2f-ai-chatbot' ),
														(int) $row['message_count']
													)
												);
												?>
												· <?php echo esc_html( $row['date'] ); ?>
											</span>
										</div>
									</div>
									<span class="f2f-lead-card__badge f2f-lead-card__badge--<?php echo esc_attr( $status_key ); ?>" data-badge>
										<?php echo esc_html( $status_lab ); ?>
									</span>
								</div>

								<?php if ( ! empty( $row['interest'] ) ) : ?>
									<p class="f2f-lead-card__interest">
										<span><?php echo esc_html__( 'İlgi', 'f2f-ai-chatbot' ); ?></span>
										<?php echo esc_html( $row['interest'] ); ?>
									</p>
								<?php endif; ?>

								<div class="f2f-lead-card__summary <?php echo empty( $row['summary'] ) ? 'is-pending' : ''; ?>">
									<?php if ( $row['summary'] ) : ?>
										<?php echo nl2br( esc_html( $row['summary'] ) ); ?>
									<?php else : ?>
										<em><?php echo esc_html__( 'Konuşma bitince (2 dk) özetlenecek…', 'f2f-ai-chatbot' ); ?></em>
									<?php endif; ?>
								</div>

								<div class="f2f-lead-card__foot">
									<div class="f2f-lead-card__links">
										<?php if ( $phone_href ) : ?>
											<a href="tel:<?php echo esc_attr( $phone_href ); ?>"><?php echo esc_html( $phone ); ?></a>
										<?php endif; ?>
										<?php if ( $email ) : ?>
											<a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a>
										<?php endif; ?>
										<?php if ( $wa_href ) : ?>
											<a class="is-wa" href="https://wa.me/<?php echo esc_attr( $wa_href ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'WhatsApp', 'f2f-ai-chatbot' ); ?></a>
										<?php endif; ?>
									</div>
									<label class="f2f-lead-card__status">
										<span class="screen-reader-text"><?php echo esc_html__( 'Durum', 'f2f-ai-chatbot' ); ?></span>
										<select class="f2f-conv__status" data-id="<?php echo esc_attr( (string) $row['id'] ); ?>">
											<?php foreach ( $statuses as $val => $lab ) : ?>
												<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $status_key, $val ); ?>><?php echo esc_html( $lab ); ?></option>
											<?php endforeach; ?>
										</select>
									</label>
								</div>
							</article>
						<?php endforeach; ?>
					</div>
					<p class="f2f-conv__empty f2f-conv__empty--filter" id="f2f_conv_filter_empty" hidden>
						<?php echo esc_html__( 'Bu filtrede kayıt yok.', 'f2f-ai-chatbot' ); ?>
					</p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}
