<?php
/**
 * Wix Single Post Scraper Adapter.
 *
 * @package WixToWordPressMigrator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Ingests a single Wix blog post directly from its published web URL.
 */
class W2W_Source_Scraper implements W2W_Source_Adapter_Interface {

	/**
	 * Unique identifier.
	 */
	public const ADAPTER_ID = 'single_post';

	/**
	 * Display name.
	 */
	public const ADAPTER_NAME = 'Wix Single Post URL';

	/**
	 * Content processor instance.
	 *
	 * @var W2W_Content_Processor
	 */
	private W2W_Content_Processor $content_processor;

	/**
	 * Constructor.
	 *
	 * @param W2W_Content_Processor|null $content_processor Content processor.
	 */
	public function __construct( ?W2W_Content_Processor $content_processor = null ) {
		$this->content_processor = $content_processor ?: new W2W_Content_Processor();
	}

	/**
	 * Returns the unique identifier for the source adapter.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return self::ADAPTER_ID;
	}

	/**
	 * Returns the human-readable display name of the source adapter.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return self::ADAPTER_NAME;
	}

	/**
	 * Validates the input source before attempting data ingestion.
	 *
	 * @param mixed $input URL string or HTML content.
	 * @return bool True if valid, false otherwise.
	 */
	public function validate_source( $input ): bool {
		if ( ! is_string( $input ) || empty( trim( $input ) ) ) {
			return false;
		}

		$trimmed = trim( $input );

		// If input is raw HTML containing an article.
		if ( 0 === strpos( $trimmed, '<' ) ) {
			return false !== strpos( $trimmed, '<article' ) || false !== strpos( $trimmed, 'schema.org' );
		}

		// Normalize URL if protocol was omitted.
		if ( ! preg_match( '~^https?://~i', $trimmed ) && preg_match( '~^[a-z0-9\-]+(\.[a-z0-9\-]+)+[/\\?#]?~i', $trimmed ) ) {
			$trimmed = 'https://' . $trimmed;
		}

		// Otherwise, validate as safe remote URL.
		if ( ! filter_var( $trimmed, FILTER_VALIDATE_URL ) ) {
			return false;
		}

		return W2W_Environment_Check::validate_safe_url( $trimmed );
	}

	/**
	 * Fetches and transforms a single Wix post into a W2W_Post_DTO.
	 *
	 * @param mixed                $input Post URL or raw HTML string.
	 * @param array<string, mixed> $args  Optional arguments.
	 * @return array<W2W_Post_DTO> Array with a single parsed W2W_Post_DTO.
	 * @throws \InvalidArgumentException When input URL is invalid.
	 * @throws \RuntimeException         When HTML retrieval or parsing fails.
	 */
	public function fetch_posts( $input, array $args = array() ): array {
		$trimmed = is_string( $input ) ? trim( $input ) : '';

		if ( ! preg_match( '~^https?://~i', $trimmed ) && 0 !== strpos( $trimmed, '<' ) && preg_match( '~^[a-z0-9\-]+(\.[a-z0-9\-]+)+[/\\?#]?~i', $trimmed ) ) {
			$trimmed = 'https://' . $trimmed;
			$input   = $trimmed;
		}

		if ( ! $this->validate_source( $input ) ) {
			throw new \InvalidArgumentException(
				__( 'Invalid Wix post URL or HTML payload. Please ensure the URL includes https:// and points to a published Wix post (e.g. https://yourdomain.com/post/your-post-slug).', 'wix-to-wp-migrator' )
			);
		}

		$html = $this->get_html_content( (string) $input );

		$dto = $this->parse_post_html( $html, is_string( $input ) && 0 !== strpos( trim( $input ), '<' ) ? $input : '' );
		if ( ! $dto ) {
			throw new \RuntimeException( 'Failed to extract post content from the provided Wix page.' );
		}

		return array( $dto );
	}

	/**
	 * Retrieves raw HTML content from either string input or remote HTTP request.
	 *
	 * @param string $input URL or raw HTML string.
	 * @return string
	 * @throws \RuntimeException On network failure.
	 */
	protected function get_html_content( string $input ): string {
		$trimmed = trim( $input );

		// If raw HTML was provided directly.
		if ( 0 === strpos( $trimmed, '<' ) ) {
			return $trimmed;
		}

		$response = wp_remote_get(
			$trimmed,
			array(
				'timeout'     => 30,
				'redirection' => 5,
				'user-agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
				'headers'     => array(
					'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException( 'Failed to fetch Wix post page: ' . $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== (int) $code ) {
			throw new \RuntimeException( sprintf( 'Wix post URL returned HTTP error %d.', (int) $code ) );
		}

		$body = wp_remote_retrieve_body( $response );
		if ( empty( trim( $body ) ) ) {
			throw new \RuntimeException( 'Wix post page returned an empty response body.' );
		}

		return $body;
	}

	/**
	 * Parses Wix post HTML into a strongly-typed W2W_Post_DTO.
	 *
	 * @param string $html     Full HTML document.
	 * @param string $page_url Source page URL.
	 * @return W2W_Post_DTO|null
	 */
	public function parse_post_html( string $html, string $page_url = '' ): ?W2W_Post_DTO {
		// 1. Extract Schema.org JSON-LD metadata.
		$json_ld = $this->extract_json_ld( $html );

		// 2. Title.
		$title = $json_ld['headline'] ?? '';
		if ( empty( $title ) && preg_match( '#<meta property="og:title" content="([^"]+)"#i', $html, $m ) ) {
			$title = html_entity_decode( $m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		}
		if ( empty( $title ) && preg_match( '#<h1[^>]*>(.*?)</h1>#is', $html, $m ) ) {
			$title = html_entity_decode( trim( wp_strip_all_tags( $m[1] ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		}

		// 3. Slug and Original URL.
		$original_url = $page_url ?: ( $json_ld['url'] ?? '' );
		if ( empty( $original_url ) && preg_match( '#<meta property="og:url" content="([^"]+)"#i', $html, $m ) ) {
			$original_url = $m[1];
		}

		$slug = '';
		if ( ! empty( $original_url ) ) {
			$path     = (string) wp_parse_url( $original_url, PHP_URL_PATH );
			$segments = explode( '/', trim( $path, '/' ) );
			$last_seg = end( $segments );
			if ( ! empty( $last_seg ) && ! in_array( $last_seg, array( 'post', 'blog' ), true ) ) {
				$slug = sanitize_title( $last_seg );
			}
		}
		if ( empty( $slug ) && ! empty( $title ) ) {
			$slug = sanitize_title( $title );
		}

		// 4. Original ID.
		$original_id = ! empty( $original_url ) ? md5( $original_url ) : md5( $title );

		// 5. Author Name.
		$author_name = null;
		if ( isset( $json_ld['author']['name'] ) ) {
			$author_name = $json_ld['author']['name'];
		} elseif ( preg_match( '#<meta property="article:author" content="([^"]+)"#i', $html, $m ) ) {
			$author_name = $m[1];
		}

		// 6. Publication Date.
		$date_published = '';
		if ( isset( $json_ld['datePublished'] ) ) {
			$time = strtotime( $json_ld['datePublished'] );
			if ( false !== $time ) {
				$date_published = gmdate( 'Y-m-d H:i:s', $time );
			}
		} elseif ( preg_match( '#<meta property="article:published_time" content="([^"]+)"#i', $html, $m ) ) {
			$time = strtotime( $m[1] );
			if ( false !== $time ) {
				$date_published = gmdate( 'Y-m-d H:i:s', $time );
			}
		}
		if ( empty( $date_published ) ) {
			$date_published = gmdate( 'Y-m-d H:i:s' );
		}

		// 7. Featured Image URL.
		$featured_image_url = null;
		if ( isset( $json_ld['image']['url'] ) ) {
			$featured_image_url = $json_ld['image']['url'];
		} elseif ( is_string( $json_ld['image'] ?? null ) ) {
			$featured_image_url = $json_ld['image'];
		} elseif ( preg_match( '#<meta property="og:image" content="([^"]+)"#i', $html, $m ) ) {
			$featured_image_url = $m[1];
		}
		if ( ! empty( $featured_image_url ) ) {
			$featured_image_url = W2W_Content_Processor::normalize_wix_image_url( $featured_image_url );
		}

		// 8. Extract & Clean Article Body Content.
		$content = $this->extract_article_body( $html );

		// 9. Extract Categories and Tags.
		$categories = $this->extract_categories( $html, $json_ld );
		$tags       = $this->extract_tags( $html, $json_ld );

		// 10. SEO Metadata.
		$seo_meta = array(
			'meta_title'       => $title,
			'meta_description' => $json_ld['description'] ?? '',
		);
		if ( empty( $seo_meta['meta_description'] ) && preg_match( '#<meta property="og:description" content="([^"]+)"#i', $html, $m ) ) {
			$seo_meta['meta_description'] = $m[1];
		}

		$dto = new W2W_Post_DTO(
			$original_id,
			$title,
			$content,
			$slug,
			$original_url,
			$featured_image_url,
			$categories,
			$tags,
			$author_name,
			$seo_meta,
			$date_published,
			'publish'
		);

		return $dto->validate() ? $dto : null;
	}

	/**
	 * Extracts category names from Wix HTML and JSON-LD metadata.
	 *
	 * @param string               $html    Full HTML document.
	 * @param array<string, mixed> $json_ld Pre-parsed JSON-LD data.
	 * @return array<string> Array of sanitized category names.
	 */
	public function extract_categories( string $html, array $json_ld = array() ): array {
		$categories = array();

		// 1. Wix explicit post categories list: aria-label="Post categories"
		if ( preg_match( '~<ul[^>]*aria-label=["\']Post categories["\'][^>]*>(.*?)</ul>~is', $html, $m ) ) {
			if ( preg_match_all( '~<a[^>]*>([^<]+)</a>~i', $m[1], $link_matches ) ) {
				foreach ( $link_matches[1] as $cat ) {
					$clean = trim( html_entity_decode( $cat, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
					if ( ! empty( $clean ) ) {
						$categories[] = $clean;
					}
				}
			}
		}

		// 2. OpenGraph article:section
		if ( preg_match_all( '~<meta[^>]*property=["\']article:section["\'][^>]*content=["\']([^"\']+)["\']~i', $html, $m ) ) {
			foreach ( $m[1] as $cat ) {
				$clean = trim( html_entity_decode( $cat, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
				if ( ! empty( $clean ) ) {
					$categories[] = $clean;
				}
			}
		}

		// 3. Schema.org JSON-LD articleSection
		if ( ! empty( $json_ld['articleSection'] ) ) {
			$sections = is_array( $json_ld['articleSection'] ) ? $json_ld['articleSection'] : array( $json_ld['articleSection'] );
			foreach ( $sections as $s ) {
				$clean = trim( (string) $s );
				if ( ! empty( $clean ) ) {
					$categories[] = $clean;
				}
			}
		}

		// 4. Wix /blog/categories/ links outside header navigation
		if ( empty( $categories ) ) {
			if ( preg_match_all( '~<a[^>]*href=["\'][^"\']*/blog/categories/([^"\'/?#]+)["\'][^>]*>([^<]+)</a>~i', $html, $m, PREG_SET_ORDER ) ) {
				foreach ( $m as $match ) {
					if ( false !== stripos( $match[0], 'header-navigation' ) ) {
						continue;
					}
					$clean = trim( html_entity_decode( $match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
					if ( ! empty( $clean ) && ! in_array( $clean, $categories, true ) ) {
						$categories[] = $clean;
					}
				}
			}
		}

		return array_values( array_unique( $categories ) );
	}

	/**
	 * Extracts tag names from Wix HTML and JSON-LD metadata.
	 *
	 * @param string               $html    Full HTML document.
	 * @param array<string, mixed> $json_ld Pre-parsed JSON-LD data.
	 * @return array<string> Array of sanitized tag names.
	 */
	public function extract_tags( string $html, array $json_ld = array() ): array {
		$tags = array();

		// 1. Wix explicit post tags list: aria-label="Post tags"
		if ( preg_match( '~<ul[^>]*aria-label=["\']Post tags["\'][^>]*>(.*?)</ul>~is', $html, $m ) ) {
			if ( preg_match_all( '~<a[^>]*>([^<]+)</a>~i', $m[1], $link_matches ) ) {
				foreach ( $link_matches[1] as $tag ) {
					$clean = trim( html_entity_decode( $tag, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
					if ( ! empty( $clean ) ) {
						$tags[] = $clean;
					}
				}
			}
		}

		// 2. OpenGraph article:tag
		if ( preg_match_all( '~<meta[^>]*property=["\']article:tag["\'][^>]*content=["\']([^"\']+)["\']~i', $html, $m ) ) {
			foreach ( $m[1] as $tag ) {
				$clean = trim( html_entity_decode( $tag, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
				if ( ! empty( $clean ) ) {
					$tags[] = $clean;
				}
			}
		}

		// 3. Schema.org keywords
		if ( ! empty( $json_ld['keywords'] ) ) {
			$raw_kws = is_array( $json_ld['keywords'] ) ? $json_ld['keywords'] : explode( ',', (string) $json_ld['keywords'] );
			foreach ( $raw_kws as $kw ) {
				$clean = trim( (string) $kw );
				if ( ! empty( $clean ) ) {
					$tags[] = $clean;
				}
			}
		}

		// 4. Wix /blog/tags/ links
		if ( preg_match_all( '~<a[^>]*href=["\'][^"\']*/blog/tags/([^"\'/?#]+)["\'][^>]*>([^<]+)</a>~i', $html, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $match ) {
				$clean = trim( html_entity_decode( $match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
				if ( ! empty( $clean ) && ! in_array( $clean, $tags, true ) ) {
					$tags[] = $clean;
				}
			}
		}

		return array_values( array_unique( $tags ) );
	}

	/**
	 * Extracts JSON-LD Schema.org data for BlogPosting or Article.
	 *
	 * @param string $html Full HTML document.
	 * @return array<string, mixed>
	 */
	private function extract_json_ld( string $html ): array {
		if ( preg_match_all( '#<script type="application/ld\+json">(.*?)</script>#is', $html, $matches ) ) {
			foreach ( $matches[1] as $json_str ) {
				$data = json_decode( trim( $json_str ), true );
				if ( is_array( $data ) && isset( $data['@type'] ) ) {
					$type = $data['@type'];
					if ( in_array( $type, array( 'BlogPosting', 'Article', 'NewsArticle' ), true ) ) {
						return $data;
					}
				}
			}
		}

		return array();
	}

	/**
	 * Extracts rich HTML body content from the Wix article element.
	 *
	 * @param string $html Full HTML document.
	 * @return string Clean HTML content.
	 */
	private function extract_article_body( string $html ): string {
		$body = '';

		// Search for <article>...</article>.
		if ( preg_match( '#<article[^>]*>(.*?)</article>#is', $html, $m ) ) {
			$body = $m[1];
		} else {
			// Fallback: look for post content container.
			if ( preg_match( '#<div[^>]+data-hook="post-description"[^>]*>(.*?)</div>#is', $html, $m ) ) {
				$body = $m[1];
			}
		}

		if ( empty( $body ) ) {
			return '';
		}

		// 1. Remove non-content elements inside article:
		// Remove post hero image if already used as thumbnail.
		$body = preg_replace( '#<section[^>]+data-hook="post-hero-image"[^>]*>.*?</section>#is', '', $body );
		// Remove post title if present inside article to avoid duplicate title in WP content.
		$body = preg_replace( '#<div[^>]+data-hook="post-title"[^>]*>.*?</div>#is', '', $body );
		// Remove author header badge.
		$body = preg_replace( '#<div[^>]+class="[^"]*NtBDdE[^"]*"[^>]*>.*?</div>#is', '', $body );
		// Remove footer, share buttons, stats, comment placeholders.
		$body = preg_replace( '#<footer[^>]*>.*?</footer>#is', '', $body );
		$body = preg_replace( '#<div[^>]+class="[^"]*ShareButtons[^"]*"[^>]*>.*?</div>#is', '', $body );
		$body = preg_replace( '#<div[^>]+data-hook="share-button[^"]*"[^>]*>.*?</div>#is', '', $body );
		$body = preg_replace( '#<section[^>]+data-hook="recent-posts"[^>]*>.*?</section>#is', '', $body );

		// 2. Convert Wix <wow-image> components into standard <img> tags.
		$body = preg_replace_callback(
			'#<wow-image[^>]*data-image-info=([\'"])(.*?)\1[^>]*>.*?</wow-image>#is',
			function( $matches ) {
				$raw  = html_entity_decode( $matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
				$info = json_decode( $raw, true );
				if ( is_array( $info ) && ! empty( $info['imageData']['uri'] ) ) {
					$uri = $info['imageData']['uri'];
					$src = 'https://static.wixstatic.com/media/' . $uri;
					$alt = esc_attr( $info['imageData']['alt'] ?? '' );
					return sprintf( '<p><img src="%s" alt="%s" class="aligncenter size-full" /></p>', esc_url( $src ), $alt );
				}
				return '';
			},
			$body
		);

		// 3. Clean remaining Wix markup via Content Processor.
		return $this->content_processor->clean_html( $body );
	}
}
