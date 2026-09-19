<?php
/**
 * Lead storage (CPT).
 *
 * @package F2F_AI_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores chatbot leads as custom posts.
 */
class F2F_AI_Chatbot_Leads {

	/**
	 * @var self|null
	 */
	private static $instance = null;

	const POST_TYPE = 'f2f_chat_lead';

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
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'column_content' ), 10, 2 );
	}

	/**
	 * Register CPT.
	 */
	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Chatbot Leadler', 'f2f-ai-chatbot' ),
					'singular_name' => __( 'Lead', 'f2f-ai-chatbot' ),
					'menu_name'     => __( 'AI Leadler', 'f2f-ai-chatbot' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => 'options-general.php',
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'supports'            => array( 'title' ),
				'has_archive'         => false,
			)
		);
	}

	/**
	 * Save a lead.
	 *
	 * @param array<string, string> $data Lead fields.
	 * @return int|WP_Error Post ID.
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
		update_post_meta( $post_id, '_f2f_ip', isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' );

		return (int) $post_id;
	}

	/**
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function columns( $columns ) {
		return array(
			'cb'      => $columns['cb'],
			'title'   => __( 'İsim', 'f2f-ai-chatbot' ),
			'email'   => __( 'E-posta', 'f2f-ai-chatbot' ),
			'phone'   => __( 'Telefon', 'f2f-ai-chatbot' ),
			'service' => __( 'Hizmet', 'f2f-ai-chatbot' ),
			'date'    => __( 'Tarih', 'f2f-ai-chatbot' ),
		);
	}

	/**
	 * @param string $column Column key.
	 * @param int    $post_id Post ID.
	 */
	public function column_content( $column, $post_id ) {
		switch ( $column ) {
			case 'email':
				echo esc_html( (string) get_post_meta( $post_id, '_f2f_email', true ) );
				break;
			case 'phone':
				echo esc_html( (string) get_post_meta( $post_id, '_f2f_phone', true ) );
				break;
			case 'service':
				echo esc_html( (string) get_post_meta( $post_id, '_f2f_service', true ) );
				break;
		}
	}
}
