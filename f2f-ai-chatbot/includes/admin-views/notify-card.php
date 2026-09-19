<?php
/**
 * Always-visible lead email notification card.
 *
 * Expected: $s (settings array). Optional: $notify_card_id (DOM id prefix).
 *
 * @package F2F_AI_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$prefix = ! empty( $notify_card_id ) ? (string) $notify_card_id : 'f2f_mail';
$email  = ! empty( $s['notify_email'] ) ? (string) $s['notify_email'] : (string) get_option( 'admin_email' );
$on_lead = isset( $s['notify_on_lead'] ) ? (string) $s['notify_on_lead'] : '1';
$on_sum  = isset( $s['notify_on_summary'] ) ? (string) $s['notify_on_summary'] : '1';
?>
<section class="f2f-mail-card" id="<?php echo esc_attr( $prefix ); ?>_card" aria-labelledby="<?php echo esc_attr( $prefix ); ?>_title">
	<div class="f2f-mail-card__head">
		<div>
			<p class="f2f-mail-card__eyebrow"><?php echo esc_html__( 'Otomatik bildirim', 'f2f-ai-chatbot' ); ?></p>
			<h2 id="<?php echo esc_attr( $prefix ); ?>_title"><?php echo esc_html__( 'Lead e-posta ayarı', 'f2f-ai-chatbot' ); ?></h2>
			<p class="f2f-mail-card__lede">
				<?php echo esc_html__( 'Panele lead düşünce bu adrese otomatik mail gider. Gönderen: noreply@f2fbilisim.com', 'f2f-ai-chatbot' ); ?>
			</p>
		</div>
		<span class="f2f-mail-card__from">noreply@f2fbilisim.com</span>
	</div>
	<div class="f2f-mail-card__body">
		<label class="f2f-mail-card__field">
			<span><?php echo esc_html__( 'Bildirim e-postası (müşteri / satış ekibi)', 'f2f-ai-chatbot' ); ?></span>
			<input
				type="email"
				id="<?php echo esc_attr( $prefix ); ?>_email"
				class="f2f-mail-card__input"
				value="<?php echo esc_attr( $email ); ?>"
				placeholder="satis@sirketiniz.com"
				autocomplete="email"
			/>
		</label>
		<div class="f2f-mail-card__toggles">
			<label class="f2f-mail-card__check">
				<input type="checkbox" id="<?php echo esc_attr( $prefix ); ?>_on_lead" value="1" <?php checked( $on_lead, '1' ); ?> />
				<span><?php echo esc_html__( 'Yeni lead gelince hemen mail at', 'f2f-ai-chatbot' ); ?></span>
			</label>
			<label class="f2f-mail-card__check">
				<input type="checkbox" id="<?php echo esc_attr( $prefix ); ?>_on_summary" value="1" <?php checked( $on_sum, '1' ); ?> />
				<span><?php echo esc_html__( 'AI özeti hazır olunca tekrar mail at', 'f2f-ai-chatbot' ); ?></span>
			</label>
		</div>
		<div class="f2f-mail-card__actions">
			<button type="button" class="button button-primary f2f-mail-card__save" id="<?php echo esc_attr( $prefix ); ?>_save" data-prefix="<?php echo esc_attr( $prefix ); ?>">
				<?php echo esc_html__( 'E-posta ayarını kaydet', 'f2f-ai-chatbot' ); ?>
			</button>
			<span class="f2f-mail-card__status" id="<?php echo esc_attr( $prefix ); ?>_status" hidden></span>
		</div>
	</div>
</section>
