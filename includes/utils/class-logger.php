<?php
/**
 * Structured File Logger for Migration Operations.
 *
 * @package WixToWordPressMigrator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Handles structured logging of migration events, errors, and progress.
 */
class W2W_Logger {

	/**
	 * Log levels.
	 */
	public const LEVEL_INFO    = 'INFO';
	public const LEVEL_WARNING = 'WARNING';
	public const LEVEL_ERROR   = 'ERROR';
	public const LEVEL_DEBUG   = 'DEBUG';

	/**
	 * Relative log directory.
	 */
	private const LOG_DIR = 'w2w-logs';

	/**
	 * Relative log file name.
	 */
	private const LOG_FILE = 'migration.log';

	/**
	 * In-memory buffer for the current request.
	 *
	 * @var array<array<string, mixed>>
	 */
	private array $buffer = array();

	/**
	 * Logs an informational message.
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Optional context data.
	 * @return void
	 */
	public function info( string $message, array $context = array() ): void {
		$this->log( self::LEVEL_INFO, $message, $context );
	}

	/**
	 * Logs a warning message.
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Optional context data.
	 * @return void
	 */
	public function warning( string $message, array $context = array() ): void {
		$this->log( self::LEVEL_WARNING, $message, $context );
	}

	/**
	 * Logs an error message.
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Optional context data.
	 * @return void
	 */
	public function error( string $message, array $context = array() ): void {
		$this->log( self::LEVEL_ERROR, $message, $context );
	}

	/**
	 * Logs a debug message.
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Optional context data.
	 * @return void
	 */
	public function debug( string $message, array $context = array() ): void {
		$this->log( self::LEVEL_DEBUG, $message, $context );
	}

	/**
	 * Appends a log entry to memory and disk.
	 *
	 * @param string               $level   Log level.
	 * @param string               $message Message text.
	 * @param array<string, mixed> $context Context parameters.
	 * @return void
	 */
	public function log( string $level, string $message, array $context = array() ): void {
		$entry = array(
			'timestamp' => gmdate( 'Y-m-d H:i:s' ),
			'level'     => strtoupper( $level ),
			'message'   => $message,
			'context'   => $context,
		);

		$this->buffer[] = $entry;

		$log_file = $this->get_log_file_path();
		if ( ! empty( $log_file ) ) {
			$dir = dirname( $log_file );
			if ( ! is_dir( $dir ) && function_exists( 'wp_mkdir_p' ) ) {
				wp_mkdir_p( $dir );
			}

			$formatted = sprintf(
				"[%s] [%s]: %s %s\n",
				$entry['timestamp'],
				$entry['level'],
				$entry['message'],
				! empty( $context ) ? json_encode( $context, JSON_UNESCAPED_SLASHES ) : ''
			);

			if ( is_dir( $dir ) && is_writable( $dir ) ) {
				file_put_contents( $log_file, $formatted, FILE_APPEND | LOCK_EX );
			}
		}
	}

	/**
	 * Retrieves the in-memory buffered entries or entries read from file.
	 *
	 * @param int $limit Number of recent entries to return.
	 * @return array<array<string, mixed>>
	 */
	public function get_recent_entries( int $limit = 50 ): array {
		if ( ! empty( $this->buffer ) ) {
			return array_slice( $this->buffer, -$limit );
		}

		$log_file = $this->get_log_file_path();
		if ( ! file_exists( $log_file ) || ! is_readable( $log_file ) ) {
			return array();
		}

		$lines   = file( $log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
		$recent  = array_slice( (array) $lines, -$limit );
		$entries = array();

		foreach ( $recent as $line ) {
			$entries[] = array(
				'raw' => $line,
			);
		}

		return $entries;
	}

	/**
	 * Clears the log file and memory buffer.
	 *
	 * @return void
	 */
	public function clear_log(): void {
		$this->buffer = array();
		$log_file     = $this->get_log_file_path();
		if ( file_exists( $log_file ) && is_writable( $log_file ) ) {
			file_put_contents( $log_file, '' );
		}
	}

	/**
	 * Computes the absolute path to the log file.
	 *
	 * @return string
	 */
	public function get_log_file_path(): string {
		if ( defined( 'W2W_PLUGIN_DIR' ) ) {
			return rtrim( W2W_PLUGIN_DIR, '/\\' ) . DIRECTORY_SEPARATOR . self::LOG_DIR . DIRECTORY_SEPARATOR . self::LOG_FILE;
		}
		return dirname( __DIR__, 2 ) . DIRECTORY_SEPARATOR . self::LOG_DIR . DIRECTORY_SEPARATOR . self::LOG_FILE;
	}
}
