<?php
/**
 * Unit Test: Source Manager & Adapter Registry.
 *
 * @package WixToWordPressMigrator
 */

// 1. Verify Autoloader loads W2W_Source_Manager and W2W_Source_Adapter_Interface
w2w_assert_true( class_exists( 'W2W_Source_Manager' ), 'Autoloader should load W2W_Source_Manager' );
w2w_assert_true( interface_exists( 'W2W_Source_Adapter_Interface' ), 'Autoloader should load W2W_Source_Adapter_Interface' );

// 2. Create a mock source adapter implementing the contract
class W2W_Test_Mock_Source_Adapter implements W2W_Source_Adapter_Interface {
	public function get_id(): string {
		return 'mock_source';
	}

	public function get_name(): string {
		return 'Mock Source Adapter';
	}

	public function validate_source( $input ): bool {
		return ! empty( $input );
	}

	public function fetch_posts( $input, array $args = array() ): array {
		return array(
			new W2W_Post_DTO( 'mock-1', 'Mock Post 1', '<p>Body</p>' ),
		);
	}
}

// 3. Test registration with W2W_Source_Manager
$manager = new W2W_Source_Manager();
$mock_adapter = new W2W_Test_Mock_Source_Adapter();

w2w_assert_false( $manager->has_adapter( 'mock_source' ), 'Adapter should not be registered initially' );
w2w_assert_null( $manager->get_adapter( 'mock_source' ), 'get_adapter should return null initially' );

$manager->register_adapter( $mock_adapter );

w2w_assert_true( $manager->has_adapter( 'mock_source' ), 'Adapter should be registered after register_adapter' );
$retrieved = $manager->get_adapter( 'mock_source' );
w2w_assert_not_null( $retrieved, 'Retrieved adapter should not be null' );
w2w_assert_equals( 'mock_source', $retrieved->get_id(), 'Retrieved adapter ID should match' );
w2w_assert_equals( 'Mock Source Adapter', $retrieved->get_name(), 'Retrieved adapter name should match' );

// 4. Test fetch_posts on registered adapter
$posts = $retrieved->fetch_posts( 'some_input' );
w2w_assert_equals( 1, count( $posts ), 'Should fetch 1 post' );
w2w_assert_instance_of( 'W2W_Post_DTO', $posts[0], 'Fetched post must be an instance of W2W_Post_DTO' );

// 5. Test unregistration
$manager->unregister_adapter( 'mock_source' );
w2w_assert_false( $manager->has_adapter( 'mock_source' ), 'Adapter should be unregistered' );
w2w_assert_null( $manager->get_adapter( 'mock_source' ), 'get_adapter should return null after unregister' );

// 6. Test WordPress filter integration
add_filter(
	'w2w_registered_sources',
	function( $sources ) use ( $mock_adapter ) {
		$sources['filtered_source'] = $mock_adapter;
		return $sources;
	}
);

w2w_assert_true( $manager->has_adapter( 'filtered_source' ), 'Manager should recognize adapters added via filter' );
