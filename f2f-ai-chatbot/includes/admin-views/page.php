<?php
/**
 * Modern step-by-step setup wizard + advanced settings.
 *
 * Expected vars: $s, $opt, $avatar, $lic, $icons, $q, and helpers via $this.
 *
 * @package F2F_AI_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$setup_done = (bool) get_option( 'f2f_ai_setup_done', false );
$start_step = max( 1, min( 5, (int) get_option( 'f2f_ai_setup_step', 1 ) ) );
if ( $setup_done ) {
	$start_step = 5;
}

$q_left     = $q['messages_left'];
$q_limit    = $q['messages_limit'];
$q_used     = $q['messages_used'];
$q_pct      = (int) $q['percent_used'];
$q_can      = ! empty( $q['can_chat'] );
$q_plan     = (string) $q['plan_label'];
$q_status   = (string) $q['status'];
$q_msg      = (string) $q['message'];
$q_exp      = (string) $q['expires_at_label'];
$q_act      = (string) $q['activated_label'];
$q_cd_d     = $q['countdown_days'];
$q_cd_h     = $q['countdown_hours'];
$q_cd_m     = $q['countdown_minutes'];
$q_cd_s     = $q['countdown_seconds'];
$q_time_pct = (int) $q['time_percent_used'];
$has_time   = ! empty( $q['expires_at'] ) && ! empty( $q['premium'] );
$card_mod   = $q_can ? 'is-ok' : ( 'exhausted' === $q_status ? 'is-warn' : 'is-bad' );

$kb         = class_exists( 'F2F_AI_Chatbot_Knowledge' ) ? F2F_AI_Chatbot_Knowledge::get() : array();
$kb_count   = isset( $kb['count'] ) ? (int) $kb['count'] : 0;
$kb_updated = isset( $kb['updated'] ) ? $kb['updated'] : '';

$steps = array(
	1 => array(
		'title' => __( 'İlk Kurulum', 'f2f-ai-chatbot' ),
		'desc'  => __( 'Lisans anahtarını girin', 'f2f-ai-chatbot' ),
	),
	2 => array(
		'title' => __( 'Karşılama', 'f2f-ai-chatbot' ),
		'desc'  => __( 'İsim, renk, balon', 'f2f-ai-chatbot' ),
	),
	3 => array(
		'title' => __( 'Hizmetler', 'f2f-ai-chatbot' ),
		'desc'  => __( 'Keşif kutuları', 'f2f-ai-chatbot' ),
	),
	4 => array(
		'title' => __( 'Lead Formu', 'f2f-ai-chatbot' ),
		'desc'  => __( 'İletişim ekranı', 'f2f-ai-chatbot' ),
	),
	5 => array(
		'title' => __( 'Canlı & Bildirim', 'f2f-ai-chatbot' ),
		'desc'  => __( 'WhatsApp + e-posta', 'f2f-ai-chatbot' ),
	),
);
?>
<div class="wrap f2f-ai-admin f2f-ai-admin--modern" id="f2f_admin_root" data-setup-done="<?php echo $setup_done ? '1' : '0'; ?>" data-start-step="<?php echo esc_attr( (string) $start_step ); ?>">
	<header class="f2f-hero">
		<div>
			<p class="f2f-hero__eyebrow"><?php echo esc_html__( 'F2F AI Chatbot', 'f2f-ai-chatbot' ); ?></p>
			<h1><?php echo esc_html__( '1 dakikada kurulum', 'f2f-ai-chatbot' ); ?></h1>
			<p class="f2f-hero__lede"><?php echo esc_html__( 'Adım adım ilerleyin. Her “Devam” kaydeder — OpenAI anahtarı burada yoktur.', 'f2f-ai-chatbot' ); ?></p>
		</div>
		<div class="f2f-hero__actions">
			<button type="button" class="button" id="f2f_restart_wizard"><?php echo esc_html__( 'Sihirbazı yeniden başlat', 'f2f-ai-chatbot' ); ?></button>
			<a class="button button-primary" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Siteyi aç', 'f2f-ai-chatbot' ); ?></a>
		</div>
	</header>

	<aside class="f2f-support-card" id="f2f_support_card" aria-label="<?php echo esc_attr__( 'F2F Destek', 'f2f-ai-chatbot' ); ?>">
		<button type="button" class="f2f-support-card__close" id="f2f_support_close" aria-label="<?php echo esc_attr__( 'Kapat', 'f2f-ai-chatbot' ); ?>">&times;</button>
		<div class="f2f-support-card__accent" aria-hidden="true"></div>
		<div class="f2f-support-card__body">
			<h2 class="f2f-support-card__title">
				<?php
				echo wp_kses(
					__( 'Sitenize özel <em>AI asistan</em> — lead toplayın, 7/24 yanıt verin.', 'f2f-ai-chatbot' ),
					array( 'em' => array() )
				);
				?>
			</h2>
			<p class="f2f-support-card__text">
				<?php echo esc_html__( 'F2F AI Chatbot; ziyaretçiyi karşılar, sektörünüze göre konuşur, iletişim formuyla lead toplar ve WhatsApp’a yönlendirir. OpenAI anahtarı müşteri panelinde yoktur — lisans + paket kotası ile yönetilir.', 'f2f-ai-chatbot' ); ?>
			</p>
			<div class="f2f-support-card__person">
				<img
					class="f2f-support-card__photo"
					src="<?php echo esc_url( F2F_AI_CHATBOT_URL . 'assets/img/f2f-founder.png' ); ?>"
					alt="<?php echo esc_attr__( 'F2F Bilişim', 'f2f-ai-chatbot' ); ?>"
					width="72"
					height="72"
					loading="lazy"
				/>
				<div class="f2f-support-card__who">
					<strong><?php echo esc_html__( 'F2F Bilişim', 'f2f-ai-chatbot' ); ?></strong>
					<span><?php echo esc_html__( 'Kurulum & destek ekibi', 'f2f-ai-chatbot' ); ?></span>
				</div>
			</div>
			<p class="f2f-support-card__help">
				<?php echo esc_html__( 'Destek almak isterseniz bize yazın veya arayın:', 'f2f-ai-chatbot' ); ?>
			</p>
			<div class="f2f-support-card__contacts">
				<a class="f2f-support-card__btn" href="mailto:info@f2fbilisim.com">info@f2fbilisim.com</a>
				<a class="f2f-support-card__btn f2f-support-card__btn--phone" href="tel:+905499009310">+90 549 900 93 10</a>
			</div>
		</div>
	</aside>

	<div
		class="f2f-quota-card <?php echo esc_attr( $card_mod ); ?>"
		id="f2f_quota_card"
		data-status="<?php echo esc_attr( $q_status ); ?>"
		data-expires-at="<?php echo esc_attr( $q['expires_at'] ? (string) (int) $q['expires_at'] : '' ); ?>"
		data-server-now="<?php echo esc_attr( (string) (int) $q['server_now'] ); ?>"
	>
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
						$bits[] = sprintf( __( 'Kullanılan: %1$d / %2$d', 'f2f-ai-chatbot' ), (int) $q_used, (int) $q_limit );
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
		<div class="f2f-quota-card__time" id="f2f_quota_time" <?php echo $has_time ? '' : 'hidden'; ?>>
			<p class="f2f-quota-card__eyebrow"><?php echo esc_html__( 'Lisans süresi', 'f2f-ai-chatbot' ); ?></p>
			<div class="f2f-countdown" id="f2f_countdown" aria-live="polite">
				<div class="f2f-countdown__cell"><strong id="f2f_cd_days"><?php echo esc_html( null !== $q_cd_d ? (string) (int) $q_cd_d : '0' ); ?></strong><span><?php echo esc_html__( 'gün', 'f2f-ai-chatbot' ); ?></span></div>
				<div class="f2f-countdown__cell"><strong id="f2f_cd_hours"><?php echo esc_html( null !== $q_cd_h ? sprintf( '%02d', (int) $q_cd_h ) : '00' ); ?></strong><span><?php echo esc_html__( 'saat', 'f2f-ai-chatbot' ); ?></span></div>
				<div class="f2f-countdown__cell"><strong id="f2f_cd_mins"><?php echo esc_html( null !== $q_cd_m ? sprintf( '%02d', (int) $q_cd_m ) : '00' ); ?></strong><span><?php echo esc_html__( 'dk', 'f2f-ai-chatbot' ); ?></span></div>
				<div class="f2f-countdown__cell"><strong id="f2f_cd_secs"><?php echo esc_html( null !== $q_cd_s ? sprintf( '%02d', (int) $q_cd_s ) : '00' ); ?></strong><span><?php echo esc_html__( 'sn', 'f2f-ai-chatbot' ); ?></span></div>
			</div>
			<p class="f2f-quota-card__dates" id="f2f_quota_dates">
				<?php if ( $q_act ) : ?>
					<span id="f2f_activated_label"><?php echo esc_html( sprintf( __( 'Başlangıç: %s', 'f2f-ai-chatbot' ), $q_act ) ); ?></span><span aria-hidden="true"> · </span>
				<?php else : ?>
					<span id="f2f_activated_label" hidden></span>
				<?php endif; ?>
				<span id="f2f_expires_label"><?php echo $q_exp ? esc_html( sprintf( __( 'Bitiş: %s', 'f2f-ai-chatbot' ), $q_exp ) ) : ''; ?></span>
			</p>
			<div class="f2f-quota-card__bar f2f-quota-card__bar--time" id="f2f_time_bar_wrap">
				<div class="f2f-quota-card__bar-fill f2f-quota-card__bar-fill--time" id="f2f_time_bar" style="width:<?php echo esc_attr( (string) $q_time_pct ); ?>%;"></div>
			</div>
		</div>
		<p class="f2f-quota-card__hint" id="f2f_quota_hint"><?php echo esc_html( $q_msg ); ?></p>
		<p class="f2f-quota-card__updated" id="f2f_quota_updated"><?php echo esc_html( sprintf( __( 'Son güncelleme: %s', 'f2f-ai-chatbot' ), (string) $q['updated_at'] ) ); ?></p>
	</div>

	<?php
	$notify_card_id = 'f2f_mail';
	include F2F_AI_CHATBOT_PATH . 'includes/admin-views/notify-card.php';
	?>

	<nav class="f2f-steps" id="f2f_steps" aria-label="<?php echo esc_attr__( 'Kurulum adımları', 'f2f-ai-chatbot' ); ?>">
		<?php foreach ( $steps as $num => $step ) : ?>
			<button type="button" class="f2f-steps__item<?php echo ( (int) $num === (int) $start_step ) ? ' is-active' : ''; ?>" data-step="<?php echo esc_attr( (string) $num ); ?>">
				<span class="f2f-steps__num"><?php echo esc_html( (string) $num ); ?></span>
				<span class="f2f-steps__copy">
					<strong><?php echo esc_html( $step['title'] ); ?></strong>
					<small><?php echo esc_html( $step['desc'] ); ?></small>
				</span>
			</button>
		<?php endforeach; ?>
	</nav>

	<div class="f2f-wizard" id="f2f_wizard">
		<p class="f2f-wizard__status" id="f2f_wizard_status" hidden></p>

		<!-- Step 1: License -->
		<section class="f2f-panel<?php echo 1 === (int) $start_step ? ' is-active' : ''; ?>" data-panel="1">
			<div class="f2f-panel__intro">
				<h2><?php echo esc_html__( '1 · İlk Kurulum', 'f2f-ai-chatbot' ); ?></h2>
				<p><?php echo esc_html__( 'F2F’den aldığınız lisans anahtarını yapıştırın. Paket (Starter / Business / Pro) ve 1 yıl otomatik açılır.', 'f2f-ai-chatbot' ); ?></p>
			</div>
			<label class="f2f-field">
				<span><?php echo esc_html__( 'Lisans anahtarı', 'f2f-ai-chatbot' ); ?></span>
				<input type="text" id="wiz_license_key" value="<?php echo esc_attr( (string) $s['license_key'] ); ?>" placeholder="F2F-XXXX-XXXX-XXXX-XXXX" spellcheck="false" autocomplete="off" />
			</label>
			<label class="f2f-check">
				<input type="checkbox" id="wiz_enabled" value="1" <?php checked( $s['enabled'], '1' ); ?> />
				<span><?php echo esc_html__( 'Widget’ı sitede göster', 'f2f-ai-chatbot' ); ?></span>
			</label>
			<div class="f2f-panel__nav">
				<span></span>
				<button type="button" class="button button-primary f2f-next" data-next="2"><?php echo esc_html__( 'Kaydet ve devam', 'f2f-ai-chatbot' ); ?></button>
			</div>
		</section>

		<!-- Step 2: Welcome -->
		<section class="f2f-panel<?php echo 2 === (int) $start_step ? ' is-active' : ''; ?>" data-panel="2">
			<div class="f2f-panel__intro">
				<h2><?php echo esc_html__( '2 · Karşılama', 'f2f-ai-chatbot' ); ?></h2>
				<p><?php echo esc_html__( 'Ziyaretçinin ilk gördüğü isim, renk ve balon mesajı.', 'f2f-ai-chatbot' ); ?></p>
			</div>
			<div class="f2f-grid">
				<label class="f2f-field"><span><?php echo esc_html__( 'Chatbot başlığı', 'f2f-ai-chatbot' ); ?></span><input type="text" id="wiz_bot_name" value="<?php echo esc_attr( (string) $s['bot_name'] ); ?>" /></label>
				<label class="f2f-field"><span><?php echo esc_html__( 'Yuvarlak ikon yazısı', 'f2f-ai-chatbot' ); ?></span><input type="text" id="wiz_launcher_label" value="<?php echo esc_attr( (string) $s['launcher_label'] ); ?>" /></label>
				<label class="f2f-field"><span><?php echo esc_html__( 'Ana renk', 'f2f-ai-chatbot' ); ?></span><input type="text" class="f2f-color-picker" id="wiz_primary_color" value="<?php echo esc_attr( (string) $s['primary_color'] ); ?>" data-default-color="#22C55E" /></label>
				<div class="f2f-field">
					<span><?php echo esc_html__( 'Konum', 'f2f-ai-chatbot' ); ?></span>
					<div class="f2f-seg">
						<label><input type="radio" name="wiz_position" value="right" <?php checked( $s['position'], 'right' ); ?> /> <?php echo esc_html__( 'Sağ', 'f2f-ai-chatbot' ); ?></label>
						<label><input type="radio" name="wiz_position" value="left" <?php checked( $s['position'], 'left' ); ?> /> <?php echo esc_html__( 'Sol', 'f2f-ai-chatbot' ); ?></label>
					</div>
				</div>
			</div>
			<label class="f2f-check"><input type="checkbox" id="wiz_show_teaser" value="1" <?php checked( $s['show_teaser'], '1' ); ?> /> <span><?php echo esc_html__( 'Karşılama balonunu göster', 'f2f-ai-chatbot' ); ?></span></label>
			<div class="f2f-grid">
				<label class="f2f-field"><span><?php echo esc_html__( 'Balon üst başlık', 'f2f-ai-chatbot' ); ?></span><input type="text" id="wiz_teaser_title" value="<?php echo esc_attr( (string) $s['teaser_title'] ); ?>" /></label>
				<label class="f2f-field f2f-field--full"><span><?php echo esc_html__( 'Balon mesajı', 'f2f-ai-chatbot' ); ?></span><textarea id="wiz_teaser_message" rows="2"><?php echo esc_textarea( (string) $s['teaser_message'] ); ?></textarea></label>
			</div>
			<div class="f2f-avatar-picker">
				<img src="<?php echo esc_url( $avatar ); ?>" alt="" class="f2f-avatar-preview" id="f2f_avatar_preview" width="48" height="48" />
				<input type="hidden" id="f2f_avatar_id" value="<?php echo esc_attr( (string) $s['avatar_id'] ); ?>" />
				<button type="button" class="button" id="f2f_avatar_upload"><?php echo esc_html__( 'Profil fotoğrafı seç', 'f2f-ai-chatbot' ); ?></button>
				<button type="button" class="button" id="f2f_avatar_clear"><?php echo esc_html__( 'Varsayılan', 'f2f-ai-chatbot' ); ?></button>
			</div>
			<label class="f2f-field"><span><?php echo esc_html__( 'veya fotoğraf URL', 'f2f-ai-chatbot' ); ?></span><input type="url" id="wiz_avatar_url" value="<?php echo esc_attr( (string) $s['avatar_url'] ); ?>" /></label>
			<div class="f2f-panel__nav">
				<button type="button" class="button f2f-prev" data-prev="1"><?php echo esc_html__( 'Geri', 'f2f-ai-chatbot' ); ?></button>
				<button type="button" class="button button-primary f2f-next" data-next="3"><?php echo esc_html__( 'Kaydet ve devam', 'f2f-ai-chatbot' ); ?></button>
			</div>
		</section>

		<!-- Step 3: Services -->
		<section class="f2f-panel<?php echo 3 === (int) $start_step ? ' is-active' : ''; ?>" data-panel="3">
			<div class="f2f-panel__intro">
				<h2><?php echo esc_html__( '3 · Hizmetler', 'f2f-ai-chatbot' ); ?></h2>
				<p><?php echo esc_html__( 'Keşif ekranındaki başlık ve 4 hizmet kutusu — sektörünüze göre yazın.', 'f2f-ai-chatbot' ); ?></p>
			</div>
			<label class="f2f-field"><span><?php echo esc_html__( 'Keşif başlığı', 'f2f-ai-chatbot' ); ?></span><input type="text" id="wiz_discover_headline" value="<?php echo esc_attr( (string) $s['discover_headline'] ); ?>" /></label>
			<label class="f2f-field"><span><?php echo esc_html__( 'Alt metin', 'f2f-ai-chatbot' ); ?></span><textarea id="wiz_discover_subtext" rows="2"><?php echo esc_textarea( (string) $s['discover_subtext'] ); ?></textarea></label>
			<div class="f2f-grid">
				<label class="f2f-field"><span><?php echo esc_html__( 'Öne çıkan kutu', 'f2f-ai-chatbot' ); ?></span><input type="text" id="wiz_featured_label" value="<?php echo esc_attr( (string) $s['featured_label'] ); ?>" /></label>
				<label class="f2f-field"><span><?php echo esc_html__( 'Öne çıkan alt yazı', 'f2f-ai-chatbot' ); ?></span><input type="text" id="wiz_featured_subtitle" value="<?php echo esc_attr( (string) $s['featured_subtitle'] ); ?>" /></label>
			</div>
			<div class="f2f-grid f2f-grid--services">
				<?php for ( $i = 1; $i <= 4; $i++ ) : ?>
					<label class="f2f-field">
						<span><?php echo esc_html( sprintf( __( 'Hizmet %d', 'f2f-ai-chatbot' ), $i ) ); ?></span>
						<input type="text" id="wiz_service_<?php echo esc_attr( (string) $i ); ?>_label" value="<?php echo esc_attr( (string) $s[ 'service_' . $i . '_label' ] ); ?>" />
					</label>
				<?php endfor; ?>
			</div>
			<div class="f2f-panel__nav">
				<button type="button" class="button f2f-prev" data-prev="2"><?php echo esc_html__( 'Geri', 'f2f-ai-chatbot' ); ?></button>
				<button type="button" class="button button-primary f2f-next" data-next="4"><?php echo esc_html__( 'Kaydet ve devam', 'f2f-ai-chatbot' ); ?></button>
			</div>
		</section>

		<!-- Step 4: Lead -->
		<section class="f2f-panel<?php echo 4 === (int) $start_step ? ' is-active' : ''; ?>" data-panel="4">
			<div class="f2f-panel__intro">
				<h2><?php echo esc_html__( '4 · İletişim / Lead formu', 'f2f-ai-chatbot' ); ?></h2>
				<p><?php echo esc_html__( 'Sohbetten önce ad-soyad ve telefon toplanır. Metinleri buradan ayarlayın.', 'f2f-ai-chatbot' ); ?></p>
			</div>
			<label class="f2f-field"><span><?php echo esc_html__( 'Form başlığı', 'f2f-ai-chatbot' ); ?></span><input type="text" id="wiz_lead_headline" value="<?php echo esc_attr( (string) $s['lead_headline'] ); ?>" /></label>
			<label class="f2f-field"><span><?php echo esc_html__( 'Açıklama', 'f2f-ai-chatbot' ); ?></span><textarea id="wiz_lead_subtext" rows="2"><?php echo esc_textarea( (string) $s['lead_subtext'] ); ?></textarea></label>
			<div class="f2f-grid">
				<label class="f2f-field"><span><?php echo esc_html__( 'Devam butonu', 'f2f-ai-chatbot' ); ?></span><input type="text" id="wiz_lead_btn" value="<?php echo esc_attr( (string) $s['lead_btn'] ); ?>" /></label>
				<label class="f2f-field"><span><?php echo esc_html__( 'Vazgeç metni', 'f2f-ai-chatbot' ); ?></span><input type="text" id="wiz_lead_cancel" value="<?php echo esc_attr( (string) $s['lead_cancel'] ); ?>" /></label>
			</div>
			<label class="f2f-field"><span><?php echo esc_html__( 'Sohbet karşılama', 'f2f-ai-chatbot' ); ?></span><textarea id="wiz_chat_welcome" rows="2"><?php echo esc_textarea( (string) $s['chat_welcome'] ); ?></textarea><small>{ad} {soyad} {hizmet}</small></label>
			<div class="f2f-panel__nav">
				<button type="button" class="button f2f-prev" data-prev="3"><?php echo esc_html__( 'Geri', 'f2f-ai-chatbot' ); ?></button>
				<button type="button" class="button button-primary f2f-next" data-next="5"><?php echo esc_html__( 'Kaydet ve devam', 'f2f-ai-chatbot' ); ?></button>
			</div>
		</section>

		<!-- Step 5: WhatsApp + Notify + Site -->
		<section class="f2f-panel<?php echo 5 === (int) $start_step ? ' is-active' : ''; ?>" data-panel="5">
			<div class="f2f-panel__intro">
				<h2><?php echo esc_html__( '5 · WhatsApp, e-posta & site', 'f2f-ai-chatbot' ); ?></h2>
				<p><?php echo esc_html__( 'Son adım: canlı görüşme, lead e-posta bildirimi ve site tarama. Bitince chatbot hazır.', 'f2f-ai-chatbot' ); ?></p>
			</div>
			<div class="f2f-grid">
				<label class="f2f-field"><span><?php echo esc_html__( 'WhatsApp telefon', 'f2f-ai-chatbot' ); ?></span><input type="text" id="wiz_whatsapp_phone" value="<?php echo esc_attr( (string) $s['whatsapp_phone'] ); ?>" placeholder="905499009310" /></label>
				<label class="f2f-field"><span><?php echo esc_html__( 'Buton yazısı', 'f2f-ai-chatbot' ); ?></span><input type="text" id="wiz_whatsapp_btn_label" value="<?php echo esc_attr( (string) $s['whatsapp_btn_label'] ); ?>" /></label>
			</div>
			<label class="f2f-field"><span><?php echo esc_html__( 'WhatsApp ön mesaj', 'f2f-ai-chatbot' ); ?></span><textarea id="wiz_whatsapp_message" rows="2"><?php echo esc_textarea( (string) $s['whatsapp_message'] ); ?></textarea></label>

			<p class="f2f-mail-card__inline-hint">
				<?php echo esc_html__( 'Lead e-posta bildirimi bu sayfanın üstündeki “Lead e-posta ayarı” kartından kaydedilir.', 'f2f-ai-chatbot' ); ?>
				<a href="#f2f_mail_card"><?php echo esc_html__( 'Yukarıya git', 'f2f-ai-chatbot' ); ?></a>
			</p>

			<label class="f2f-field"><span><?php echo esc_html__( 'İşletme / sektör notları', 'f2f-ai-chatbot' ); ?></span><textarea id="wiz_business_notes" rows="3" placeholder="<?php echo esc_attr__( 'CNC torna üretiyoruz; hosting satmıyoruz…', 'f2f-ai-chatbot' ); ?>"><?php echo esc_textarea( (string) $s['business_notes'] ); ?></textarea></label>
			<div class="f2f-scan">
				<p id="f2f_kb_summary">
					<?php
					echo esc_html(
						$kb_count
							? sprintf( __( 'Son tarama: %1$d içerik — %2$s', 'f2f-ai-chatbot' ), $kb_count, $kb_updated )
							: __( 'Henüz tarama yok. Siteyi tarayın ki asistan doğru cevaplasın.', 'f2f-ai-chatbot' )
					);
					?>
				</p>
				<button type="button" class="button button-secondary" id="f2f_reindex_btn"><?php echo esc_html__( 'Siteyi şimdi tara', 'f2f-ai-chatbot' ); ?></button>
				<span id="f2f_reindex_status"></span>
				<label class="f2f-check"><input type="checkbox" id="wiz_auto_reindex" value="1" <?php checked( $s['auto_reindex'], '1' ); ?> /> <span><?php echo esc_html__( 'İçerik kaydedilince otomatik tara', 'f2f-ai-chatbot' ); ?></span></label>
			</div>
			<div class="f2f-done" id="f2f_done_box" <?php echo $setup_done ? '' : 'hidden'; ?>>
				<strong><?php echo esc_html__( 'Kurulum tamam', 'f2f-ai-chatbot' ); ?></strong>
				<p><?php echo esc_html__( 'Widget sitede hazır. İsterseniz gelişmiş ayarlardan ince ayar yapın.', 'f2f-ai-chatbot' ); ?></p>
			</div>
			<div class="f2f-panel__nav">
				<button type="button" class="button f2f-prev" data-prev="4"><?php echo esc_html__( 'Geri', 'f2f-ai-chatbot' ); ?></button>
				<button type="button" class="button button-primary" id="f2f_finish_wizard"><?php echo esc_html__( 'Kurulumu bitir', 'f2f-ai-chatbot' ); ?></button>
			</div>
		</section>
	</div>

	<details class="f2f-advanced" id="f2f_advanced">
		<summary><?php echo esc_html__( 'Gelişmiş ayarlar (ince ayar)', 'f2f-ai-chatbot' ); ?></summary>
		<form method="post" action="options.php" class="f2f-ai-admin__form">
			<?php settings_fields( 'f2f_ai_chatbot_group' ); ?>
			<input type="hidden" name="<?php echo esc_attr( $opt ); ?>[_partial]" value="1" />
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="f2f_bottom_margin"><?php echo esc_html__( 'Alt boşluk %', 'f2f-ai-chatbot' ); ?></label></th>
					<td><input type="number" min="0" max="40" step="0.5" id="f2f_bottom_margin" name="<?php echo esc_attr( $opt ); ?>[bottom_margin]" value="<?php echo esc_attr( (string) $s['bottom_margin'] ); ?>" class="small-text" /></td>
				</tr>
				<tr>
					<th><label for="f2f_side_margin"><?php echo esc_html__( 'Yan boşluk %', 'f2f-ai-chatbot' ); ?></label></th>
					<td><input type="number" min="0" max="20" step="0.5" id="f2f_side_margin" name="<?php echo esc_attr( $opt ); ?>[side_margin]" value="<?php echo esc_attr( (string) $s['side_margin'] ); ?>" class="small-text" /></td>
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
					<th><label for="f2f_featured_icon"><?php echo esc_html__( 'Öne çıkan ikon', 'f2f-ai-chatbot' ); ?></label></th>
					<td>
						<select id="f2f_featured_icon" name="<?php echo esc_attr( $opt ); ?>[featured_icon]">
							<?php foreach ( $icons as $val => $lab ) : ?>
								<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $s['featured_icon'], $val ); ?>><?php echo esc_html( $lab ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<?php for ( $i = 1; $i <= 4; $i++ ) : ?>
					<tr>
						<th><?php echo esc_html( sprintf( __( 'Hizmet %d ikon', 'f2f-ai-chatbot' ), $i ) ); ?></th>
						<td>
							<select name="<?php echo esc_attr( $opt ); ?>[service_<?php echo esc_attr( (string) $i ); ?>_icon]">
								<?php foreach ( $icons as $val => $lab ) : ?>
									<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $s[ 'service_' . $i . '_icon' ], $val ); ?>><?php echo esc_html( $lab ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				<?php endfor; ?>
				<tr>
					<th><label for="f2f_system_prompt"><?php echo esc_html__( 'Asistan talimatı', 'f2f-ai-chatbot' ); ?></label></th>
					<td>
						<?php $this->field( 'system_prompt', $s, 'textarea', array( 'rows' => 5, 'class' => 'large-text' ) ); ?>
						<p class="description"><?php echo esc_html__( '{site_name} {site_description} {business_notes} {services}', 'f2f-ai-chatbot' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="f2f_notify_email"><?php echo esc_html__( 'Lead bildirim e-postası', 'f2f-ai-chatbot' ); ?></label></th>
					<td>
						<input
							type="email"
							id="f2f_notify_email"
							name="<?php echo esc_attr( $opt ); ?>[notify_email]"
							value="<?php echo esc_attr( (string) $s['notify_email'] ); ?>"
							class="regular-text"
							placeholder="<?php echo esc_attr( (string) get_option( 'admin_email' ) ); ?>"
						/>
						<p class="description"><?php echo esc_html__( 'Ziyaretçi chat bilgileri bu adrese gider. Boşsa WordPress yönetici e-postası kullanılır.', 'f2f-ai-chatbot' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><?php echo esc_html__( 'E-posta bildirimleri', 'f2f-ai-chatbot' ); ?></th>
					<td>
						<input type="hidden" name="<?php echo esc_attr( $opt ); ?>[notify_on_lead]" value="0" />
						<label>
							<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[notify_on_lead]" value="1" <?php checked( $s['notify_on_lead'], '1' ); ?> />
							<?php echo esc_html__( 'Yeni lead gelince hemen mail', 'f2f-ai-chatbot' ); ?>
						</label>
						<br />
						<input type="hidden" name="<?php echo esc_attr( $opt ); ?>[notify_on_summary]" value="0" />
						<label>
							<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[notify_on_summary]" value="1" <?php checked( $s['notify_on_summary'], '1' ); ?> />
							<?php echo esc_html__( 'AI özeti hazır olunca mail', 'f2f-ai-chatbot' ); ?>
						</label>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Gelişmiş ayarları kaydet', 'f2f-ai-chatbot' ) ); ?>
		</form>
	</details>
</div>
