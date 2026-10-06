<?php
/**
 * Content Processor & Wix HTML Normalizer.
 *
 * @package WixToWordPressMigrator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Handles cleaning of Wix-specific HTML artifacts, URL normalization, and media extraction.
 */
class W2W_Content_Processor {

	/**
	 * Regex pattern matching Wix CDN media URLs.
	 */
	private const WIX_MEDIA_REGEX = '#https?://static\.wixstatic\.com/media/([a-zA-Z0-9_\-~]+(\.[a-zA-Z0-9]+))(/v1/[^"\'\s>]+)?#i';

	/**
	 * Normalizes a Wix dynamic image URL to its full-resolution original form.
	 *
	 * Strips dynamic compression/crop parameters (/v1/fill/...) and query arguments.
	 *
	 * @param string $url Dynamic Wix CDN image URL.
	 * @return string Full-resolution original image URL.
	 */
	public static function normalize_wix_image_url( string $url ): string {
		$url = trim( $url );
		if ( empty( $url ) ) {
			return '';
		}

		// Remove query string if present.
		$clean_url = strtok( $url, '?' );

		// If this is a static.wixstatic.com URL with transformation suffixes (/v1/fill/...)
		if ( preg_match( self::WIX_MEDIA_REGEX, $clean_url, $matches ) ) {
			return 'https://static.wixstatic.com/media/' . $matches[1];
		}

		return $clean_url ?: $url;
	}

	/**
	 * Extracts all image URLs from HTML body content.
	 *
	 * @param string $html HTML content.
	 * @return array<string> Unique list of image URLs.
	 */
	public function extract_image_urls( string $html ): array {
		if ( empty( $html ) ) {
			return array();
		}

		$images = array();
		if ( preg_match_all( '/<img[^>]+(?:src|data-src|data-image-url)=[\'"]([^\'"]+)[\'"]/i', $html, $matches ) ) {
			foreach ( $matches[1] as $src ) {
				// Skip inline data URIs.
				if ( 0 === strpos( $src, 'data:' ) ) {
					continue;
				}
				$normalized = $this->normalize_wix_image_url( $src );
				if ( ! empty( $normalized ) && ! in_array( $normalized, $images, true ) ) {
					$images[] = $normalized;
				}
			}
		}

		return $images;
	}

	/**
	 * Cleans Wix platform artifacts, unwanted inline styling, and empty container tags.
	 *
	 * @param string $html Raw HTML content from Wix.
	 * @return string Cleaned, standards-compliant HTML.
	 */
	public function clean_html( string $html ): string {
		if ( empty( $html ) ) {
			return '';
		}

		// 1. Remove script and style tags.
		$html = preg_replace( '#<script(.*?)>(.*?)</script>#is', '', $html );
		$html = preg_replace( '#<style(.*?)>(.*?)</style>#is', '', $html );

		// 2. Strip Wix proprietary data attributes (data-mesh-id, data-testid, data-hook, etc.).
		$html = preg_replace( '/\s*data-(?:mesh-id|testid|hook|preview|aspect-ratio|is-touch)="[^"]*"/i', '', $html );
		$html = preg_replace( '/\s*data-(?:mesh-id|testid|hook|preview|aspect-ratio|is-touch)=\'[^\']*\'/i', '', $html );

		// 3. Remove hardcoded fixed widths on container divs/sections (e.g. style="width: 980px;").
		$html = preg_replace( '/style="[^"]*(?:min-)?width:\s*\d{3,4}px;?[^"]*"/i', '', $html );

		// 4. Remove empty paragraph spacers (<p>&nbsp;</p>, <p></p>, <p><br></p>).
		$html = preg_replace( '#<p[^>]*>(\s*|&nbsp;|<br\s*/?>)*</p>#i', '', $html );

		// 5. Normalize embed video iframes.
		$html = $this->normalize_embeds( $html );

		// 6. Ensure all content <img> tags have responsive styling and semantic figure wrappers.
		$html = $this->ensure_responsive_images( $html );

		return trim( $html );
	}

	/**
	 * Normalizes Wix video iframe embeds to standard responsive markup or WordPress oEmbed URLs.
	 *
	 * @param string $html HTML content.
	 * @return string Normalized HTML.
	 */
	public function normalize_embeds( string $html ): string {
		if ( empty( $html ) ) {
			return '';
		}

		// Convert YouTube iframe embeds to clean oEmbed URL paragraphs.
		$html = preg_replace_callback(
			'#<iframe[^>]+src=[\'"](?:https?:)?//(?:www\.)?youtube\.com/embed/([a-zA-Z0-9_\-]+)[^\'"]*[\'"][^>]*></iframe>#i',
			function ( $matches ) {
				$video_id = $matches[1];
				return "\n<p>https://www.youtube.com/watch?v={$video_id}</p>\n";
			},
			$html
		);

		// Convert Vimeo iframe embeds to clean oEmbed URL paragraphs.
		$html = preg_replace_callback(
			'#<iframe[^>]+src=[\'"](?:https?:)?//player\.vimeo\.com/video/([0-9]+)[^\'"]*[\'"][^>]*></iframe>#i',
			function ( $matches ) {
				$video_id = $matches[1];
				return "\n<p>https://vimeo.com/{$video_id}</p>\n";
			},
			$html
		);

		return $html;
	}

	/**
	 * Replaces external Wix image URLs in HTML with newly imported local WordPress attachment URLs,
	 * ensuring images have responsive styles, proper WordPress classes, and clean URLs.
	 *
	 * @param string                      $html        HTML content.
	 * @param array<string, string|array> $url_mapping Associative array [ 'original_url' => 'wp_uploads_url' ]
	 *                                                 or [ 'original_url' => [ 'url' => '...', 'attachment_id' => 123 ] ].
	 * @return string HTML with updated local URLs and responsive attributes.
	 */
	public function replace_image_urls( string $html, array $url_mapping ): string {
		if ( empty( $html ) ) {
			return '';
		}

		if ( ! empty( $url_mapping ) ) {
			foreach ( $url_mapping as $original_url => $target ) {
				if ( empty( $original_url ) || empty( $target ) ) {
					continue;
				}

				$local_url     = is_array( $target ) ? ( $target['url'] ?? '' ) : (string) $target;
				$attachment_id = is_array( $target ) ? ( $target['attachment_id'] ?? null ) : null;

				if ( empty( $local_url ) ) {
					continue;
				}

				$normalized_base = $this->normalize_wix_image_url( $original_url );
				$escaped_base    = preg_quote( $normalized_base, '#' );

				// Replace normalized base URL plus any dynamic Wix sizing suffix (/v1/fill/...) or query parameters.
				$html = preg_replace( '#' . $escaped_base . '(/v1/[^"\'\s>]+|\?[^"\'\s>]*)?#i', $local_url, $html );

				// Also replace raw original URL if distinct from normalized base.
				if ( $original_url !== $normalized_base ) {
					$escaped_orig = preg_quote( $original_url, '#' );
					$html         = preg_replace( '#' . $escaped_orig . '(\?[^"\'\s>]*)?#i', $local_url, $html );
				}

				// If attachment_id provided, inject wp-image-{id} class into the matching img tag.
				if ( ! empty( $attachment_id ) ) {
					$escaped_local = preg_quote( $local_url, '#' );
					$html          = preg_replace_callback(
						'#<img([^>]+src=[\'"]' . $escaped_local . '[\'"][^>]*)>#i',
						function( $matches ) use ( $attachment_id ) {
							$img_tag  = $matches[0];
							$attrs    = $matches[1];
							$wp_class = 'wp-image-' . (int) $attachment_id;

							if ( false !== strpos( $attrs, 'class=' ) ) {
								if ( false === strpos( $attrs, $wp_class ) ) {
									$img_tag = preg_replace( '#class=([\'"])(.*?)\1#i', 'class=$1$2 ' . $wp_class . '$1', $img_tag );
								}
							} else {
								$img_tag = str_replace( '<img', '<img class="' . $wp_class . '"', $img_tag );
							}
							return $img_tag;
						},
						$html
					);
				}
			}
		}

		return $this->ensure_responsive_images( $html );
	}

	/**
	 * Ensures all <img> elements have responsive inline styles and standard WordPress classes
	 * to prevent overflow on narrow containers or themes lacking CSS resets.
	 *
	 * Also converts standalone paragraph-wrapped images into WordPress standard <figure> elements.
	 *
	 * @param string $html HTML content.
	 * @return string
	 */
	public function ensure_responsive_images( string $html ): string {
		if ( empty( $html ) ) {
			return '';
		}

		// 1. Ensure style="max-width: 100%; height: auto;" on every <img> tag.
		$html = preg_replace_callback(
			'#<img(?![^>]*style=[\'"][^\'"]*max-width)[^>]*>#i',
			function( $matches ) {
				$tag = $matches[0];
				if ( preg_match( '#style=([\'"])(.*?)\1#i', $tag, $m ) ) {
					$existing  = rtrim( trim( $m[2] ), ';' );
					$new_style = $existing . '; max-width: 100%; height: auto;';
					return str_replace( $m[0], 'style=' . $m[1] . $new_style . $m[1], $tag );
				}
				return str_replace( '<img', '<img style="max-width: 100%; height: auto;"', $tag );
			},
			$html
		);

		// 2. Ensure class="size-full" on <img> tags if missing class.
		$html = preg_replace_callback(
			'#<img(?![^>]*class=)[^>]*>#i',
			function( $matches ) {
				return str_replace( '<img', '<img class="aligncenter size-full"', $matches[0] );
			},
			$html
		);

		// 3. Convert standalone paragraph-wrapped images <p><img ...></p> to <figure class="wp-block-image size-full"><img ...></figure>.
		$html = preg_replace(
			'#<p[^>]*>\s*(<img[^>]+>)\s*</p>#i',
			'<figure class="wp-block-image size-full">$1</figure>',
			$html
		);

		// 4. Wrap any remaining <img> not already inside a <figure> block into <figure class="wp-block-image size-full">.
		$html = preg_replace_callback(
			'#(<figure[^>]*>.*?</figure>)|(<img[^>]+>)#is',
			function( $matches ) {
				if ( ! empty( $matches[1] ) ) {
					return $matches[1];
				}
				return '<figure class="wp-block-image size-full">' . $matches[2] . '</figure>';
			},
			$html
		);

		return $html;
	}
}
