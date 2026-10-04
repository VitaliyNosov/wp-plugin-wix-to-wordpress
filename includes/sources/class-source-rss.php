<?php
/**
 * Wix RSS Feed Source Adapter.
 *
 * @package WixToWordPressMigrator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Source adapter for importing posts from a Wix RSS XML feed.
 */
class W2W_Source_RSS implements W2W_Source_Adapter_Interface {

	/**
	 * Unique identifier.
	 *
	 * @var string
	 */
	private const ADAPTER_ID = 'rss';

	/**
	 * Human-readable adapter name.
	 *
	 * @var string
	 */
	private const ADAPTER_NAME = 'Wix RSS Feed';

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
	 * @param mixed $input URL string or raw XML content.
	 * @return bool True if valid, false otherwise.
	 */
	public function validate_source( $input ): bool {
		if ( ! is_string( $input ) || empty( trim( $input ) ) ) {
			return false;
		}

		$trimmed = trim( $input );

		// If input is raw XML string.
		if ( 0 === strpos( $trimmed, '<' ) ) {
			return false !== strpos( $trimmed, '<rss' ) || false !== strpos( $trimmed, '<feed' );
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
	 * Fetches and transforms posts from the RSS feed into W2W_Post_DTO objects.
	 *
	 * @param mixed                $input Source input parameter (URL or raw XML string).
	 * @param array<string, mixed> $args  Optional query arguments (offset, limit).
	 * @return array<W2W_Post_DTO> Array of parsed post DTO objects.
	 * @throws \InvalidArgumentException When input source is invalid.
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
				__( 'Invalid RSS feed source: URL or XML payload failed validation. Please ensure the URL includes https:// (e.g. https://yourdomain.com/blog-feed.xml).', 'wix-to-wp-migrator' )
			);
		}

		$xml_content = $this->get_xml_content( (string) $input );

		return $this->parse_rss_xml( $xml_content, $args );
	}

	/**
	 * Retrieves the raw XML content from either string input or remote HTTP request.
	 *
	 * @param string $input URL or raw XML string.
	 * @return string Raw XML string.
	 * @throws \RuntimeException On network failure or non-200 HTTP response.
	 */
	protected function get_xml_content( string $input ): string {
		$trimmed = trim( $input );

		// If direct XML string was provided.
		if ( 0 === strpos( $trimmed, '<' ) ) {
			return $trimmed;
		}

		// Perform remote GET request.
		$response = wp_remote_get(
			$trimmed,
			array(
				'timeout'     => 30,
				'redirection' => 5,
				'user-agent'  => 'Mozilla/5.0 (compatible; WixToWordPressMigrator/' . ( defined( 'W2W_VERSION' ) ? W2W_VERSION : '1.0.0' ) . '; +https://wordpress.org)',
				'headers'     => array(
					'Accept' => 'application/rss+xml, application/xml, text/xml;q=0.9, */*;q=0.8',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException( 'Failed to fetch RSS feed: ' . $response->get_error_message() );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== (int) $response_code ) {
			throw new \RuntimeException( sprintf( 'RSS feed request returned HTTP error code %d.', (int) $response_code ) );
		}

		$body = wp_remote_retrieve_body( $response );
		if ( empty( trim( $body ) ) ) {
			throw new \RuntimeException( 'RSS feed returned an empty response body.' );
		}

		return $body;
	}

	/**
	 * Parses RSS XML content into strongly-typed DTO objects.
	 *
	 * @param string               $xml_content Raw XML string.
	 * @param array<string, mixed> $args        Optional pagination arguments.
	 * @return array<W2W_Post_DTO>
	 * @throws \RuntimeException If XML parsing fails or channel has no items.
	 */
	public function parse_rss_xml( string $xml_content, array $args = array() ): array {
		// Suppress libxml errors to handle them cleanly.
		$prev_use_errors = libxml_use_internal_errors( true );

		$xml = simplexml_load_string( $xml_content, 'SimpleXMLElement', LIBXML_NOCDATA );

		if ( false === $xml ) {
			$errors    = libxml_get_errors();
			$error_msg = ! empty( $errors ) ? $errors[0]->message : 'Malformed XML syntax.';
			libxml_clear_errors();
			libxml_use_internal_errors( $prev_use_errors );
			throw new \RuntimeException( 'XML parsing error: ' . trim( $error_msg ) );
		}

		libxml_use_internal_errors( $prev_use_errors );

		if ( ! isset( $xml->channel ) || ! isset( $xml->channel->item ) ) {
			return array();
		}

		$namespaces = $xml->getNamespaces( true );
		$dtos       = array();

		foreach ( $xml->channel->item as $item ) {
			$dto = $this->transform_item_to_dto( $item, $namespaces );
			if ( $dto ) {
				$dtos[] = $dto;
			}
		}

		// Handle optional pagination offset and limit.
		$offset = isset( $args['offset'] ) ? max( 0, (int) $args['offset'] ) : 0;
		$limit  = isset( $args['limit'] ) ? max( 1, (int) $args['limit'] ) : null;

		if ( null !== $limit || $offset > 0 ) {
			$dtos = array_slice( $dtos, $offset, $limit );
		}

		return $dtos;
	}

	/**
	 * Transforms a single XML item into a W2W_Post_DTO.
	 *
	 * @param \SimpleXMLElement    $item       XML item element.
	 * @param array<string, string> $namespaces Array of XML namespace URIs.
	 * @return W2W_Post_DTO|null
	 */
	private function transform_item_to_dto( \SimpleXMLElement $item, array $namespaces ): ?W2W_Post_DTO {
		// 1. Title.
		$raw_title = (string) $item->title;
		$title     = html_entity_decode( trim( $raw_title ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		// 2. Link & Slug.
		$link = (string) $item->link;
		$slug = $this->extract_slug_from_link( $link, $title );

		// 3. Original ID / GUID.
		$original_id = (string) $item->guid;
		if ( empty( $original_id ) ) {
			$original_id = ! empty( $link ) ? md5( $link ) : md5( $title );
		}

		// 4. Content.
		$content = '';
		if ( isset( $namespaces['content'] ) ) {
			$content_ns = $item->children( $namespaces['content'] );
			if ( isset( $content_ns->encoded ) ) {
				$content = (string) $content_ns->encoded;
			}
		}

		if ( empty( $content ) ) {
			$content = (string) $item->description;
		}

		// 5. Featured Image.
		$featured_image_url = $this->extract_featured_image( $item, $namespaces, $content );

		// 6. Categories.
		$categories = array();
		if ( isset( $item->category ) ) {
			foreach ( $item->category as $category ) {
				$cat_name = html_entity_decode( trim( (string) $category ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
				if ( ! empty( $cat_name ) && ! in_array( $cat_name, $categories, true ) ) {
					$categories[] = $cat_name;
				}
			}
		}

		// 7. Author.
		$author_name = null;
		if ( isset( $namespaces['dc'] ) ) {
			$dc_ns = $item->children( $namespaces['dc'] );
			if ( isset( $dc_ns->creator ) ) {
				$author_name = html_entity_decode( trim( (string) $dc_ns->creator ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			}
		}
		if ( empty( $author_name ) && isset( $item->author ) ) {
			$author_name = html_entity_decode( trim( (string) $item->author ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		}

		// 8. Publication Date.
		$date_published = '';
		if ( isset( $item->pubDate ) ) {
			$timestamp = strtotime( (string) $item->pubDate );
			if ( false !== $timestamp ) {
				$date_published = gmdate( 'Y-m-d H:i:s', $timestamp );
			}
		}

		// 9. SEO Metadata.
		$seo_meta = array();
		$desc     = (string) $item->description;
		if ( ! empty( $desc ) ) {
			// Extract clean plain text snippet for meta description.
			$clean_desc = wp_strip_all_tags( $desc );
			if ( mb_strlen( $clean_desc ) > 160 ) {
				$clean_desc = mb_substr( $clean_desc, 0, 157 ) . '...';
			}
			$seo_meta['meta_description'] = $clean_desc;
		}
		if ( ! empty( $title ) ) {
			$seo_meta['meta_title'] = $title;
		}

		$dto = new W2W_Post_DTO(
			$original_id,
			$title,
			$content,
			$slug,
			$link,
			$featured_image_url,
			$categories,
			array(), // Tags.
			$author_name,
			$seo_meta,
			$date_published,
			'publish'
		);

		return $dto->validate() ? $dto : null;
	}

	/**
	 * Extracts a clean slug from the post link or falls back to sanitizing the title.
	 *
	 * @param string $link  Item link.
	 * @param string $title Item title.
	 * @return string
	 */
	private function extract_slug_from_link( string $link, string $title ): string {
		if ( ! empty( $link ) ) {
			$path = wp_parse_url( $link, PHP_URL_PATH );
			if ( ! empty( $path ) ) {
				// Wix blog URLs typically look like: /post/my-article-slug or /blog/post/my-article-slug
				$trimmed_path = trim( $path, '/' );
				$segments     = explode( '/', $trimmed_path );
				$last_segment = end( $segments );

				if ( ! empty( $last_segment ) && ! in_array( $last_segment, array( 'feed', 'rss', 'blog-feed.xml' ), true ) ) {
					return sanitize_title( $last_segment );
				}
			}
		}

		return sanitize_title( $title );
	}

	/**
	 * Extracts the best candidate featured image URL from the item.
	 *
	 * @param \SimpleXMLElement    $item       XML item.
	 * @param array<string, string> $namespaces Namespaces array.
	 * @param string               $content    Full body content.
	 * @return string|null
	 */
	private function extract_featured_image( \SimpleXMLElement $item, array $namespaces, string $content ): ?string {
		// 1. Check <enclosure url="..." type="image/...">
		if ( isset( $item->enclosure ) ) {
			$enc_url  = (string) $item->enclosure['url'];
			$enc_type = (string) $item->enclosure['type'];

			if ( ! empty( $enc_url ) && ( 0 === strpos( $enc_type, 'image/' ) || preg_match( '/\.(jpg|jpeg|png|gif|webp|svg)/i', $enc_url ) ) ) {
				return W2W_Content_Processor::normalize_wix_image_url( $enc_url );
			}
		}

		// 2. Check <media:content> or <media:thumbnail>.
		if ( isset( $namespaces['media'] ) ) {
			$media_ns = $item->children( $namespaces['media'] );

			if ( isset( $media_ns->content ) ) {
				$media_url = (string) $media_ns->content->attributes()['url'];
				if ( ! empty( $media_url ) ) {
					return W2W_Content_Processor::normalize_wix_image_url( $media_url );
				}
			}

			if ( isset( $media_ns->thumbnail ) ) {
				$thumb_url = (string) $media_ns->thumbnail->attributes()['url'];
				if ( ! empty( $thumb_url ) ) {
					return W2W_Content_Processor::normalize_wix_image_url( $thumb_url );
				}
			}
		}

		// 3. Fallback: Search for the first <img> in post content.
		if ( ! empty( $content ) && preg_match( '/<img[^>]+src=[\'"]([^\'"]+)[\'"]/i', $content, $matches ) ) {
			return W2W_Content_Processor::normalize_wix_image_url( $matches[1] );
		}

		return null;
	}
}
