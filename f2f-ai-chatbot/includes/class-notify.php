<?php
/**
 * Email notifications for new leads / summaries.
 *
 * @package F2F_AI_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends HTML mail from noreply@f2fbilisim.com to the site owner's notify_email.
 */
class F2F_AI_Chatbot_Notify {

	const FROM_EMAIL = 'noreply@f2fbilisim.com';
	const FROM_NAME  = 'F2F AI Chatbot';

	/**
	 * @return string
	 */
	public static function from_email() {
		/**
		 * Filter notification From address.
		 *
		 * @param string $email Email.
		 */
		return (string) apply_filters( 'f2f_ai_chatbot_notify_from_email', self::FROM_EMAIL );
	}

	/**
	 * Destination inbox from settings (fallback: admin email).
	 *
	 * @return string
	 */
	public static function recipient() {
		$s = f2f_ai_chatbot_get_settings();
		if ( ! empty( $s['notify_email'] ) && is_email( $s['notify_email'] ) ) {
			return (string) $s['notify_email'];
		}
		$admin = get_option( 'admin_email' );
		return is_email( $admin ) ? (string) $admin : '';
	}

	/**
	 * @return bool
	 */
	public static function enabled_on_lead() {
		$s = f2f_ai_chatbot_get_settings();
		return empty( $s['notify_on_lead'] ) ? false : ( '1' === (string) $s['notify_on_lead'] );
	}

	/**
	 * @return bool
	 */
	public static function enabled_on_summary() {
		$s = f2f_ai_chatbot_get_settings();
		return empty( $s['notify_on_summary'] ) ? false : ( '1' === (string) $s['notify_on_summary'] );
	}

	/**
	 * Immediate mail when lead form is submitted.
	 *
	 * @param int $lead_id Lead ID.
	 * @return bool
	 */
	public static function send_new_lead( $lead_id ) {
		if ( ! self::enabled_on_lead() ) {
			return false;
		}
		$to = self::recipient();
		if ( ! $to ) {
			return false;
		}
		$payload = self::lead_payload( $lead_id );
		if ( ! $payload ) {
			return false;
		}

		$site    = $payload['site'];
		$subject = sprintf(
			/* translators: 1: site 2: name */
			__( '[%1$s] Yeni lead: %2$s', 'f2f-ai-chatbot' ),
			$site,
			$payload['name']
		);

		$body = self::render_html(
			__( 'Yeni lead geldi', 'f2f-ai-chatbot' ),
			__( 'Panele yeni bir kayıt düştü. Özet sohbet bitince (yaklaşık 2 dk) ayrıca gönderilir.', 'f2f-ai-chatbot' ),
			$payload,
			false
		);

		return self::mail( $to, $subject, $body, $payload['email'] );
	}

	/**
	 * Mail after AI summary is ready.
	 *
	 * @param int $lead_id Lead ID.
	 * @return bool
	 */
	public static function send_summary( $lead_id ) {
		if ( ! self::enabled_on_summary() ) {
			return false;
		}
		$to = self::recipient();
		if ( ! $to ) {
			return false;
		}
		$payload = self::lead_payload( $lead_id );
		if ( ! $payload ) {
			return false;
		}

		$site    = $payload['site'];
		$subject = sprintf(
			/* translators: 1: site 2: interest */
			__( '[%1$s] Lead özeti: %2$s', 'f2f-ai-chatbot' ),
			$site,
			$payload['interest'] ? $payload['interest'] : $payload['name']
		);

		$body = self::render_html(
			__( 'Konuşma özeti hazır', 'f2f-ai-chatbot' ),
			__( 'AI özeti oluştu. Satış ekibi bu kayıttan dönüş yapabilir.', 'f2f-ai-chatbot' ),
			$payload,
			true
		);

		return self::mail( $to, $subject, $body, $payload['email'] );
	}

	/**
	 * @param int $lead_id Lead ID.
	 * @return array<string, string>|null
	 */
	private static function lead_payload( $lead_id ) {
		$lead_id = absint( $lead_id );
		$post    = get_post( $lead_id );
		if ( ! $post || F2F_AI_Chatbot_Leads::POST_TYPE !== $post->post_type ) {
			return null;
		}

		$phone = (string) get_post_meta( $lead_id, '_f2f_phone', true );
		$email = (string) get_post_meta( $lead_id, '_f2f_email', true );
		$wa    = preg_replace( '/\D+/', '', $phone );
		if ( $wa && 0 === strpos( $wa, '0' ) ) {
			$wa = '90' . substr( $wa, 1 );
		}

		return array(
			'id'       => (string) $lead_id,
			'name'     => $post->post_title,
			'phone'    => $phone,
			'email'    => $email,
			'interest' => (string) get_post_meta( $lead_id, '_f2f_interest', true ),
			'summary'  => (string) get_post_meta( $lead_id, '_f2f_summary', true ),
			'status'   => (string) get_post_meta( $lead_id, '_f2f_status', true ),
			'date'     => get_the_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $post ),
			'site'     => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'panel'    => admin_url( 'admin.php?page=f2f-ai-conversations' ),
			'wa'       => $wa ? 'https://wa.me/' . $wa : '',
			'tel'      => $phone ? 'tel:' . preg_replace( '/\D+/', '', $phone ) : '',
			'mailto'   => $email ? 'mailto:' . $email : '',
		);
	}

	/**
	 * @param string               $title   Title.
	 * @param string               $intro   Intro.
	 * @param array<string,string> $p       Payload.
	 * @param bool                 $with_summary Include summary block.
	 * @return string
	 */
	private static function render_html( $title, $intro, $p, $with_summary ) {
		$rows = array(
			__( 'Müşteri', 'f2f-ai-chatbot' )  => esc_html( $p['name'] ),
			__( 'Telefon', 'f2f-ai-chatbot' )  => esc_html( $p['phone'] ? $p['phone'] : '—' ),
			__( 'E-posta', 'f2f-ai-chatbot' )  => esc_html( $p['email'] ? $p['email'] : '—' ),
			__( 'İlgi', 'f2f-ai-chatbot' )     => esc_html( $p['interest'] ? $p['interest'] : '—' ),
			__( 'Tarih', 'f2f-ai-chatbot' )    => esc_html( $p['date'] ),
			__( 'Site', 'f2f-ai-chatbot' )     => esc_html( $p['site'] ),
		);

		$tr = '';
		foreach ( $rows as $label => $value ) {
			$tr .= '<tr><td style="padding:8px 0;color:#6b7280;font-size:12px;width:110px;vertical-align:top;">'
				. esc_html( $label )
				. '</td><td style="padding:8px 0;color:#111827;font-size:14px;font-weight:600;">'
				. $value
				. '</td></tr>';
		}

		$summary_block = '';
		if ( $with_summary && ! empty( $p['summary'] ) ) {
			$summary_block = '<div style="margin:16px 0 0;padding:14px 16px;background:#f8faf9;border:1px solid #e5e7eb;border-radius:12px;color:#374151;font-size:13px;line-height:1.55;white-space:pre-wrap;">'
				. esc_html( $p['summary'] )
				. '</div>';
		}

		$actions = '';
		if ( ! empty( $p['tel'] ) ) {
			$actions .= '<a href="' . esc_url( $p['tel'] ) . '" style="display:inline-block;margin:0 8px 8px 0;padding:10px 14px;border-radius:10px;background:#0b6e4f;color:#fff;text-decoration:none;font-weight:700;font-size:13px;">'
				. esc_html__( 'Ara', 'f2f-ai-chatbot' ) . '</a>';
		}
		if ( ! empty( $p['mailto'] ) ) {
			$actions .= '<a href="' . esc_url( $p['mailto'] ) . '" style="display:inline-block;margin:0 8px 8px 0;padding:10px 14px;border-radius:10px;background:#111827;color:#fff;text-decoration:none;font-weight:700;font-size:13px;">'
				. esc_html__( 'E-posta', 'f2f-ai-chatbot' ) . '</a>';
		}
		if ( ! empty( $p['wa'] ) ) {
			$actions .= '<a href="' . esc_url( $p['wa'] ) . '" style="display:inline-block;margin:0 8px 8px 0;padding:10px 14px;border-radius:10px;background:#16a34a;color:#fff;text-decoration:none;font-weight:700;font-size:13px;">WhatsApp</a>';
		}
		$actions .= '<a href="' . esc_url( $p['panel'] ) . '" style="display:inline-block;margin:0 8px 8px 0;padding:10px 14px;border-radius:10px;background:#fff;color:#0b6e4f;border:1px solid #0b6e4f;text-decoration:none;font-weight:700;font-size:13px;">'
			. esc_html__( 'Panele git', 'f2f-ai-chatbot' ) . '</a>';

		return '<!DOCTYPE html><html><body style="margin:0;padding:24px;background:#f3f4f6;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif;">'
			. '<div style="max-width:560px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden;border:1px solid #e5e7eb;">'
			. '<div style="padding:18px 20px;background:linear-gradient(135deg,#0f1f1a,#0b6e4f);color:#fff;">'
			. '<div style="font-size:11px;letter-spacing:.12em;text-transform:uppercase;opacity:.8;">F2F AI Chatbot</div>'
			. '<h1 style="margin:8px 0 0;font-size:20px;line-height:1.3;">' . esc_html( $title ) . '</h1>'
			. '</div>'
			. '<div style="padding:20px;">'
			. '<p style="margin:0 0 14px;color:#4b5563;font-size:14px;line-height:1.5;">' . esc_html( $intro ) . '</p>'
			. '<table style="width:100%;border-collapse:collapse;">' . $tr . '</table>'
			. $summary_block
			. '<div style="margin-top:18px;">' . $actions . '</div>'
			. '</div></div>'
			. '<p style="max-width:560px;margin:14px auto 0;color:#9ca3af;font-size:11px;text-align:center;">noreply@f2fbilisim.com</p>'
			. '</body></html>';
	}

	/**
	 * @param string $to       To.
	 * @param string $subject  Subject.
	 * @param string $html     HTML body.
	 * @param string $reply_to Optional Reply-To (lead email).
	 * @return bool
	 */
	private static function mail( $to, $subject, $html, $reply_to = '' ) {
		$from = self::from_email();
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . self::FROM_NAME . ' <' . $from . '>',
		);
		if ( $reply_to && is_email( $reply_to ) ) {
			$headers[] = 'Reply-To: ' . $reply_to;
		}

		$filter = function ( $phpmailer ) use ( $from ) {
			$phpmailer->setFrom( $from, self::FROM_NAME, false );
		};
		add_action( 'phpmailer_init', $filter );

		$ok = wp_mail( $to, $subject, $html, $headers );

		remove_action( 'phpmailer_init', $filter );

		/**
		 * Fires after a notification attempt.
		 *
		 * @param bool   $ok      Success.
		 * @param string $to      Recipient.
		 * @param string $subject Subject.
		 */
		do_action( 'f2f_ai_chatbot_notify_sent', $ok, $to, $subject );

		return (bool) $ok;
	}
}
