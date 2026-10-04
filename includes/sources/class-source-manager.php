<?php
/**
 * Source Adapters Registry and Factory Manager.
 *
 * @package WixToWordPressMigrator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Registry and factory manager for all data sources.
 */
class W2W_Source_Manager {

	/**
	 * Registered source adapters.
	 *
	 * @var array<string, W2W_Source_Adapter_Interface>
	 */
	private array $adapters = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->register_default_adapters();
	}

	/**
	 * Registers internal default source adapters.
	 *
	 * @return void
	 */
	private function register_default_adapters(): void {
		if ( class_exists( 'W2W_Source_RSS' ) ) {
			$this->register_adapter( new W2W_Source_RSS() );
		}

		if ( class_exists( 'W2W_Source_Scraper' ) ) {
			$this->register_adapter( new W2W_Source_Scraper() );
		}

		if ( class_exists( 'W2W_Source_Sitemap' ) ) {
			$this->register_adapter( new W2W_Source_Sitemap() );
		}

		/**
		 * Hook to allow early registration of source adapters.
		 *
		 * @param W2W_Source_Manager $this Current source manager instance.
		 */
		do_action( 'w2w_register_sources', $this );
	}

	/**
	 * Automatically detects the appropriate adapter ID from user URL or content.
	 *
	 * @param string $input URL or raw content string.
	 * @return string Adapter ID ('single_post', 'sitemap', or 'rss').
	 */
	public static function detect_source_type( string $input ): string {
		$trimmed = trim( $input );

		// If input is XML/HTML content.
		if ( 0 === strpos( $trimmed, '<' ) ) {
			if ( false !== strpos( $trimmed, '<rss' ) || false !== strpos( $trimmed, '<feed' ) ) {
				return 'rss';
			}
			if ( false !== strpos( $trimmed, '<urlset' ) || false !== strpos( $trimmed, '<sitemapindex' ) ) {
				return 'sitemap';
			}
			return 'single_post';
		}

		// Input is a URL.
		if ( false !== strpos( $trimmed, 'sitemap' ) ) {
			return 'sitemap';
		}

		if ( false !== strpos( $trimmed, '/post/' ) || false !== strpos( $trimmed, '/posts/' ) ) {
			return 'single_post';
		}

		if ( false !== strpos( $trimmed, 'feed' ) ) {
			return 'rss';
		}

		return 'rss';
	}

	/**
	 * Registers a source adapter instance.
	 *
	 * @param W2W_Source_Adapter_Interface $adapter Source adapter to register.
	 * @return void
	 */
	public function register_adapter( W2W_Source_Adapter_Interface $adapter ): void {
		$this->adapters[ $adapter->get_id() ] = $adapter;
	}

	/**
	 * Unregisters an adapter by ID.
	 *
	 * @param string $source_id Source ID to unregister.
	 * @return void
	 */
	public function unregister_adapter( string $source_id ): void {
		unset( $this->adapters[ $source_id ] );
	}

	/**
	 * Checks if an adapter is registered.
	 *
	 * @param string $source_id Source ID.
	 * @return bool
	 */
	public function has_adapter( string $source_id ): bool {
		$adapters = $this->get_registered_adapters();
		return isset( $adapters[ $source_id ] );
	}

	/**
	 * Retrieves an adapter by ID.
	 *
	 * @param string $source_id Source ID.
	 * @return W2W_Source_Adapter_Interface|null
	 */
	public function get_adapter( string $source_id ): ?W2W_Source_Adapter_Interface {
		$adapters = $this->get_registered_adapters();
		return $adapters[ $source_id ] ?? null;
	}

	/**
	 * Returns all registered adapters with WordPress filter applied.
	 *
	 * @return array<string, W2W_Source_Adapter_Interface>
	 */
	public function get_registered_adapters(): array {
		/**
		 * Filters the registered source adapters.
		 *
		 * @param array<string, W2W_Source_Adapter_Interface> $adapters Registered adapters.
		 */
		$filtered = apply_filters( 'w2w_registered_sources', $this->adapters );
		return is_array( $filtered ) ? $filtered : $this->adapters;
	}
}
