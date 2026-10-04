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
		/**
		 * Hook to allow early registration of source adapters.
		 *
		 * @param W2W_Source_Manager $this Current source manager instance.
		 */
		do_action( 'w2w_register_sources', $this );
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
