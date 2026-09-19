<?php
/**
 * Site knowledge indexer — crawl WP content for chat context.
 *
 * @package F2F_AI_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Indexes published site content so the chatbot answers from THIS site only.
 */
class F2F_AI_Chatbot_Knowledge {

	/**
	 * @var self|null
	 */
	private static $instance = null;

	const OPTION      = 'f2f_ai_chatbot_knowledge';
	const META_STAMP  = 'f2f_ai_chatbot_knowledge_updated';
	const MAX_DOCS    = 80;
	const SNIPPET_LEN = 900;

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
		add_action( 'save_post', array( $this, 'maybe_reindex_on_save' ), 20, 2 );
		add_action( 'wp_ajax_f2f_ai_reindex_site', array( $this, 'ajax_reindex' ) );
	}

	/**
	 * Post types to scan.
	 *
	 * @return array<int, string>
	 */
	public static function post_types() {
		$types = array( 'page', 'post' );
		if ( post_type_exists( 'product' ) ) {
			$types[] = 'product';
		}
		/**
		 * Filter indexed post types.
		 *
		 * @param array<int, string> $types Post types.
		 */
		return apply_filters( 'f2f_ai_chatbot_knowledge_post_types', $types );
	}

	/**
	 * Full site reindex.
	 *
	 * @return array{ok:bool, count:int, chars:int, updated:string}
	 */
	public static function reindex() {
		$types = self::post_types();
		$query = new WP_Query(
			array(
				'post_type'              => $types,
				'post_status'            => 'publish',
				'posts_per_page'         => self::MAX_DOCS,
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$docs  = array();
		$chars = 0;

		// Site identity.
		$site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$site_desc = wp_specialchars_decode( get_bloginfo( 'description' ), ENT_QUOTES );
		$docs[]    = array(
			'id'      => 0,
			'type'    => 'site',
			'title'   => $site_name ? $site_name : __( 'Site', 'f2f-ai-chatbot' ),
			'url'     => home_url( '/' ),
			'excerpt' => $site_desc,
			'content' => trim( $site_name . "\n" . $site_desc ),
		);

		foreach ( $query->posts as $post ) {
			$text = self::plain_text( $post->post_content );
			$excerpt = $post->post_excerpt
				? self::plain_text( $post->post_excerpt )
				: mb_substr( $text, 0, 220 );

			$snippet = mb_substr( $text, 0, self::SNIPPET_LEN );
			$docs[]  = array(
				'id'      => (int) $post->ID,
				'type'    => $post->post_type,
				'title'   => wp_specialchars_decode( get_the_title( $post ), ENT_QUOTES ),
				'url'     => get_permalink( $post ),
				'excerpt' => $excerpt,
				'content' => $snippet,
			);
			$chars += mb_strlen( $snippet );
		}

		$payload = array(
			'updated'   => current_time( 'mysql' ),
			'site_name' => $site_name,
			'site_desc' => $site_desc,
			'site_url'  => home_url( '/' ),
			'docs'      => $docs,
			'count'     => count( $docs ),
		);

		update_option( self::OPTION, $payload, false );

		return array(
			'ok'      => true,
			'count'   => count( $docs ),
			'chars'   => $chars,
			'updated' => $payload['updated'],
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get() {
		$data = get_option( self::OPTION, array() );
		return is_array( $data ) ? $data : array();
	}

	/**
	 * Strip HTML / shortcodes to plain text.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function plain_text( $html ) {
		$html = strip_shortcodes( (string) $html );
		$html = wp_strip_all_tags( $html );
		$html = preg_replace( '/\s+/u', ' ', $html );
		return trim( (string) $html );
	}

	/**
	 * Pick relevant docs for a user message (simple keyword score).
	 *
	 * @param string $query User message / service.
	 * @param int    $limit Max docs.
	 * @return array<int, array<string, mixed>>
	 */
	public static function search( $query, $limit = 6 ) {
		$data = self::get();
		$docs = isset( $data['docs'] ) && is_array( $data['docs'] ) ? $data['docs'] : array();
		if ( ! $docs ) {
			return array();
		}

		$q = mb_strtolower( self::plain_text( $query ) );
		$tokens = preg_split( '/[\s\p{P}]+/u', $q, -1, PREG_SPLIT_NO_EMPTY );
		$tokens = array_values(
			array_filter(
				$tokens,
				function ( $t ) {
					return mb_strlen( $t ) >= 3;
				}
			)
		);

		$scored = array();
		foreach ( $docs as $doc ) {
			if ( ! is_array( $doc ) ) {
				continue;
			}
			$hay = mb_strtolower(
				( isset( $doc['title'] ) ? $doc['title'] : '' ) . ' ' .
				( isset( $doc['excerpt'] ) ? $doc['excerpt'] : '' ) . ' ' .
				( isset( $doc['content'] ) ? $doc['content'] : '' )
			);
			$score = 0;
			if ( $tokens ) {
				foreach ( $tokens as $tok ) {
					if ( false !== mb_strpos( $hay, $tok ) ) {
						$score += 2;
					}
				}
			}
			// Always keep site identity doc with small base score.
			if ( isset( $doc['type'] ) && 'site' === $doc['type'] ) {
				$score += 1;
			}
			// Pages slightly preferred.
			if ( isset( $doc['type'] ) && 'page' === $doc['type'] ) {
				$score += 0.5;
			}
			$scored[] = array(
				'score' => $score,
				'doc'   => $doc,
			);
		}

		usort(
			$scored,
			function ( $a, $b ) {
				if ( $a['score'] === $b['score'] ) {
					return 0;
				}
				return ( $a['score'] < $b['score'] ) ? 1 : -1;
			}
		);

		// If nothing matched well, take top pages by recency (first N non-zero or first pages).
		$out = array();
		foreach ( $scored as $row ) {
			if ( count( $out ) >= $limit ) {
				break;
			}
			if ( $row['score'] <= 0 && count( $out ) >= 3 ) {
				continue;
			}
			$out[] = $row['doc'];
		}

		if ( count( $out ) < 3 ) {
			foreach ( $docs as $doc ) {
				if ( count( $out ) >= $limit ) {
					break;
				}
				$already = false;
				foreach ( $out as $o ) {
					if ( isset( $o['id'], $doc['id'] ) && (int) $o['id'] === (int) $doc['id'] ) {
						$already = true;
						break;
					}
				}
				if ( ! $already ) {
					$out[] = $doc;
				}
			}
		}

		return $out;
	}

	/**
	 * Build knowledge block for system prompt.
	 *
	 * @param string $query Message for retrieval.
	 * @param int    $max_chars Max chars.
	 * @return string
	 */
	public static function context_for_prompt( $query = '', $max_chars = 10000 ) {
		$data = self::get();
		if ( empty( $data['docs'] ) ) {
			return '';
		}

		$docs = self::search( $query, 8 );
		$lines = array();
		$used  = 0;

		$site_name = isset( $data['site_name'] ) ? $data['site_name'] : get_bloginfo( 'name' );
		$site_desc = isset( $data['site_desc'] ) ? $data['site_desc'] : get_bloginfo( 'description' );
		$site_url  = isset( $data['site_url'] ) ? $data['site_url'] : home_url( '/' );

		$header = "SİTE KİMLİĞİ\nAd: {$site_name}\nURL: {$site_url}\nAçıklama: {$site_desc}\n";
		$lines[] = $header;
		$used   += mb_strlen( $header );

		$lines[] = "İLGİLİ SİTE İÇERİKLERİ (yalnızca bunlara dayan):";
		foreach ( $docs as $doc ) {
			if ( isset( $doc['type'] ) && 'site' === $doc['type'] ) {
				continue;
			}
			$title   = isset( $doc['title'] ) ? $doc['title'] : '';
			$url     = isset( $doc['url'] ) ? $doc['url'] : '';
			$content = isset( $doc['content'] ) ? $doc['content'] : '';
			$block   = "\n### {$title}\nURL: {$url}\n{$content}\n";
			if ( $used + mb_strlen( $block ) > $max_chars ) {
				break;
			}
			$lines[] = $block;
			$used   += mb_strlen( $block );
		}

		return implode( "\n", $lines );
	}

	/**
	 * Build runtime system prompt from settings + site knowledge.
	 *
	 * @param array<string, mixed> $settings Settings.
	 * @param string               $query    User message / intent.
	 * @param array<string, mixed> $lead     Lead payload.
	 * @return string
	 */
	public static function build_system_prompt( $settings, $query = '', $lead = array() ) {
		$site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$site_desc = wp_specialchars_decode( get_bloginfo( 'description' ), ENT_QUOTES );
		$notes     = isset( $settings['business_notes'] ) ? trim( (string) $settings['business_notes'] ) : '';
		$base      = isset( $settings['system_prompt'] ) ? trim( (string) $settings['system_prompt'] ) : '';

		$services = array();
		if ( ! empty( $settings['featured_label'] ) ) {
			$services[] = $settings['featured_label'];
		}
		for ( $i = 1; $i <= 4; $i++ ) {
			$key = 'service_' . $i . '_label';
			if ( ! empty( $settings[ $key ] ) ) {
				$services[] = $settings[ $key ];
			}
		}
		$services_line = $services ? implode( ', ', $services ) : __( '(panelden henüz tanımlanmadı)', 'f2f-ai-chatbot' );

		if ( '' === $base ) {
			$base = f2f_ai_chatbot_default_settings()['system_prompt'];
		}

		$replacements = array(
			'{site_name}'        => $site_name,
			'{site_description}' => $site_desc,
			'{business_notes}'   => $notes,
			'{services}'         => $services_line,
		);
		$prompt = str_replace( array_keys( $replacements ), array_values( $replacements ), $base );

		$rules = "\n\nZORUNLU KURALLAR:\n"
			. "- Sen YALNIZCA \"{$site_name}\" web sitesinin asistanısın.\n"
			. "- Yanıtlarını bu sitenin taranmış içeriklerine, paneldeki hizmet kutularına ve işletme notlarına dayandır.\n"
			. "- Sitede olmayan ürün/hizmetleri uydurma (ör. bu site makina satıyorsa hosting/domain önerme).\n"
			. "- Bilmiyorsan kısa söyle ve canlı görüşmeye / WhatsApp'a yönlendir.\n"
			. "- Dil: net, nazik, kısa Türkçe.\n";

		if ( $notes ) {
			$rules .= "- İşletme notları: {$notes}\n";
		}
		$rules .= "- Paneldeki hizmet etiketleri: {$services_line}\n";

		$knowledge = self::context_for_prompt( $query, 10000 );
		if ( $knowledge ) {
			$rules .= "\n--- SİTE BİLGİ BANKASI ---\n" . $knowledge . "\n--- BİLGİ BANKASI SONU ---\n";
		} else {
			$rules .= "\n(Uyarı: Site henüz taranmamış. Yalnızca site adı, açıklama ve panel hizmetleriyle yanıt ver; uydurma.)\n";
		}

		if ( is_array( $lead ) && $lead ) {
			$parts = array();
			foreach ( array(
				'first_name' => 'Ad',
				'last_name'  => 'Soyad',
				'phone'      => 'Telefon',
				'email'      => 'E-posta',
				'service'    => 'Seçilen hizmet',
				'intent'     => 'İlk niyet',
			) as $k => $label ) {
				if ( ! empty( $lead[ $k ] ) ) {
					$parts[] = $label . ': ' . sanitize_text_field( (string) $lead[ $k ] );
				}
			}
			if ( $parts ) {
				$rules .= "\nZiyaretçi:\n" . implode( "\n", $parts ) . "\n";
			}
		}

		return $prompt . $rules;
	}

	/**
	 * Reindex when content changes (throttled).
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 */
	public function maybe_reindex_on_save( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( ! $post || ! in_array( $post->post_type, self::post_types(), true ) ) {
			return;
		}
		if ( 'publish' !== $post->post_status ) {
			return;
		}
		$settings = f2f_ai_chatbot_get_settings();
		if ( empty( $settings['auto_reindex'] ) || '1' !== (string) $settings['auto_reindex'] ) {
			return;
		}
		// Light debounce via transient.
		if ( get_transient( 'f2f_ai_reindex_lock' ) ) {
			return;
		}
		set_transient( 'f2f_ai_reindex_lock', 1, 60 );
		self::reindex();
	}

	/**
	 * Admin AJAX reindex.
	 */
	public function ajax_reindex() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Forbidden' ), 403 );
		}
		check_ajax_referer( 'f2f_ai_reindex', 'nonce' );
		$result = self::reindex();
		wp_send_json_success( $result );
	}
}
