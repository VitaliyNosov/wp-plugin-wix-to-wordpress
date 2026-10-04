<?php
/**
 * Contract for Data Source Adapters.
 *
 * @package WixToWordPressMigrator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Interface for all data source adapters (RSS, REST API, Sitemap Scraper).
 */
interface W2W_Source_Adapter_Interface {

	/**
	 * Returns the unique identifier for the source adapter.
	 *
	 * @return string e.g. 'rss', 'api', 'sitemap'
	 */
	public function get_id(): string;

	/**
	 * Returns the human-readable display name of the source adapter.
	 *
	 * @return string
	 */
	public function get_name(): string;

	/**
	 * Validates the input source before attempting data ingestion.
	 *
	 * @param mixed $input URL string, API credentials array, or file path.
	 * @return bool True if valid, false otherwise.
	 */
	public function validate_source( $input ): bool;

	/**
	 * Fetches and transforms posts from the source into an array of W2W_Post_DTO objects.
	 *
	 * @param mixed                $input Source input parameter.
	 * @param array<string, mixed> $args  Additional query arguments (offset, limit, batch).
	 * @return array<W2W_Post_DTO> Array of parsed post DTO objects.
	 * @throws \Exception When source retrieval or parsing fails.
	 */
	public function fetch_posts( $input, array $args = array() ): array;
}
