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

$prefix  = ! empty( $notify_card_id ) ? (string) $notify_card_id : 'f2f_mail';
$email   = ! empty( $s['notify_email'] ) ? (string) $s['notify_email'] : (string) get_option( 'admin_email' );
$on_lead = isset( $s['notify_on_lead'] ) ? (string) $s['notify_on_lead'] : '1';
$on_sum  = isset( $s['notify_on_summary'] ) ? (string) $s['notify_on_summary'] : '1';
$last    = get_option( 'f2f_ai_notify_last', array() );
$last_ok = is_array( $last ) && ! empty( $last['ok'] );
$last_at = ( is_array( $last ) && ! empty( $last['at'] ) ) ? (int) $last['at'] : 0;
$last_via = ( is_array( $last ) && ! empty( $last['via'] ) ) ? (string) $last['via'] : '';
$last_err = ( is_array( $last ) && ! empty( $last['error'] ) ) ? (string) $last['error'] : '';
?>
<section class="f2f-mail-card" id="<?php echo esc_attr( $prefix ); ?>_card" aria-labelledby="<?php echo esc_attr( $prefix ); ?>_title">
	<div class="f2f-mail-card__head">
		<div>
			<p class="f2f-mail-card__eyebrow"><?php echo esc_html__( 'Otomatik bildirim', 'f2f-ai-chatbot' ); ?></p>
			<h2 id="<?php echo esc_attr( $prefix ); ?>_title"><?php echo esc_html__( 'Lead e-posta ayarı', 'f2f-ai-chatbot' ); ?></h2>
			<p class="f2f-mail-card__lede">
				<?php echo esc_html__( 'Form ve konuşma kayıtları aşağıdaki adrese gider. Konuşma bitince (~1 dk) özet + sohbet F2F hub üzerinden iletilir.', 'f2f-ai-chatbot' ); ?>
			</p>
		</div>
	</div>
	<div class="f2f-mail-card__body">
		<label class="f2f-mail-card__field">
			<span><?php echo esc_html__( 'E-posta adresi', 'f2f-ai-chatbot' ); ?></span>
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
				<span><?php echo esc_html__( 'Form doldurulunca hemen mail gönder', 'f2f-ai-chatbot' ); ?></span>
			</label>
			<label class="f2f-mail-card__check">
				<input type="checkbox" id="<?php echo esc_attr( $prefix ); ?>_on_summary" value="1" <?php checked( $on_sum, '1' ); ?> />
				<span><?php echo esc_html__( 'Konuşma özeti + sohbet kaydı hazır olunca mail gönder', 'f2f-ai-chatbot' ); ?></span>
			</label>
		</div>
		<div class="f2f-mail-card__actions">
			<button type="button" class="button button-primary f2f-mail-card__save" id="<?php echo esc_attr( $prefix ); ?>_save" data-prefix="<?php echo esc_attr( $prefix ); ?>">
				<?php echo esc_html__( 'E-posta ayarını kaydet', 'f2f-ai-chatbot' ); ?>
			</button>
			<button type="button" class="button f2f-mail-card__test" id="<?php echo esc_attr( $prefix ); ?>_test" data-prefix="<?php echo esc_attr( $prefix ); ?>">
				<?php echo esc_html__( 'Test maili gönder', 'f2f-ai-chatbot' ); ?>
			</button>
			<span class="f2f-mail-card__status" id="<?php echo esc_attr( $prefix ); ?>_status" hidden></span>
		</div>
		<?php if ( $last_at ) : ?>
			<p class="f2f-mail-card__inline-hint <?php echo $last_ok ? '' : 'is-error'; ?>">
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: time 2: via 3: ok/fail */
						__( 'Son deneme: %1$s · yol: %2$s · %3$s', 'f2f-ai-chatbot' ),
						wp_date( 'd.m.Y H:i', $last_at ),
						$last_via ? $last_via : '—',
						$last_ok ? __( 'başarılı', 'f2f-ai-chatbot' ) : __( 'başarısız', 'f2f-ai-chatbot' )
					)
				);
				if ( ! $last_ok && $last_err ) {
					echo ' — ' . esc_html( $last_err );
				}
				?>
			</p>
		<?php else : ?>
			<p class="f2f-mail-card__inline-hint">
				<?php echo esc_html__( 'Test maili önce F2F hub üzerinden gider (noreply@f2fbilisim.com). Spam klasörünü kontrol edin. Hub (f2fbilisim.com) de aynı eklenti sürümünde olmalı.', 'f2f-ai-chatbot' ); ?>
			</p>
		<?php endif; ?>
	</div>
</section>
