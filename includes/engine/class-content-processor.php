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
	public function normalize_wix_image_url( string $url ): string {
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
		if ( preg_match_all( '/<img[^>]+src=[\'"]([^\'"]+)[\'"]/i', $html, $matches ) ) {
			foreach ( $matches[1] as $src ) {
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
		$html = preg_replace( '/style="[^"]*width:\s*\d{3,4}px;?[^"]*"/i', '', $html );

		// 4. Remove empty paragraph spacers (<p>&nbsp;</p>, <p></p>, <p><br></p>).
		$html = preg_replace( '#<p[^>]*>(\s*|&nbsp;|<br\s*/?>)*</p>#i', '', $html );

		// 5. Normalize embed video iframes.
		$html = $this->normalize_embeds( $html );

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
	 * Replaces external Wix image URLs in HTML with newly imported local WordPress attachment URLs.
	 *
	 * @param string                $html        HTML content.
	 * @param array<string, string> $url_mapping Associative array [ 'original_url' => 'wp_uploads_url' ].
	 * @return string HTML with updated local URLs.
	 */
	public function replace_image_urls( string $html, array $url_mapping ): string {
		if ( empty( $html ) || empty( $url_mapping ) ) {
			return $html;
		}

		foreach ( $url_mapping as $original_url => $local_url ) {
			if ( empty( $original_url ) || empty( $local_url ) ) {
				continue;
			}

			// Replace both raw URL and potential escaped variations.
			$html = str_replace( $original_url, $local_url, $html );

			// Also replace normalized base if original had dynamic parameters.
			$normalized_base = $this->normalize_wix_image_url( $original_url );
			if ( $normalized_base !== $original_url ) {
				$html = str_replace( $normalized_base, $local_url, $html );
			}
		}

		return $html;
	}
}
