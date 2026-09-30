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
 *
 * Also indexes WooCommerce / post categories and panel service labels —
 * otherwise a service like "Yurt Dışı Dil Okulları" exists as a category
 * but the bot claims the site has no information.
 */
class F2F_AI_Chatbot_Knowledge {

	/**
	 * @var self|null
	 */
	private static $instance = null;

	const OPTION      = 'f2f_ai_chatbot_knowledge';
	const META_STAMP  = 'f2f_ai_chatbot_knowledge_updated';
	const MAX_DOCS    = 160;
	const SNIPPET_LEN = 1400;

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
		add_action( 'created_term', array( $this, 'maybe_reindex_on_term' ), 20, 3 );
		add_action( 'edited_term', array( $this, 'maybe_reindex_on_term' ), 20, 3 );
		add_action( 'delete_term', array( $this, 'maybe_reindex_on_term' ), 20, 3 );
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
		// Other public CPTs (camps, tours, etc.) if registered.
		$public = get_post_types(
			array(
				'public'             => true,
				'publicly_queryable' => true,
			),
			'names'
		);
		foreach ( (array) $public as $pt ) {
			if ( in_array( $pt, array( 'attachment', 'revision', 'nav_menu_item' ), true ) ) {
				continue;
			}
			if ( ! in_array( $pt, $types, true ) ) {
				$types[] = $pt;
			}
		}
		/**
		 * Filter indexed post types.
		 *
		 * @param array<int, string> $types Post types.
		 */
		return apply_filters( 'f2f_ai_chatbot_knowledge_post_types', $types );
	}

	/**
	 * Taxonomies to index (categories / product categories).
	 *
	 * @return array<int, string>
	 */
	public static function taxonomies() {
		$taxes = array( 'category' );
		if ( taxonomy_exists( 'product_cat' ) ) {
			$taxes[] = 'product_cat';
		}
		if ( taxonomy_exists( 'product_tag' ) ) {
			$taxes[] = 'product_tag';
		}
		/**
		 * Filter indexed taxonomies.
		 *
		 * @param array<int, string> $taxes Taxonomies.
		 */
		return apply_filters( 'f2f_ai_chatbot_knowledge_taxonomies', $taxes );
	}

	/**
	 * Full site reindex.
	 *
	 * @return array{ok:bool, count:int, chars:int, updated:string}
	 */
	public static function reindex() {
		$docs  = array();
		$chars = 0;

		$site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$site_desc = wp_specialchars_decode( get_bloginfo( 'description' ), ENT_QUOTES );
		$docs[]    = array(
			'id'      => 0,
			'type'    => 'site',
			'title'   => $site_name ? $site_name : __( 'Site', 'f2f-ai-chatbot' ),
			'url'     => home_url( '/' ),
			'excerpt' => $site_desc,
			'content' => trim( $site_name . "\n" . $site_desc ),
			'tags'    => '',
		);

		// Panel service labels — always known offerings.
		$settings = f2f_ai_chatbot_get_settings();
		foreach ( self::panel_services( $settings ) as $i => $label ) {
			$docs[] = array(
				'id'      => -100 - $i,
				'type'    => 'service',
				'title'   => $label,
				'url'     => home_url( '/' ),
				'excerpt' => $label,
				'content' => sprintf(
					/* translators: 1: site 2: service */
					__( '%1$s sitesinde sunulan hizmet: %2$s. Ziyaretçi bu hizmeti seçtiğinde ilgili ürün, kategori ve kamp içeriklerine göre yardımcı ol.', 'f2f-ai-chatbot' ),
					$site_name,
					$label
				),
				'tags'    => $label,
			);
		}

		// Category / product_cat archives (critical for WooCommerce shops).
		foreach ( self::taxonomies() as $taxonomy ) {
			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
					'number'     => 80,
				)
			);
			if ( is_wp_error( $terms ) || ! $terms ) {
				continue;
			}
			foreach ( $terms as $term ) {
				$child_titles = self::term_item_titles( $term, $taxonomy, 12 );
				$desc         = self::plain_text( (string) $term->description );
				$parts        = array(
					$term->name,
					$desc,
					$child_titles ? ( __( 'Bu kategorideki içerikler:', 'f2f-ai-chatbot' ) . ' ' . $child_titles ) : '',
				);
				$content = trim( implode( "\n", array_filter( $parts ) ) );
				$link    = get_term_link( $term );
				if ( is_wp_error( $link ) ) {
					$link = home_url( '/' );
				}
				$docs[] = array(
					'id'      => -1000 - (int) $term->term_id,
					'type'    => 'taxonomy',
					'title'   => $term->name,
					'url'     => $link,
					'excerpt' => $desc ? $desc : $term->name,
					'content' => mb_substr( $content, 0, self::SNIPPET_LEN ),
					'tags'    => $term->name . ' ' . $taxonomy,
				);
				$chars += mb_strlen( $content );
			}
		}

		$types = self::post_types();
		$query = new WP_Query(
			array(
				'post_type'              => $types,
				'post_status'            => 'publish',
				'posts_per_page'         => self::MAX_DOCS,
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => true,
			)
		);

		foreach ( $query->posts as $post ) {
			$text    = self::post_plain_content( $post );
			$terms   = self::post_term_labels( $post );
			$excerpt = $post->post_excerpt
				? self::plain_text( $post->post_excerpt )
				: mb_substr( $text, 0, 220 );

			$snippet = mb_substr( trim( $terms . "\n" . $text ), 0, self::SNIPPET_LEN );
			$docs[]  = array(
				'id'      => (int) $post->ID,
				'type'    => $post->post_type,
				'title'   => wp_specialchars_decode( get_the_title( $post ), ENT_QUOTES ),
				'url'     => get_permalink( $post ),
				'excerpt' => $excerpt,
				'content' => $snippet,
				'tags'    => $terms,
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
	 * Panel service labels from settings.
	 *
	 * @param array<string, mixed> $settings Settings.
	 * @return array<int, string>
	 */
	public static function panel_services( $settings ) {
		$services = array();
		if ( ! empty( $settings['featured_label'] ) ) {
			$services[] = trim( (string) $settings['featured_label'] );
		}
		for ( $i = 1; $i <= 4; $i++ ) {
			$key = 'service_' . $i . '_label';
			if ( ! empty( $settings[ $key ] ) ) {
				$services[] = trim( (string) $settings[ $key ] );
			}
		}
		return array_values( array_unique( array_filter( $services ) ) );
	}

	/**
	 * Titles of posts/products under a term (for category knowledge).
	 *
	 * @param WP_Term $term     Term.
	 * @param string  $taxonomy Taxonomy.
	 * @param int     $limit    Max titles.
	 * @return string
	 */
	private static function term_item_titles( $term, $taxonomy, $limit = 12 ) {
		$pt = ( 'product_cat' === $taxonomy || 'product_tag' === $taxonomy ) ? array( 'product' ) : self::post_types();
		$q  = new WP_Query(
			array(
				'post_type'              => $pt,
				'post_status'            => 'publish',
				'posts_per_page'         => $limit,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'tax_query'              => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => $taxonomy,
						'field'    => 'term_id',
						'terms'    => (int) $term->term_id,
					),
				),
			)
		);
		$titles = array();
		foreach ( $q->posts as $pid ) {
			$t = get_the_title( (int) $pid );
			if ( $t ) {
				$titles[] = wp_specialchars_decode( $t, ENT_QUOTES );
			}
		}
		return implode( '; ', $titles );
	}

	/**
	 * Category / tag labels attached to a post.
	 *
	 * @param WP_Post $post Post.
	 * @return string
	 */
	private static function post_term_labels( $post ) {
		$labels = array();
		foreach ( self::taxonomies() as $taxonomy ) {
			if ( ! is_object_in_taxonomy( $post->post_type, $taxonomy ) ) {
				continue;
			}
			$terms = get_the_terms( $post, $taxonomy );
			if ( is_wp_error( $terms ) || ! $terms ) {
				continue;
			}
			foreach ( $terms as $term ) {
				$labels[] = $term->name;
			}
		}
		return implode( ', ', array_unique( $labels ) );
	}

	/**
	 * Best-effort plain text from a post (Woo short desc + content).
	 *
	 * @param WP_Post $post Post.
	 * @return string
	 */
	private static function post_plain_content( $post ) {
		$chunks = array();
		if ( ! empty( $post->post_excerpt ) ) {
			$chunks[] = self::plain_text( $post->post_excerpt );
		}
		if ( 'product' === $post->post_type ) {
			$short = get_post_meta( $post->ID, '_product_short_description', true );
			if ( is_string( $short ) && $short ) {
				$chunks[] = self::plain_text( $short );
			}
		}
		$chunks[] = self::plain_text( $post->post_content );
		$text     = trim( implode( "\n", array_filter( $chunks ) ) );
		// Builder leftovers often leave almost nothing — keep title at least.
		if ( mb_strlen( $text ) < 40 ) {
			$text = trim( get_the_title( $post ) . "\n" . $text );
		}
		return $text;
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
		$html = html_entity_decode( $html, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$html = preg_replace( '/\s+/u', ' ', $html );
		return trim( (string) $html );
	}

	/**
	 * Normalize Turkish / ASCII for matching.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function normalize( $text ) {
		$text = mb_strtolower( self::plain_text( $text ) );
		$map  = array(
			'ı' => 'i',
			'İ' => 'i',
			'ş' => 's',
			'Ş' => 's',
			'ğ' => 'g',
			'Ğ' => 'g',
			'ü' => 'u',
			'Ü' => 'u',
			'ö' => 'o',
			'Ö' => 'o',
			'ç' => 'c',
			'Ç' => 'c',
		);
		$text = strtr( $text, $map );
		return $text;
	}

	/**
	 * Pick relevant docs for a user message (simple keyword score).
	 *
	 * @param string $query User message / service.
	 * @param int    $limit Max docs.
	 * @return array<int, array<string, mixed>>
	 */
	public static function search( $query, $limit = 8 ) {
		$data = self::get();
		$docs = isset( $data['docs'] ) && is_array( $data['docs'] ) ? $data['docs'] : array();
		if ( ! $docs ) {
			return array();
		}

		$q      = self::normalize( $query );
		$tokens = preg_split( '/[\s\p{P}]+/u', $q, -1, PREG_SPLIT_NO_EMPTY );
		$tokens = array_values(
			array_filter(
				$tokens,
				function ( $t ) {
					// Keep short but meaningful tokens (dil, tur…).
					return mb_strlen( $t ) >= 2;
				}
			)
		);
		// Drop ultra-common fillers.
		$stop = array( 've', 'veya', 'ile', 'icin', 'için', 'bir', 'bu', 'şu', 'mi', 'mu', 'mü', 'de', 'da', 'ne', 'nedir', 'hakkinda', 'hakkında', 'var', 'mi', 'var mı', 'lütfen', 'lutfen' );
		$tokens = array_values( array_diff( $tokens, $stop ) );

		$scored = array();
		foreach ( $docs as $doc ) {
			if ( ! is_array( $doc ) ) {
				continue;
			}
			$title = isset( $doc['title'] ) ? (string) $doc['title'] : '';
			$hay   = self::normalize(
				$title . ' ' .
				( isset( $doc['tags'] ) ? $doc['tags'] : '' ) . ' ' .
				( isset( $doc['excerpt'] ) ? $doc['excerpt'] : '' ) . ' ' .
				( isset( $doc['content'] ) ? $doc['content'] : '' )
			);
			$score = 0;
			$title_n = self::normalize( $title );

			if ( $q && false !== mb_strpos( $hay, $q ) ) {
				$score += 12;
			}
			if ( $tokens ) {
				foreach ( $tokens as $tok ) {
					if ( false !== mb_strpos( $title_n, $tok ) ) {
						$score += 5;
					} elseif ( false !== mb_strpos( $hay, $tok ) ) {
						$score += 2;
					}
				}
			}
			$type = isset( $doc['type'] ) ? (string) $doc['type'] : '';
			if ( 'site' === $type ) {
				$score += 1;
			}
			if ( 'taxonomy' === $type || 'service' === $type ) {
				$score += 1.5;
			}
			if ( 'product' === $type || 'page' === $type ) {
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

		$out = array();
		foreach ( $scored as $row ) {
			if ( count( $out ) >= $limit ) {
				break;
			}
			if ( $row['score'] <= 0 && count( $out ) >= 4 ) {
				continue;
			}
			$out[] = $row['doc'];
		}

		if ( count( $out ) < 4 ) {
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

		$docs  = self::search( $query, 10 );
		$lines = array();
		$used  = 0;

		$site_name = isset( $data['site_name'] ) ? $data['site_name'] : get_bloginfo( 'name' );
		$site_desc = isset( $data['site_desc'] ) ? $data['site_desc'] : get_bloginfo( 'description' );
		$site_url  = isset( $data['site_url'] ) ? $data['site_url'] : home_url( '/' );

		$header  = "SİTE KİMLİĞİ\nAd: {$site_name}\nURL: {$site_url}\nAçıklama: {$site_desc}\n";
		$lines[] = $header;
		$used   += mb_strlen( $header );

		$lines[] = 'İLGİLİ SİTE İÇERİKLERİ (kategoriler, ürünler, sayfalar — bunlara dayan):';
		foreach ( $docs as $doc ) {
			if ( isset( $doc['type'] ) && 'site' === $doc['type'] ) {
				continue;
			}
			$title   = isset( $doc['title'] ) ? $doc['title'] : '';
			$url     = isset( $doc['url'] ) ? $doc['url'] : '';
			$type    = isset( $doc['type'] ) ? $doc['type'] : '';
			$tags    = isset( $doc['tags'] ) ? $doc['tags'] : '';
			$content = isset( $doc['content'] ) ? $doc['content'] : '';
			$label   = $type ? "[{$type}] " : '';
			$tagline = $tags ? "Kategoriler: {$tags}\n" : '';
			$block   = "\n### {$label}{$title}\nURL: {$url}\n{$tagline}{$content}\n";
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

		$services      = self::panel_services( $settings );
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
			. "- Yanıtlarını bu sitenin taranmış içeriklerine (ürün, kategori, sayfa), paneldeki hizmet kutularına ve işletme notlarına dayandır.\n"
			. "- Panelde tanımlı hizmetler BU SİTENİN gerçek hizmetleridir. Ziyaretçi bunlardan birini seçtiyse ASLA \"sitede bilgi yok / böyle bir hizmet yok\" deme.\n"
			. "- Seçilen hizmet için bilgi bankasında kategori veya ürün varsa onları özetle; yoksa hizmeti onayla, bilinen ilgili kampları/ürünleri paylaş ve iletişim/WhatsApp'a yönlendir.\n"
			. "- Sitede ve panelde olmayan tamamen alakasız sektörleri uydurma (ör. bu site kamp/tur ise hosting satma).\n"
			. "- Bilmediğin fiyat/tarih detayında tahmin etme; kısa söyle ve canlı görüşmeye yönlendir.\n"
			. "- Dil: net, nazik, kısa Türkçe.\n";

		if ( $notes ) {
			$rules .= "- İşletme notları: {$notes}\n";
		}
		$rules .= "- Paneldeki hizmet etiketleri (gerçek hizmetler): {$services_line}\n";

		if ( ! empty( $lead['service'] ) ) {
			$svc = sanitize_text_field( (string) $lead['service'] );
			$rules .= "- Ziyaretçi şu hizmeti seçti: \"{$svc}\". Bu hizmet site/panel kapsamında kabul edilir; buna göre yardımcı ol.\n";
		}

		$knowledge = self::context_for_prompt( $query, 6500 );
		if ( $knowledge ) {
			$rules .= "\n--- SİTE BİLGİ BANKASI ---\n" . $knowledge . "\n--- BİLGİ BANKASI SONU ---\n";
		} else {
			$rules .= "\n(Uyarı: Site henüz taranmamış. Panel hizmetleri ve site adı/açıklama ile yanıt ver; panel hizmetlerini yok sayma.)\n";
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
		$this->throttled_reindex();
	}

	/**
	 * Reindex when a category / product category changes.
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy.
	 */
	public function maybe_reindex_on_term( $term_id, $tt_id = 0, $taxonomy = '' ) {
		unset( $term_id, $tt_id );
		if ( $taxonomy && ! in_array( $taxonomy, self::taxonomies(), true ) ) {
			return;
		}
		$this->throttled_reindex();
	}

	/**
	 * Debounced reindex if auto_reindex enabled.
	 */
	private function throttled_reindex() {
		$settings = f2f_ai_chatbot_get_settings();
		if ( empty( $settings['auto_reindex'] ) || '1' !== (string) $settings['auto_reindex'] ) {
			return;
		}
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
		check_ajax_referer( 'f2f_ai_admin', 'nonce' );
		$result = self::reindex();
		wp_send_json_success( $result );
	}
}
