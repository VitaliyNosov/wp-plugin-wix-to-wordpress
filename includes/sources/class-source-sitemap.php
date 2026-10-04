<?php
/**
 * Wix Sitemap Ingestion Adapter.
 *
 * @package WixToWordPressMigrator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Parses Wix sitemap.xml and blog-posts-sitemap.xml to ingest all historical posts,
 * overcoming the standard 20-post RSS feed limit.
 */
class W2W_Source_Sitemap implements W2W_Source_Adapter_Interface {

	/**
	 * Unique identifier.
	 */
	public const ADAPTER_ID = 'sitemap';

	/**
	 * Display name.
	 */
	public const ADAPTER_NAME = 'Wix Sitemap XML';

	/**
	 * Scraper instance for lazy full-content extraction.
	 *
	 * @var W2W_Source_Scraper
	 */
	private W2W_Source_Scraper $scraper;

	/**
	 * Constructor.
	 *
	 * @param W2W_Source_Scraper|null $scraper Scraper instance.
	 */
	public function __construct( ?W2W_Source_Scraper $scraper = null ) {
		$this->scraper = $scraper ?: new W2W_Source_Scraper();
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
	 * @param mixed $input URL string or XML content.
	 * @return bool True if valid, false otherwise.
	 */
	public function validate_source( $input ): bool {
		if ( ! is_string( $input ) || empty( trim( $input ) ) ) {
			return false;
		}

		$trimmed = trim( $input );

		// If input is raw XML string.
		if ( 0 === strpos( $trimmed, '<' ) ) {
			return false !== strpos( $trimmed, '<urlset' ) || false !== strpos( $trimmed, '<sitemapindex' );
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
	 * Fetches and transforms all posts listed in the sitemap into W2W_Post_DTO objects.
	 *
	 * @param mixed                $input Source input parameter (URL or raw XML string).
	 * @param array<string, mixed> $args  Optional pagination arguments (offset, limit).
	 * @return array<W2W_Post_DTO> Array of parsed post DTO objects.
	 * @throws \InvalidArgumentException When input is invalid.
	 * @throws \RuntimeException         When XML retrieval or parsing fails.
	 */
	public function fetch_posts( $input, array $args = array() ): array {
		$trimmed = is_string( $input ) ? trim( $input ) : '';

		if ( ! preg_match( '~^https?://~i', $trimmed ) && 0 !== strpos( $trimmed, '<' ) && preg_match( '~^[a-z0-9\-]+(\.[a-z0-9\-]+)+[/\\?#]?~i', $trimmed ) ) {
			$trimmed = 'https://' . $trimmed;
			$input   = $trimmed;
		}

		if ( ! $this->validate_source( $input ) ) {
			throw new \InvalidArgumentException(
				__( 'Invalid sitemap URL or XML payload. Please ensure the URL includes https:// and points to a valid XML sitemap (e.g. https://yourdomain.com/blog-posts-sitemap.xml).', 'wix-to-wp-migrator' )
			);
		}

		$xml_content = $this->get_xml_content( (string) $input );

		return $this->parse_sitemap_xml( $xml_content, $args );
	}

	/**
	 * Retrieves raw XML content from either string input or remote HTTP request.
	 *
	 * @param string $input URL or raw XML string.
	 * @return string
	 * @throws \RuntimeException On network failure.
	 */
	protected function get_xml_content( string $input ): string {
		$trimmed = trim( $input );

		// If raw XML was provided directly.
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
					'Accept' => 'application/xml, text/xml, */*;q=0.8',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException( 'Failed to fetch sitemap: ' . $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== (int) $code ) {
			throw new \RuntimeException( sprintf( 'Sitemap request returned HTTP error %d.', (int) $code ) );
		}

		$body = wp_remote_retrieve_body( $response );
		if ( empty( trim( $body ) ) ) {
			throw new \RuntimeException( 'Sitemap returned an empty response body.' );
		}

		return $body;
	}

	/**
	 * Parses sitemap XML content into W2W_Post_DTO objects.
	 *
	 * Auto-resolves <sitemapindex> parent files to their child blog-posts-sitemap.xml!
	 *
	 * @param string               $xml_content Raw XML string.
	 * @param array<string, mixed> $args        Optional arguments.
	 * @return array<W2W_Post_DTO>
	 * @throws \RuntimeException When XML parsing fails.
	 */
	public function parse_sitemap_xml( string $xml_content, array $args = array() ): array {
		$prev_use_errors = libxml_use_internal_errors( true );
		$xml             = simplexml_load_string( $xml_content, 'SimpleXMLElement', LIBXML_NOCDATA );

		if ( false === $xml ) {
			$errors    = libxml_get_errors();
			$error_msg = ! empty( $errors ) ? $errors[0]->message : 'Malformed XML syntax.';
			libxml_clear_errors();
			libxml_use_internal_errors( $prev_use_errors );
			throw new \RuntimeException( 'Sitemap XML parsing error: ' . trim( $error_msg ) );
		}

		libxml_use_internal_errors( $prev_use_errors );

		// 1. If this is a <sitemapindex>, find and fetch the child blog-posts-sitemap.
		if ( 'sitemapindex' === $xml->getName() && isset( $xml->sitemap ) ) {
			$blog_sitemap_url = '';
			foreach ( $xml->sitemap as $sm ) {
				$loc = (string) $sm->loc;
				if ( false !== strpos( $loc, 'blog-posts-sitemap' ) || false !== strpos( $loc, 'post-sitemap' ) || false !== strpos( $loc, 'posts-sitemap' ) ) {
					$blog_sitemap_url = $loc;
					break;
				}
			}

			// Fallback to first child sitemap if specific blog sitemap not named.
			if ( empty( $blog_sitemap_url ) && isset( $xml->sitemap[0]->loc ) ) {
				$blog_sitemap_url = (string) $xml->sitemap[0]->loc;
			}

			if ( ! empty( $blog_sitemap_url ) ) {
				return $this->fetch_posts( $blog_sitemap_url, $args );
			}
		}

		// 2. Parse <urlset> entries.
		if ( ! isset( $xml->url ) ) {
			return array();
		}

		$dtos = array();
		foreach ( $xml->url as $url_entry ) {
			$loc = trim( (string) $url_entry->loc );
			if ( empty( $loc ) ) {
				continue;
			}

			// Only process blog post URLs (typically contains /post/).
			if ( false === strpos( $loc, '/post/' ) && false === strpos( $loc, '/blog/' ) && false === strpos( $loc, '/posts/' ) ) {
				continue;
			}

			$slug  = $this->extract_slug_from_url( $loc );
			$title = $this->humanize_slug( $slug );

			// Extract date modified / published.
			$date_published = '';
			if ( isset( $url_entry->lastmod ) ) {
				$raw_date = trim( (string) $url_entry->lastmod );
				if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw_date ) ) {
					$date_published = $raw_date . ' 00:00:00';
				} else {
					$time = strtotime( $raw_date );
					if ( false !== $time ) {
						$date_published = gmdate( 'Y-m-d H:i:s', $time );
					}
				}
			}
			if ( empty( $date_published ) ) {
				$date_published = gmdate( 'Y-m-d H:i:s' );
			}

			// Extract image from <image:image><image:loc> if available.
			$featured_image_url = null;
			$namespaces         = $url_entry->getNamespaces( true );
			if ( isset( $namespaces['image'] ) ) {
				$image_ns = $url_entry->children( $namespaces['image'] );
				if ( isset( $image_ns->image ) && isset( $image_ns->image->loc ) ) {
					$raw_img = (string) $image_ns->image->loc;
					if ( ! empty( $raw_img ) ) {
						$featured_image_url = W2W_Content_Processor::normalize_wix_image_url( $raw_img );
					}
				}
			}

			$dto = new W2W_Post_DTO(
				md5( $loc ),
				$title,
				'', // Content is loaded on demand during chunk migration.
				$slug,
				$loc,
				$featured_image_url,
				array(),
				array(),
				null,
				array( 'meta_title' => $title ),
				$date_published,
				'publish'
			);

			if ( $dto->validate() ) {
				$dtos[] = $dto;
			}
		}

		// Pagination arguments.
		$offset = isset( $args['offset'] ) ? max( 0, (int) $args['offset'] ) : 0;
		$limit  = isset( $args['limit'] ) ? max( 1, (int) $args['limit'] ) : null;

		if ( null !== $limit || $offset > 0 ) {
			$dtos = array_slice( $dtos, $offset, $limit );
		}

		return $dtos;
	}

	/**
	 * Extracts clean slug from a URL path.
	 *
	 * @param string $url Full post URL.
	 * @return string
	 */
	private function extract_slug_from_url( string $url ): string {
		$path     = (string) wp_parse_url( $url, PHP_URL_PATH );
		$segments = explode( '/', trim( $path, '/' ) );
		$last     = end( $segments );
		return sanitize_title( $last ?: 'post' );
	}

	/**
	 * Converts a kebab-case slug into a capitalized human title.
	 *
	 * @param string $slug Slug string.
	 * @return string
	 */
	private function humanize_slug( string $slug ): string {
		$words = explode( '-', $slug );
		$words = array_map( 'ucfirst', $words );
		return implode( ' ', $words );
	}
}
