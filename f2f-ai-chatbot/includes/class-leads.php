<?php
/**
 * Lead / conversation storage + 2-minute summary.
 *
 * @package F2F_AI_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores chatbot conversations for sales follow-up.
 */
class F2F_AI_Chatbot_Leads {

	/**
	 * @var self|null
	 */
	private static $instance = null;

	const POST_TYPE = 'f2f_chat_lead';
	const CRON_HOOK = 'f2f_ai_summarize_lead';

	/**
	 * Status options.
	 *
	 * @return array<string, string>
	 */
	public static function statuses() {
		return array(
			'yeni'         => __( 'Yeni', 'f2f-ai-chatbot' ),
			'iletisimde'   => __( 'İletişimde', 'f2f-ai-chatbot' ),
			'teklif'       => __( 'Teklif', 'f2f-ai-chatbot' ),
			'tamamlandi'   => __( 'Tamamlandı', 'f2f-ai-chatbot' ),
			'iptal'        => __( 'İptal', 'f2f-ai-chatbot' ),
		);
	}

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
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'cron_summarize' ), 10, 1 );
		add_action( 'wp_ajax_f2f_ai_update_lead_status', array( $this, 'ajax_update_status' ) );
	}

	/**
	 * Register CPT (hidden from default UI; custom admin page used).
	 */
	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'AI Chat Bot konuşmalar', 'f2f-ai-chatbot' ),
					'singular_name' => __( 'Konuşma', 'f2f-ai-chatbot' ),
					'menu_name'     => __( 'AI Chat Bot konuşmalar', 'f2f-ai-chatbot' ),
				),
				'public'              => false,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'supports'            => array( 'title' ),
				'has_archive'         => false,
			)
		);
	}

	/**
	 * Create lead record.
	 *
	 * @param array<string, string> $data Fields.
	 * @return int|WP_Error
	 */
	public static function save( $data ) {
		$first   = isset( $data['first_name'] ) ? sanitize_text_field( $data['first_name'] ) : '';
		$last    = isset( $data['last_name'] ) ? sanitize_text_field( $data['last_name'] ) : '';
		$phone   = isset( $data['phone'] ) ? sanitize_text_field( $data['phone'] ) : '';
		$email   = isset( $data['email'] ) ? sanitize_email( $data['email'] ) : '';
		$service = isset( $data['service'] ) ? sanitize_text_field( $data['service'] ) : '';
		$intent  = isset( $data['intent'] ) ? sanitize_text_field( $data['intent'] ) : '';

		$title = trim( $first . ' ' . $last );
		if ( '' === $title ) {
			$title = $email ? $email : __( 'İsimsiz lead', 'f2f-ai-chatbot' );
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => $title,
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		update_post_meta( $post_id, '_f2f_first_name', $first );
		update_post_meta( $post_id, '_f2f_last_name', $last );
		update_post_meta( $post_id, '_f2f_phone', $phone );
		update_post_meta( $post_id, '_f2f_email', $email );
		update_post_meta( $post_id, '_f2f_service', $service );
		update_post_meta( $post_id, '_f2f_intent', $intent );
		update_post_meta( $post_id, '_f2f_interest', $service ? $service : __( 'Genel', 'f2f-ai-chatbot' ) );
		update_post_meta( $post_id, '_f2f_status', 'yeni' );
		update_post_meta( $post_id, '_f2f_summary', '' );
		update_post_meta( $post_id, '_f2f_history', array() );
		update_post_meta( $post_id, '_f2f_message_count', 0 );
		update_post_meta( $post_id, '_f2f_summarized', '0' );
		update_post_meta( $post_id, '_f2f_last_activity', time() );
		update_post_meta( $post_id, '_f2f_ip', isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' );

		// First timer: summarize 2 minutes after lead if no chat, or after chat activity.
		self::schedule_summary( (int) $post_id );

		return (int) $post_id;
	}

	/**
	 * Append chat turns and (re)schedule summary in 2 minutes.
	 *
	 * @param int                  $lead_id Lead ID.
	 * @param array<int, array>    $turns   Messages [{role, content}].
	 * @return true|WP_Error
	 */
	public static function append_history( $lead_id, $turns ) {
		$lead_id = absint( $lead_id );
		$post    = get_post( $lead_id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return new WP_Error( 'f2f_lead', __( 'Lead bulunamadı.', 'f2f-ai-chatbot' ), array( 'status' => 404 ) );
		}

		$history = get_post_meta( $lead_id, '_f2f_history', true );
		if ( ! is_array( $history ) ) {
			$history = array();
		}

		foreach ( $turns as $turn ) {
			if ( ! is_array( $turn ) ) {
				continue;
			}
			$role = isset( $turn['role'] ) ? (string) $turn['role'] : '';
			$text = isset( $turn['content'] ) ? sanitize_text_field( (string) $turn['content'] ) : '';
			if ( ! in_array( $role, array( 'user', 'assistant' ), true ) || '' === $text ) {
				continue;
			}
			$history[] = array(
				'role'    => $role,
				'content' => mb_substr( $text, 0, 4000 ),
				'at'      => time(),
			);
		}

		if ( count( $history ) > 80 ) {
			$history = array_slice( $history, -80 );
		}

		$user_msgs = 0;
		foreach ( $history as $h ) {
			if ( isset( $h['role'] ) && 'user' === $h['role'] ) {
				++$user_msgs;
			}
		}

		update_post_meta( $lead_id, '_f2f_history', $history );
		update_post_meta( $lead_id, '_f2f_message_count', $user_msgs );
		update_post_meta( $lead_id, '_f2f_last_activity', time() );
		update_post_meta( $lead_id, '_f2f_summarized', '0' );

		self::schedule_summary( $lead_id );

		return true;
	}

	/**
	 * Schedule summary exactly 2 minutes from now (replaces previous).
	 *
	 * @param int $lead_id Lead ID.
	 */
	public static function schedule_summary( $lead_id ) {
		$lead_id = absint( $lead_id );
		$args    = array( $lead_id );

		// Clear pending runs for this lead.
		while ( $ts = wp_next_scheduled( self::CRON_HOOK, $args ) ) { // phpcs:ignore
			wp_unschedule_event( $ts, self::CRON_HOOK, $args );
		}

		wp_schedule_single_event( time() + 120, self::CRON_HOOK, $args );
		update_post_meta( $lead_id, '_f2f_summary_due', time() + 120 );

		// Nudge WP-Cron on busy hosts (spawn if possible).
		spawn_cron();
	}

	/**
	 * Cron callback.
	 *
	 * @param int $lead_id Lead ID.
	 */
	public static function cron_summarize( $lead_id ) {
		self::summarize_lead( absint( $lead_id ), false );
	}

	/**
	 * Generate and store AI summary.
	 *
	 * @param int  $lead_id Lead ID.
	 * @param bool $force   Force even if recently summarized.
	 * @return array{ok:bool, summary?:string, interest?:string, error?:string}
	 */
	public static function summarize_lead( $lead_id, $force = false ) {
		$lead_id = absint( $lead_id );
		$post    = get_post( $lead_id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return array(
				'ok'    => false,
				'error' => 'Lead yok',
			);
		}

		$last = (int) get_post_meta( $lead_id, '_f2f_last_activity', true );
		$due  = (int) get_post_meta( $lead_id, '_f2f_summary_due', true );
		// If activity happened after this job was scheduled, wait for newer job.
		if ( ! $force && $due && $last && ( $last + 110 ) > time() ) {
			self::schedule_summary( $lead_id );
			return array(
				'ok'    => false,
				'error' => 'Henüz erken — yeniden zamanlandı',
			);
		}

		$history = get_post_meta( $lead_id, '_f2f_history', true );
		if ( ! is_array( $history ) ) {
			$history = array();
		}

		$service = (string) get_post_meta( $lead_id, '_f2f_service', true );
		$intent  = (string) get_post_meta( $lead_id, '_f2f_intent', true );
		$first   = (string) get_post_meta( $lead_id, '_f2f_first_name', true );

		if ( ! $history ) {
			$interest = $service ? $service : __( 'Genel', 'f2f-ai-chatbot' );
			$summary  = "1) İhtiyaç / proje türü: {$interest}\n2) Öne çıkan detaylar: Henüz sohbet mesajı yok; form bilgileri alındı.";
			update_post_meta( $lead_id, '_f2f_interest', $interest );
			update_post_meta( $lead_id, '_f2f_summary', $summary );
			update_post_meta( $lead_id, '_f2f_summarized', '1' );
			update_post_meta( $lead_id, '_f2f_summarized_at', time() );
			return array(
				'ok'       => true,
				'summary'  => $summary,
				'interest' => $interest,
			);
		}

		$transcript = '';
		foreach ( $history as $turn ) {
			$role = ( 'user' === $turn['role'] ) ? 'Müşteri' : 'Asistan';
			$transcript .= $role . ': ' . $turn['content'] . "\n";
		}

		$settings = f2f_ai_chatbot_get_settings();
		$site     = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

		$prompt = "Aşağıdaki müşteri sohbetini satış ekibi için özetle.\n"
			. "Site: {$site}\n"
			. "Seçilen hizmet: {$service}\n"
			. "İlk niyet: {$intent}\n"
			. "Müşteri adı: {$first}\n\n"
			. "SOHBET:\n{$transcript}\n\n"
			. "SADECE geçerli JSON döndür (başka metin yok):\n"
			. '{"interest":"kısa ilgi başlığı (max 6 kelime)","need":"ihtiyaç / proje türü tek cümle","details":"öne çıkan detaylar 1-2 cümle"}';

		$result = F2F_AI_Chatbot_OpenAI::chat(
			(string) $settings['api_key'],
			(string) $settings['model'],
			array(
				array(
					'role'    => 'system',
					'content' => 'Sen satış lead özetleyicisisin. Yalnızca istenen JSON formatında yanıt ver.',
				),
				array(
					'role'    => 'user',
					'content' => $prompt,
				),
			),
			array(
				'max_tokens'  => 300,
				'temperature' => 0.3,
			)
		);

		$interest = $service ? $service : __( 'Genel', 'f2f-ai-chatbot' );
		$need     = $interest;
		$details  = __( 'Sohbet özeti oluşturulamadı; ham konuşma kaydı mevcut.', 'f2f-ai-chatbot' );

		if ( ! empty( $result['ok'] ) && ! empty( $result['content'] ) ) {
			$raw  = trim( $result['content'] );
			$raw  = preg_replace( '/^```(?:json)?\s*|\s*```$/', '', $raw );
			$json = json_decode( $raw, true );
			if ( is_array( $json ) ) {
				if ( ! empty( $json['interest'] ) ) {
					$interest = sanitize_text_field( (string) $json['interest'] );
				}
				if ( ! empty( $json['need'] ) ) {
					$need = sanitize_text_field( (string) $json['need'] );
				}
				if ( ! empty( $json['details'] ) ) {
					$details = sanitize_text_field( (string) $json['details'] );
				}
			} else {
				$details = sanitize_text_field( mb_substr( $raw, 0, 400 ) );
			}
		} elseif ( empty( $settings['api_key'] ) ) {
			// Offline fallback from history.
			$last_user = '';
			foreach ( array_reverse( $history ) as $turn ) {
				if ( 'user' === $turn['role'] ) {
					$last_user = $turn['content'];
					break;
				}
			}
			$need    = $service ? $service : __( 'Genel talep', 'f2f-ai-chatbot' );
			$details = $last_user ? $last_user : $intent;
			$interest = $need;
		}

		$summary = "1) İhtiyaç / proje türü: {$need}\n2) Öne çıkan detaylar: {$details}";

		update_post_meta( $lead_id, '_f2f_interest', $interest );
		update_post_meta( $lead_id, '_f2f_summary', $summary );
		update_post_meta( $lead_id, '_f2f_summarized', '1' );
		update_post_meta( $lead_id, '_f2f_summarized_at', time() );

		return array(
			'ok'       => true,
			'summary'  => $summary,
			'interest' => $interest,
		);
	}

	/**
	 * List leads for admin table.
	 *
	 * @param int $limit Limit.
	 * @return array<int, array<string, mixed>>
	 */
	public static function list_records( $limit = 100 ) {
		$q = new WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		$rows = array();
		foreach ( $q->posts as $post ) {
			$rows[] = self::record_row( $post );
		}
		return $rows;
	}

	/**
	 * @param WP_Post $post Post.
	 * @return array<string, mixed>
	 */
	public static function record_row( $post ) {
		$id = (int) $post->ID;
		return array(
			'id'            => $id,
			'name'          => $post->post_title,
			'first_name'    => (string) get_post_meta( $id, '_f2f_first_name', true ),
			'last_name'     => (string) get_post_meta( $id, '_f2f_last_name', true ),
			'phone'         => (string) get_post_meta( $id, '_f2f_phone', true ),
			'email'         => (string) get_post_meta( $id, '_f2f_email', true ),
			'interest'      => (string) get_post_meta( $id, '_f2f_interest', true ),
			'service'       => (string) get_post_meta( $id, '_f2f_service', true ),
			'summary'       => (string) get_post_meta( $id, '_f2f_summary', true ),
			'status'        => (string) get_post_meta( $id, '_f2f_status', true ) ?: 'yeni',
			'message_count' => (int) get_post_meta( $id, '_f2f_message_count', true ),
			'summarized'    => ( '1' === (string) get_post_meta( $id, '_f2f_summarized', true ) ),
			'date'          => get_the_date( 'd.m.Y H:i:s', $post ),
			'date_raw'      => $post->post_date,
		);
	}

	/**
	 * AJAX status update.
	 */
	public function ajax_update_status() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Forbidden' ), 403 );
		}
		check_ajax_referer( 'f2f_ai_lead_status', 'nonce' );
		$id     = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$status = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';
		if ( ! $id || ! isset( self::statuses()[ $status ] ) ) {
			wp_send_json_error( array( 'message' => 'Geçersiz' ), 400 );
		}
		$post = get_post( $id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			wp_send_json_error( array( 'message' => 'Kayıt yok' ), 404 );
		}
		update_post_meta( $id, '_f2f_status', $status );
		wp_send_json_success(
			array(
				'id'     => $id,
				'status' => $status,
				'label'  => self::statuses()[ $status ],
			)
		);
	}
}
