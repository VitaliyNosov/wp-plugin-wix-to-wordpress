<?php
/**
 * Test Suite Bootstrap & WordPress Environment Mock Harness.
 *
 * @package WixToWordPressMigrator
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . DIRECTORY_SEPARATOR );
}

// Set up plugin constants for the testing environment.
if ( ! defined( 'W2W_VERSION' ) ) {
	define( 'W2W_VERSION', '1.0.0-test' );
}

if ( ! defined( 'W2W_PLUGIN_FILE' ) ) {
	define( 'W2W_PLUGIN_FILE', dirname( __DIR__ ) . DIRECTORY_SEPARATOR . 'wix-to-wp-migrator.php' );
}

if ( ! defined( 'W2W_PLUGIN_DIR' ) ) {
	define( 'W2W_PLUGIN_DIR', dirname( __DIR__ ) . DIRECTORY_SEPARATOR );
}

if ( ! defined( 'W2W_PLUGIN_URL' ) ) {
	define( 'W2W_PLUGIN_URL', 'http://example.org/wp-content/plugins/wix-to-wp-plugin/' );
}

if ( ! defined( 'W2W_PLUGIN_BASENAME' ) ) {
	define( 'W2W_PLUGIN_BASENAME', 'wix-to-wp-plugin/wix-to-wp-migrator.php' );
}

if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 60 * MINUTE_IN_SECONDS );
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 24 * HOUR_IN_SECONDS );
}

// Global in-memory storage for WP mocks.
global $w2w_test_db;
$w2w_test_db = array(
	'posts'        => array(),
	'postmeta'     => array(),
	'terms'        => array(),
	'term_rel'     => array(),
	'actions'      => array(),
	'filters'      => array(),
	'options'      => array(),
	'next_post_id' => 100,
	'next_term_id' => 200,
);

/**
 * Resets the in-memory mock database.
 */
function w2w_test_reset_db(): void {
	global $w2w_test_db;
	$w2w_test_db = array(
		'posts'        => array(),
		'postmeta'     => array(),
		'terms'        => array(),
		'term_rel'     => array(),
		'actions'      => array(),
		'filters'      => array(),
		'options'      => array(),
		'next_post_id' => 100,
		'next_term_id' => 200,
	);
}

// -------------------------------------------------------------
// WordPress Core Function Mocks
// -------------------------------------------------------------

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
		global $w2w_test_db;
		$w2w_test_db['actions'][ $tag ][] = array(
			'callback' => $callback,
			'priority' => $priority,
		);
		return true;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	function do_action( $tag, ...$args ) {
		global $w2w_test_db;
		if ( ! empty( $w2w_test_db['actions'][ $tag ] ) ) {
			foreach ( $w2w_test_db['actions'][ $tag ] as $hook ) {
				call_user_func_array( $hook['callback'], $args );
			}
		}
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
		global $w2w_test_db;
		$w2w_test_db['filters'][ $tag ][] = array(
			'callback' => $callback,
			'priority' => $priority,
		);
		return true;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value, ...$args ) {
		global $w2w_test_db;
		if ( ! empty( $w2w_test_db['filters'][ $tag ] ) ) {
			foreach ( $w2w_test_db['filters'][ $tag ] as $hook ) {
				$call_args = array_merge( array( $value ), $args );
				$value     = call_user_func_array( $hook['callback'], $call_args );
			}
		}
		return $value;
	}
}

if ( ! function_exists( 'load_plugin_textdomain' ) ) {
	function load_plugin_textdomain( $domain, $deprecated = false, $plugin_rel_path = false ) {
		return true;
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( '_e' ) ) {
	function _e( $text, $domain = 'default' ) {
		echo $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return filter_var( $url, FILTER_SANITIZE_URL );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) {
		return filter_var( $url, FILTER_SANITIZE_URL );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return trim( strip_tags( (string) $str ) );
	}
}

if ( ! function_exists( 'sanitize_file_name' ) ) {
	function sanitize_file_name( $filename ) {
		return preg_replace( '/[^a-zA-Z0-9_\.\-]/', '', (string) $filename );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $key ) );
	}
}

if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( $title ) {
		return strtolower( trim( preg_replace( '/[^a-zA-Z0-9_\-]+/', '-', (string) $title ), '-' ) );
	}
}

if ( ! function_exists( 'plugin_basename' ) ) {
	function plugin_basename( $file ) {
		return basename( dirname( $file ) ) . '/' . basename( $file );
	}
}

if ( ! function_exists( 'plugin_dir_path' ) ) {
	function plugin_dir_path( $file ) {
		return trailingslashit( dirname( $file ) );
	}
}

if ( ! function_exists( 'plugin_dir_url' ) ) {
	function plugin_dir_url( $file ) {
		return 'http://example.org/wp-content/plugins/' . basename( dirname( $file ) ) . '/';
	}
}

if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $string ) {
		return rtrim( $string, '/\\' ) . '/';
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private array $errors = array();
		public function __construct( $code = '', $message = '', $data = '' ) {
			if ( ! empty( $code ) ) {
				$this->errors[ $code ][] = $message;
			}
		}
		public function get_error_message() {
			foreach ( $this->errors as $messages ) {
				return $messages[0];
			}
			return '';
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}

// Post and meta storage mocks
if ( ! function_exists( 'wp_insert_post' ) ) {
	function wp_insert_post( $postarr, $wp_error = false ) {
		global $w2w_test_db;
		$id = $postarr['ID'] ?? ++$w2w_test_db['next_post_id'];
		$w2w_test_db['posts'][ $id ] = array_merge(
			array(
				'ID'          => $id,
				'post_title'  => '',
				'post_content'=> '',
				'post_name'   => '',
				'post_status' => 'publish',
				'post_type'   => 'post',
				'post_author' => 1,
			),
			$postarr
		);
		return $id;
	}
}

if ( ! function_exists( 'get_post' ) ) {
	function get_post( $post_id ) {
		global $w2w_test_db;
		$id = is_object( $post_id ) ? $post_id->ID : (int) $post_id;
		if ( isset( $w2w_test_db['posts'][ $id ] ) ) {
			return (object) $w2w_test_db['posts'][ $id ];
		}
		return null;
	}
}

if ( ! function_exists( 'wp_delete_post' ) ) {
	function wp_delete_post( $post_id, $force_delete = false ) {
		global $w2w_test_db;
		$id = (int) $post_id;
		if ( isset( $w2w_test_db['posts'][ $id ] ) ) {
			$post = (object) $w2w_test_db['posts'][ $id ];
			unset( $w2w_test_db['posts'][ $id ] );
			unset( $w2w_test_db['postmeta'][ $id ] );
			return $post;
		}
		return false;
	}
}

if ( ! function_exists( 'wp_delete_attachment' ) ) {
	function wp_delete_attachment( $post_id, $force_delete = false ) {
		return wp_delete_post( $post_id, $force_delete );
	}
}

if ( ! function_exists( 'update_post_meta' ) ) {
	function update_post_meta( $post_id, $meta_key, $meta_value, $prev_value = '' ) {
		global $w2w_test_db;
		$w2w_test_db['postmeta'][ (int) $post_id ][ $meta_key ] = $meta_value;
		return true;
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $post_id, $key = '', $single = false ) {
		global $w2w_test_db;
		$id = (int) $post_id;
		if ( empty( $key ) ) {
			return $w2w_test_db['postmeta'][ $id ] ?? array();
		}
		if ( isset( $w2w_test_db['postmeta'][ $id ][ $key ] ) ) {
			return $single ? $w2w_test_db['postmeta'][ $id ][ $key ] : array( $w2w_test_db['postmeta'][ $id ][ $key ] );
		}
		return $single ? '' : array();
	}
}

if ( ! function_exists( 'delete_post_meta' ) ) {
	function delete_post_meta( $post_id, $meta_key ) {
		global $w2w_test_db;
		unset( $w2w_test_db['postmeta'][ (int) $post_id ][ $meta_key ] );
		return true;
	}
}

if ( ! function_exists( 'term_exists' ) ) {
	function term_exists( $term, $taxonomy = '', $parent = null ) {
		global $w2w_test_db;
		$name = is_string( $term ) ? trim( $term ) : '';
		foreach ( $w2w_test_db['terms'] as $t_id => $data ) {
			if ( $taxonomy && $data['taxonomy'] !== $taxonomy ) {
				continue;
			}
			if ( strtolower( $data['name'] ) === strtolower( $name ) ) {
				return $t_id;
			}
		}
		return 0;
	}
}

if ( ! function_exists( 'wp_insert_term' ) ) {
	function wp_insert_term( $term, $taxonomy, $args = array() ) {
		global $w2w_test_db;
		$existing = term_exists( $term, $taxonomy );
		if ( $existing ) {
			return array(
				'term_id'          => $existing,
				'term_taxonomy_id' => $existing,
			);
		}
		$term_id                          = ++$w2w_test_db['next_term_id'];
		$w2w_test_db['terms'][ $term_id ] = array(
			'term_id'  => $term_id,
			'name'     => $term,
			'slug'     => sanitize_key( $term ),
			'taxonomy' => $taxonomy,
			'parent'   => $args['parent'] ?? 0,
		);
		return array(
			'term_id'          => $term_id,
			'term_taxonomy_id' => $term_id,
		);
	}
}

if ( ! function_exists( 'wp_set_post_terms' ) ) {
	function wp_set_post_terms( $post_id, $terms, $taxonomy, $append = false ) {
		global $w2w_test_db;
		$term_ids = is_array( $terms ) ? $terms : array( $terms );
		if ( ! $append || ! isset( $w2w_test_db['term_rel'][ (int) $post_id ][ $taxonomy ] ) ) {
			$w2w_test_db['term_rel'][ (int) $post_id ][ $taxonomy ] = array();
		}
		foreach ( $term_ids as $tid ) {
			$id = (int) $tid;
			if ( ! in_array( $id, $w2w_test_db['term_rel'][ (int) $post_id ][ $taxonomy ], true ) ) {
				$w2w_test_db['term_rel'][ (int) $post_id ][ $taxonomy ][] = $id;
			}
		}
		return $w2w_test_db['term_rel'][ (int) $post_id ][ $taxonomy ];
	}
}

if ( ! function_exists( 'wp_get_post_terms' ) ) {
	function wp_get_post_terms( $post_id, $taxonomy, $args = array() ) {
		global $w2w_test_db;
		$ids     = $w2w_test_db['term_rel'][ (int) $post_id ][ $taxonomy ] ?? array();
		$results = array();
		foreach ( $ids as $tid ) {
			if ( isset( $w2w_test_db['terms'][ $tid ] ) ) {
				$results[] = (object) $w2w_test_db['terms'][ $tid ];
			}
		}
		return $results;
	}
}

if ( ! function_exists( 'wp_insert_attachment' ) ) {
	function wp_insert_attachment( $args, $filename = false, $parent_post_id = 0 ) {
		$args['post_type']   = 'attachment';
		$args['post_parent'] = $parent_post_id;
		$id                  = wp_insert_post( $args );
		if ( $filename ) {
			update_post_meta( $id, '_wp_attached_file', $filename );
		}
		return $id;
	}
}

if ( ! function_exists( 'wp_upload_bits' ) ) {
	function wp_upload_bits( $name, $deprecated, $bits ) {
		return array(
			'file'  => '/tmp/uploads/' . $name,
			'url'   => 'http://example.org/wp-content/uploads/' . $name,
			'error' => false,
		);
	}
}

if ( ! function_exists( 'wp_check_filetype_and_ext' ) ) {
	function wp_check_filetype_and_ext( $file, $filename, $mimes = null ) {
		$ext     = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		$allowed = array(
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png'  => 'image/png',
			'webp' => 'image/webp',
			'gif'  => 'image/gif',
			'svg'  => 'image/svg+xml',
		);
		if ( isset( $allowed[ $ext ] ) ) {
			return array(
				'ext'             => $ext,
				'type'            => $allowed[ $ext ],
				'proper_filename' => false,
			);
		}
		return array(
			'ext'             => false,
			'type'            => false,
			'proper_filename' => false,
		);
	}
}

if ( ! function_exists( 'wp_http_validate_url' ) ) {
	function wp_http_validate_url( $url ) {
		if ( filter_var( $url, FILTER_VALIDATE_URL ) ) {
			$host = parse_url( $url, PHP_URL_HOST );
			if ( $host && ! in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true ) ) {
				return $url;
			}
		}
		return false;
	}
}

global $w2w_test_mock_responses;
$w2w_test_mock_responses = array();

if ( ! function_exists( 'wp_remote_get' ) ) {
	function wp_remote_get( $url, $args = array() ) {
		global $w2w_test_mock_responses;
		if ( isset( $w2w_test_mock_responses[ $url ] ) ) {
			return $w2w_test_mock_responses[ $url ];
		}
		return array(
			'response' => array( 'code' => 200 ),
			'body'     => 'MOCK_IMAGE_BINARY_DATA_XYZ',
		);
	}
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	function wp_remote_retrieve_response_code( $response ) {
		return is_array( $response ) ? ( $response['response']['code'] ?? 200 ) : 200;
	}
}

if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	function wp_remote_retrieve_body( $response ) {
		return is_array( $response ) ? ( $response['body'] ?? '' ) : '';
	}
}

if ( ! function_exists( 'get_posts' ) ) {
	function get_posts( $args = array() ) {
		global $w2w_test_db;
		$results    = array();
		$post_type  = $args['post_type'] ?? 'post';
		$meta_key   = $args['meta_key'] ?? '';
		$meta_value = $args['meta_value'] ?? null;

		foreach ( $w2w_test_db['posts'] as $id => $post ) {
			if ( $post_type && $post['post_type'] !== $post_type ) {
				continue;
			}
			if ( ! empty( $meta_key ) ) {
				$val = $w2w_test_db['postmeta'][ $id ][ $meta_key ] ?? null;
				if ( null !== $meta_value && $val !== $meta_value ) {
					continue;
				}
				if ( null === $val ) {
					continue;
				}
			}
			$results[] = (object) $post;
		}
		return $results;
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( $url, $component );
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $string, $remove_breaks = false ) {
		$string = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $string );
		$string = strip_tags( $string );
		if ( $remove_breaks ) {
			$string = preg_replace( '/[\r\n\t ]+/', ' ', $string );
		}
		return trim( $string );
	}
}

// -------------------------------------------------------------
// AJAX & User Capability Mocks
// -------------------------------------------------------------

class W2W_Test_Ajax_Exception extends \Exception {
	public $response;
	public $status_code;

	public function __construct( $response, $status_code = 200 ) {
		$this->response    = $response;
		$this->status_code = $status_code;
		parent::__construct( is_array( $response ) ? json_encode( $response ) : (string) $response, $status_code );
	}
}

global $w2w_test_current_user_id, $w2w_test_current_user_caps, $w2w_test_valid_nonces, $w2w_test_transients;
$w2w_test_current_user_id  = 1;
$w2w_test_current_user_caps = array( 'manage_options' => true );
$w2w_test_valid_nonces     = array( 'w2w_admin_nonce' => true );
$w2w_test_transients       = array();

if ( ! function_exists( 'check_ajax_referer' ) ) {
	function check_ajax_referer( $action = -1, $query_arg = false, $die = true ) {
		global $w2w_test_valid_nonces;
		$nonce = isset( $_REQUEST[ $query_arg ] ) ? $_REQUEST[ $query_arg ] : ( isset( $_REQUEST['_ajax_nonce'] ) ? $_REQUEST['_ajax_nonce'] : '' );
		$valid = ! empty( $w2w_test_valid_nonces[ $action ] ) && 'valid_nonce' === $nonce;
		if ( ! $valid && $die ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
		}
		return $valid;
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return is_array( $value ) ? array_map( 'wp_unslash', $value ) : stripslashes( $value );
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $capability, ...$args ) {
		global $w2w_test_current_user_caps;
		return ! empty( $w2w_test_current_user_caps[ $capability ] );
	}
}

if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id() {
		global $w2w_test_current_user_id;
		return $w2w_test_current_user_id ?? 1;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $transient, $value, $expiration = 0 ) {
		global $w2w_test_transients;
		$w2w_test_transients[ $transient ] = $value;
		return true;
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $transient ) {
		global $w2w_test_transients;
		return $w2w_test_transients[ $transient ] ?? false;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $transient ) {
		global $w2w_test_transients;
		unset( $w2w_test_transients[ $transient ] );
		return true;
	}
}

if ( ! function_exists( 'wp_generate_uuid4' ) ) {
	function wp_generate_uuid4() {
		return sprintf(
			'%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0x0fff ) | 0x4000,
			mt_rand( 0, 0x3fff ) | 0x8000,
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0xffff )
		);
	}
}

if ( ! function_exists( 'get_permalink' ) ) {
	function get_permalink( $post_id = 0 ) {
		return 'http://example.org/?p=' . (int) $post_id;
	}
}

if ( ! function_exists( 'wp_send_json_success' ) ) {
	function wp_send_json_success( $data = null, $status_code = null, $options = 0 ) {
		$response = array(
			'success' => true,
			'data'    => $data,
		);
		throw new W2W_Test_Ajax_Exception( $response, $status_code ?: 200 );
	}
}

if ( ! function_exists( 'wp_send_json_error' ) ) {
	function wp_send_json_error( $data = null, $status_code = null, $options = 0 ) {
		$response = array(
			'success' => false,
			'data'    => $data,
		);
		throw new W2W_Test_Ajax_Exception( $response, $status_code ?: 400 );
	}
}

// Load autoloader into the test harness.
require_once dirname( __DIR__ ) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'class-autoloader.php';
W2W_Autoloader::register();


