<?php
/**
 * Pre-Flight Server Environment & SSRF Security Check.
 *
 * @package WixToWordPressMigrator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Inspects server dependencies, PHP extensions, directory permissions, and validates URLs against SSRF.
 */
class W2W_Environment_Check {

	/**
	 * Minimum required PHP version.
	 */
	public const MIN_PHP_VERSION = '7.4';

	/**
	 * Runs all pre-flight compatibility checks.
	 *
	 * @return array<string, array{name: string, passed: bool, current: string, required: string, critical: bool}>
	 */
	public function check_all(): array {
		$checks = array();

		// 1. PHP Version.
		$php_passed = version_compare( PHP_VERSION, self::MIN_PHP_VERSION, '>=' );
		$checks['php_version'] = array(
			'name'     => __( 'PHP Version', 'wix-to-wp-migrator' ),
			'passed'   => $php_passed,
			'current'  => PHP_VERSION,
			'required' => '>=' . self::MIN_PHP_VERSION,
			'critical' => true,
		);

		// 2. cURL Extension.
		$curl_passed = extension_loaded( 'curl' );
		$checks['curl'] = array(
			'name'     => __( 'cURL Extension', 'wix-to-wp-migrator' ),
			'passed'   => $curl_passed,
			'current'  => $curl_passed ? __( 'Enabled', 'wix-to-wp-migrator' ) : __( 'Disabled', 'wix-to-wp-migrator' ),
			'required' => __( 'Required for network requests', 'wix-to-wp-migrator' ),
			'critical' => true,
		);

		// 3. SimpleXML Extension (Required for RSS parsing).
		$xml_passed = extension_loaded( 'simplexml' );
		$checks['simplexml'] = array(
			'name'     => __( 'SimpleXML Extension', 'wix-to-wp-migrator' ),
			'passed'   => $xml_passed,
			'current'  => $xml_passed ? __( 'Enabled', 'wix-to-wp-migrator' ) : __( 'Disabled', 'wix-to-wp-migrator' ),
			'required' => __( 'Required for XML/RSS feeds', 'wix-to-wp-migrator' ),
			'critical' => true,
		);

		// 4. Multibyte String Extension.
		$mb_passed = extension_loaded( 'mbstring' );
		$checks['mbstring'] = array(
			'name'     => __( 'mbstring Extension', 'wix-to-wp-migrator' ),
			'passed'   => $mb_passed,
			'current'  => $mb_passed ? __( 'Enabled', 'wix-to-wp-migrator' ) : __( 'Disabled', 'wix-to-wp-migrator' ),
			'required' => __( 'Required for UTF-8 processing', 'wix-to-wp-migrator' ),
			'critical' => true,
		);

		// 5. Image Processing Library (GD or Imagick).
		$gd_loaded      = extension_loaded( 'gd' );
		$imagick_loaded = extension_loaded( 'imagick' );
		$image_passed   = $gd_loaded || $imagick_loaded;
		$image_current  = array();
		if ( $gd_loaded ) {
			$image_current[] = 'GD';
		}
		if ( $imagick_loaded ) {
			$image_current[] = 'Imagick';
		}

		$checks['image_library'] = array(
			'name'     => __( 'Image Library (GD / Imagick)', 'wix-to-wp-migrator' ),
			'passed'   => $image_passed,
			'current'  => ! empty( $image_current ) ? implode( ', ', $image_current ) : __( 'None', 'wix-to-wp-migrator' ),
			'required' => __( 'Recommended for image thumbnails', 'wix-to-wp-migrator' ),
			'critical' => false,
		);

		// 6. Uploads Directory Write Permission.
		$upload_dir       = function_exists( 'wp_upload_dir' ) ? wp_upload_dir() : array( 'basedir' => '/tmp' );
		$basedir          = $upload_dir['basedir'] ?? '';
		$uploads_writable = ! empty( $basedir ) && ( is_writable( $basedir ) || ( ! file_exists( $basedir ) && is_writable( dirname( $basedir ) ) ) );

		$checks['uploads_writable'] = array(
			'name'     => __( 'Uploads Directory Writable', 'wix-to-wp-migrator' ),
			'passed'   => $uploads_writable,
			'current'  => $uploads_writable ? __( 'Writable', 'wix-to-wp-migrator' ) : __( 'Not Writable', 'wix-to-wp-migrator' ),
			'required' => __( 'Writable for media downloads', 'wix-to-wp-migrator' ),
			'critical' => true,
		);

		return $checks;
	}

	/**
	 * Determines if the current server environment meets all critical requirements.
	 *
	 * @return bool True if fully compatible, false otherwise.
	 */
	public function is_compatible(): bool {
		$checks = $this->check_all();
		foreach ( $checks as $check ) {
			if ( ! empty( $check['critical'] ) && empty( $check['passed'] ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Validates a user-supplied URL to protect against SSRF (Server-Side Request Forgery).
	 *
	 * Disallows localhost, private IPv4/IPv6 ranges, and non-http(s) protocols.
	 *
	 * @param string $url Source URL string.
	 * @return bool True if the URL is safe, false otherwise.
	 */
	public function validate_source_url( string $url ): bool {
		$clean_url = trim( $url );
		if ( empty( $clean_url ) || ! filter_var( $clean_url, FILTER_VALIDATE_URL ) ) {
			return false;
		}

		$scheme = strtolower( (string) parse_url( $clean_url, PHP_URL_SCHEME ) );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return false;
		}

		$host = parse_url( $clean_url, PHP_URL_HOST );
		if ( empty( $host ) ) {
			return false;
		}

		// Reject loopback hostnames.
		$blocked_hosts = array( 'localhost', '127.0.0.1', '::1', '0.0.0.0' );
		if ( in_array( strtolower( $host ), $blocked_hosts, true ) ) {
			return false;
		}

		// Reject private IP ranges.
		if ( filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) === false && filter_var( $host, FILTER_VALIDATE_IP ) !== false ) {
			return false;
		}

		// Use WordPress core validation if available.
		if ( function_exists( 'wp_http_validate_url' ) ) {
			return (bool) wp_http_validate_url( $clean_url );
		}

		return true;
	}

	/**
	 * Static helper to validate a safe URL against SSRF attacks.
	 *
	 * @param string $url Source URL string.
	 * @return bool True if safe, false otherwise.
	 */
	public static function validate_safe_url( string $url ): bool {
		$instance = new self();
		return $instance->validate_source_url( $url );
	}
}
