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
 * Sends HTML mail to the site owner's notify_email.
 *
 * From address uses the customer site domain (not noreply@f2fbilisim.com)
 * so shared hosts / SPF do not reject the message.
 */
class F2F_AI_Chatbot_Notify {

	const FROM_NAME = 'F2F AI Chatbot';

	/**
	 * Prefer a deliverable From address.
	 * Hub: noreply@f2fbilisim.com. Customer: admin_email (SMTP plugins bind to this).
	 *
	 * @return string
	 */
	public static function from_email() {
		if ( class_exists( 'F2F_AI_Chatbot_Platform' ) && F2F_AI_Chatbot_Platform::is_hub() ) {
			/**
			 * Filter notification From address.
			 *
			 * @param string $email Email.
			 */
			return (string) apply_filters( 'f2f_ai_chatbot_notify_from_email', 'noreply@f2fbilisim.com' );
		}

		$admin = get_option( 'admin_email' );
		if ( is_email( $admin ) ) {
			return (string) apply_filters( 'f2f_ai_chatbot_notify_from_email', $admin );
		}

		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		$host = is_string( $host ) ? strtolower( preg_replace( '/^www\./', '', $host ) ) : '';
		if ( $host && false === strpos( $host, 'localhost' ) && false !== strpos( $host, '.' ) ) {
			$candidate = 'wordpress@' . $host;
			if ( is_email( $candidate ) ) {
				return (string) apply_filters( 'f2f_ai_chatbot_notify_from_email', $candidate );
			}
		}

		return (string) apply_filters( 'f2f_ai_chatbot_notify_from_email', 'wordpress@localhost' );
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
		if ( '1' === (string) get_post_meta( $lead_id, '_f2f_notify_lead_sent', true ) ) {
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
			__( 'Panele yeni bir kayıt düştü. Konuşma bitince (yaklaşık 2 dk) özet + sohbet kaydı ayrıca gönderilir.', 'f2f-ai-chatbot' ),
			$payload,
			false,
			false
		);

		$ok = self::mail( $to, $subject, $body, $payload['email'] );
		if ( $ok ) {
			update_post_meta( $lead_id, '_f2f_notify_lead_sent', '1' );
		}
		return $ok;
	}

	/**
	 * Mail after AI summary is ready — includes conversation transcript.
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
		if ( '1' === (string) get_post_meta( $lead_id, '_f2f_notify_summary_sent', true ) ) {
			return false;
		}

		$payload = self::lead_payload( $lead_id );
		if ( ! $payload ) {
			return false;
		}

		$site    = $payload['site'];
		$subject = sprintf(
			/* translators: 1: site 2: interest */
			__( '[%1$s] Lead özeti + konuşma: %2$s', 'f2f-ai-chatbot' ),
			$site,
			$payload['interest'] ? $payload['interest'] : $payload['name']
		);

		$body = self::render_html(
			__( 'Konuşma özeti hazır', 'f2f-ai-chatbot' ),
			__( 'AI özeti ve sohbet kaydı aşağıda. Satış ekibi bu kayıttan dönüş yapabilir.', 'f2f-ai-chatbot' ),
			$payload,
			true,
			true
		);

		$ok = self::mail( $to, $subject, $body, $payload['email'] );
		if ( $ok ) {
			update_post_meta( $lead_id, '_f2f_notify_summary_sent', '1' );
		}
		return $ok;
	}

	/**
	 * Send a test message to the configured (or given) inbox.
	 *
	 * @param string $to Optional override recipient.
	 * @return array{ok:bool, to:string, from:string, message:string}
	 */
	public static function send_test( $to = '' ) {
		$to = is_email( $to ) ? (string) $to : self::recipient();
		if ( ! $to ) {
			return array(
				'ok'      => false,
				'to'      => '',
				'from'    => self::from_email(),
				'message' => __( 'Geçerli bir bildirim e-postası yok.', 'f2f-ai-chatbot' ),
			);
		}

		$site    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$subject = sprintf(
			/* translators: %s: site name */
			__( '[%s] F2F AI Chatbot test maili', 'f2f-ai-chatbot' ),
			$site
		);
		$from = self::from_email();
		$html = '<!DOCTYPE html><html><body style="margin:0;padding:24px;background:#f3f4f6;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif;">'
			. '<div style="max-width:560px;margin:0 auto;background:#fff;border-radius:16px;padding:24px;border:1px solid #e5e7eb;">'
			. '<h1 style="margin:0 0 12px;font-size:18px;color:#111827;">' . esc_html__( 'Test maili başarılı', 'f2f-ai-chatbot' ) . '</h1>'
			. '<p style="margin:0 0 10px;color:#4b5563;font-size:14px;line-height:1.5;">'
			. esc_html__( 'Bu mesaj eklenti bildirim ayarından gönderildi. Lead ve konuşma mailleri de aynı adrese gidecek.', 'f2f-ai-chatbot' )
			. '</p>'
			. '<p style="margin:0;color:#6b7280;font-size:12px;">'
			. esc_html(
				sprintf(
					/* translators: 1: to 2: from */
					__( 'Alıcı: %1$s · Gönderen: %2$s', 'f2f-ai-chatbot' ),
					$to,
					$from
				)
			)
			. '</p></div></body></html>';

		$ok = self::mail( $to, $subject, $html, '' );
		return array(
			'ok'      => $ok,
			'to'      => $to,
			'from'    => $from,
			'message' => $ok
				? sprintf(
					/* translators: %s: email */
					__( 'Test maili gönderildi: %s (spam klasörünü de kontrol edin)', 'f2f-ai-chatbot' ),
					$to
				)
				: __( 'wp_mail başarısız. Hosting SMTP / e-posta eklentisi gerekebilir.', 'f2f-ai-chatbot' ),
		);
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

		$history = get_post_meta( $lead_id, '_f2f_history', true );
		if ( ! is_array( $history ) ) {
			$history = array();
		}
		$transcript = '';
		foreach ( $history as $turn ) {
			if ( ! is_array( $turn ) || empty( $turn['content'] ) ) {
				continue;
			}
			$role        = ( isset( $turn['role'] ) && 'user' === $turn['role'] )
				? __( 'Müşteri', 'f2f-ai-chatbot' )
				: __( 'Asistan', 'f2f-ai-chatbot' );
			$transcript .= $role . ': ' . (string) $turn['content'] . "\n";
		}

		return array(
			'id'          => (string) $lead_id,
			'name'        => $post->post_title,
			'phone'       => $phone,
			'email'       => $email,
			'interest'    => (string) get_post_meta( $lead_id, '_f2f_interest', true ),
			'summary'     => (string) get_post_meta( $lead_id, '_f2f_summary', true ),
			'transcript'  => trim( $transcript ),
			'status'      => (string) get_post_meta( $lead_id, '_f2f_status', true ),
			'date'        => get_the_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $post ),
			'site'        => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'panel'       => admin_url( 'admin.php?page=f2f-ai-conversations' ),
			'wa'          => $wa ? 'https://wa.me/' . $wa : '',
			'tel'         => $phone ? 'tel:' . preg_replace( '/\D+/', '', $phone ) : '',
			'mailto'      => $email ? 'mailto:' . $email : '',
		);
	}

	/**
	 * @param string               $title          Title.
	 * @param string               $intro          Intro.
	 * @param array<string,string> $p              Payload.
	 * @param bool                 $with_summary   Include summary block.
	 * @param bool                 $with_transcript Include chat transcript.
	 * @return string
	 */
	private static function render_html( $title, $intro, $p, $with_summary, $with_transcript = false ) {
		$rows = array(
			__( 'Müşteri', 'f2f-ai-chatbot' ) => esc_html( $p['name'] ),
			__( 'Telefon', 'f2f-ai-chatbot' ) => esc_html( $p['phone'] ? $p['phone'] : '—' ),
			__( 'E-posta', 'f2f-ai-chatbot' ) => esc_html( $p['email'] ? $p['email'] : '—' ),
			__( 'İlgi', 'f2f-ai-chatbot' )    => esc_html( $p['interest'] ? $p['interest'] : '—' ),
			__( 'Tarih', 'f2f-ai-chatbot' )   => esc_html( $p['date'] ),
			__( 'Site', 'f2f-ai-chatbot' )    => esc_html( $p['site'] ),
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
				. '<div style="font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:#6b7280;margin-bottom:8px;">'
				. esc_html__( 'AI özeti', 'f2f-ai-chatbot' )
				. '</div>'
				. esc_html( $p['summary'] )
				. '</div>';
		}

		$transcript_block = '';
		if ( $with_transcript && ! empty( $p['transcript'] ) ) {
			$transcript_block = '<div style="margin:16px 0 0;padding:14px 16px;background:#fff;border:1px solid #e5e7eb;border-radius:12px;color:#374151;font-size:13px;line-height:1.55;white-space:pre-wrap;">'
				. '<div style="font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:#6b7280;margin-bottom:8px;">'
				. esc_html__( 'Konuşma kaydı', 'f2f-ai-chatbot' )
				. '</div>'
				. esc_html( $p['transcript'] )
				. '</div>';
		} elseif ( $with_transcript ) {
			$transcript_block = '<p style="margin:16px 0 0;color:#9ca3af;font-size:12px;">'
				. esc_html__( 'Bu kayıtta henüz sohbet mesajı yok (yalnızca form).', 'f2f-ai-chatbot' )
				. '</p>';
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

		$from_note = self::from_email();

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
			. $transcript_block
			. '<div style="margin-top:18px;">' . $actions . '</div>'
			. '</div></div>'
			. '<p style="max-width:560px;margin:14px auto 0;color:#9ca3af;font-size:11px;text-align:center;">'
			. esc_html( $from_note )
			. '</p>'
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
		$via     = '';
		$error   = '';
		$from    = self::from_email();
		$is_hub  = class_exists( 'F2F_AI_Chatbot_Platform' ) && F2F_AI_Chatbot_Platform::is_hub();

		// Customer sites: try F2F hub relay first (reliable SPF from f2fbilisim.com).
		if ( ! $is_hub ) {
			$relay = self::mail_via_hub( $to, $subject, $html, $reply_to );
			if ( ! empty( $relay['ok'] ) ) {
				self::store_last( true, $to, $subject, isset( $relay['from'] ) ? $relay['from'] : 'noreply@f2fbilisim.com', 'hub', '' );
				do_action( 'f2f_ai_chatbot_notify_sent', true, $to, $subject );
				return true;
			}
			$error = isset( $relay['error'] ) ? (string) $relay['error'] : '';
		}

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . self::FROM_NAME . ' <' . $from . '>',
		);
		if ( $reply_to && is_email( $reply_to ) ) {
			$headers[] = 'Reply-To: ' . $reply_to;
		}

		$mail_error = '';
		$fail_cb    = static function ( $wp_error ) use ( &$mail_error ) {
			if ( is_wp_error( $wp_error ) ) {
				$mail_error = $wp_error->get_error_message();
			}
		};
		add_action( 'wp_mail_failed', $fail_cb );

		$filter = static function ( $phpmailer ) use ( $from ) {
			$phpmailer->setFrom( $from, self::FROM_NAME, false );
			// Don't force Sender — breaks many shared hosts / SMTP plugins.
		};
		add_action( 'phpmailer_init', $filter );

		$ok = wp_mail( $to, $subject, $html, $headers );

		remove_action( 'phpmailer_init', $filter );
		remove_action( 'wp_mail_failed', $fail_cb );

		$via = $is_hub ? 'hub-local' : 'local';
		if ( ! $ok && $mail_error ) {
			$error = $mail_error;
			update_option( 'f2f_ai_notify_last_error', $mail_error, false );
		}

		// If local failed and we haven't tried hub yet (shouldn't happen), or hub was down earlier — one more try.
		if ( ! $ok && ! $is_hub && false === strpos( $error, 'hub' ) ) {
			$relay = self::mail_via_hub( $to, $subject, $html, $reply_to );
			if ( ! empty( $relay['ok'] ) ) {
				self::store_last( true, $to, $subject, isset( $relay['from'] ) ? $relay['from'] : 'noreply@f2fbilisim.com', 'hub-fallback', '' );
				do_action( 'f2f_ai_chatbot_notify_sent', true, $to, $subject );
				return true;
			}
			if ( ! empty( $relay['error'] ) ) {
				$error = trim( $error . ' | hub: ' . $relay['error'] );
			}
		}

		self::store_last( (bool) $ok, $to, $subject, $from, $via, $error );
		do_action( 'f2f_ai_chatbot_notify_sent', (bool) $ok, $to, $subject );

		return (bool) $ok;
	}

	/**
	 * Send mail through F2F platform hub.
	 *
	 * @param string $to       To.
	 * @param string $subject  Subject.
	 * @param string $html     HTML.
	 * @param string $reply_to Reply-To.
	 * @return array{ok:bool, from?:string, error?:string}
	 */
	private static function mail_via_hub( $to, $subject, $html, $reply_to = '' ) {
		if ( ! class_exists( 'F2F_AI_Chatbot_Platform' ) || ! class_exists( 'F2F_AI_Chatbot_License' ) ) {
			return array(
				'ok'    => false,
				'error' => 'platform missing',
			);
		}

		$s       = f2f_ai_chatbot_get_settings();
		$license = isset( $s['license_key'] ) ? F2F_AI_Chatbot_License::normalize( (string) $s['license_key'] ) : '';
		if ( ! $license ) {
			return array(
				'ok'    => false,
				'error' => 'no license for hub mail',
			);
		}

		$url = F2F_AI_Chatbot_Platform::notify_endpoint();
		$res = wp_remote_post(
			$url,
			array(
				'timeout'     => 20,
				'redirection' => 2,
				'headers'     => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $license,
					'Accept'        => 'application/json',
					'User-Agent'    => 'F2F-AI-Chatbot/' . F2F_AI_CHATBOT_VERSION,
				),
				'body'        => wp_json_encode(
					array(
						'license'  => $license,
						'to'       => $to,
						'subject'  => $subject,
						'html'     => $html,
						'reply_to' => $reply_to,
						'site_url' => home_url( '/' ),
					)
				),
			)
		);

		if ( is_wp_error( $res ) ) {
			return array(
				'ok'    => false,
				'error' => $res->get_error_message(),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $res );
		$data = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( is_array( $data ) && ! empty( $data['ok'] ) ) {
			return array(
				'ok'   => true,
				'from' => isset( $data['from'] ) ? (string) $data['from'] : 'noreply@f2fbilisim.com',
			);
		}

		$err = '';
		if ( is_array( $data ) ) {
			if ( ! empty( $data['error'] ) ) {
				$err = (string) $data['error'];
			} elseif ( ! empty( $data['message'] ) ) {
				$err = (string) $data['message'];
			}
		}
		if ( ! $err ) {
			$err = sprintf( 'hub mail HTTP %d', $code );
		}

		return array(
			'ok'    => false,
			'error' => $err,
		);
	}

	/**
	 * Persist last attempt for admin diagnostics.
	 *
	 * @param bool   $ok      Success.
	 * @param string $to      To.
	 * @param string $subject Subject.
	 * @param string $from    From.
	 * @param string $via     Path.
	 * @param string $error   Error.
	 */
	private static function store_last( $ok, $to, $subject, $from, $via, $error = '' ) {
		update_option(
			'f2f_ai_notify_last',
			array(
				'ok'      => (bool) $ok,
				'to'      => $to,
				'subject' => $subject,
				'from'    => $from,
				'via'     => $via,
				'error'   => $error,
				'at'      => time(),
			),
			false
		);
		if ( $error ) {
			update_option( 'f2f_ai_notify_last_error', $error, false );
		}
	}
}
