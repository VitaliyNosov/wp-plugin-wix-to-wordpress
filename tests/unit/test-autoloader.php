<?php
/**
 * Unit Test: Autoloader & Core Plugin Initialization.
 *
 * @package WixToWordPressMigrator
 */

// 1. Verify Autoloader loads W2W_Plugin class
w2w_assert_true( class_exists( 'W2W_Plugin' ), 'W2W_Autoloader should automatically load W2W_Plugin' );

// 2. Verify Singleton Instance
$plugin = W2W_Plugin::get_instance();
w2w_assert_not_null( $plugin, 'Plugin instance should not be null' );
w2w_assert_instance_of( 'W2W_Plugin', $plugin, 'Instance must be of W2W_Plugin' );

// 3. Verify Singleton returns the same instance
$plugin2 = W2W_Plugin::get_instance();
w2w_assert_true( $plugin === $plugin2, 'Subsequent get_instance calls must return exact same reference' );

// 4. Verify constants
w2w_assert_true( defined( 'W2W_VERSION' ), 'W2W_VERSION must be defined' );
w2w_assert_true( defined( 'W2W_PLUGIN_DIR' ), 'W2W_PLUGIN_DIR must be defined' );
w2w_assert_true( is_dir( W2W_PLUGIN_DIR ), 'W2W_PLUGIN_DIR must point to a valid directory' );

// 5. Verify Autoloader ignores classes not prefixed with W2W_
$loaded_other = W2W_Autoloader::autoload( 'Some_Random_Class' );
w2w_assert_false( $loaded_other, 'Autoloader should ignore non-W2W classes' );

// 6. Verify Autoloader returns false for non-existent W2W class without throwing errors
$loaded_fake = W2W_Autoloader::autoload( 'W2W_Non_Existent_Class_XYZ' );
w2w_assert_false( $loaded_fake, 'Autoloader should return false for nonexistent W2W classes' );
