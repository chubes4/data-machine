<?php
/**
 * Base WP-CLI Command
 *
 * Provides standardized formatting methods using WP-CLI's native Formatter.
 * All Data Machine CLI commands extend this class.
 *
 * @package DataMachine\Cli
 * @since 0.15.1
 */

namespace DataMachine\Cli;

use WP_CLI;
use WP_CLI_Command;

defined( 'ABSPATH' ) || exit;

/**
 * Base WP-CLI command class with standardized formatting methods.
 *
 * All Data Machine CLI commands extend this class to access shared
 * formatting utilities using WP-CLI's native Formatter.
 *
 * @since 0.15.1
 */
class BaseCommand extends WP_CLI_Command {

	/**
	 * Format and display items using WP-CLI's native Formatter.
	 *
	 * @param array  $items      Items to display (array of associative arrays).
	 * @param array  $fields     Default fields/columns to display.
	 * @param array  $assoc_args Command arguments (format, fields).
	 * @param string $id_field   Field name to use for --format=ids.
	 */
	protected function format_items( array $items, array $fields, array $assoc_args, string $id_field = '' ): void {
		if ( empty( $items ) ) {
			WP_CLI::warning( 'No items found.' );
			return;
		}

		// Set ID field for --format=ids.
		if ( $id_field && ! isset( $assoc_args['field'] ) ) {
			$format = $assoc_args['format'] ?? 'table';
			if ( 'ids' === $format ) {
				$assoc_args['field'] = $id_field;
			}
		}

		$formatter = new \WP_CLI\Formatter( $assoc_args, $fields );
		$formatter->display_items( $items );
	}

	/**
	 * Output pagination info (table format only).
	 *
	 * @param int    $offset      Current offset.
	 * @param int    $count       Items returned.
	 * @param int    $total       Total items available.
	 * @param string $format      Current output format.
	 * @param string $item_label  Label for items (e.g., 'flows', 'pipelines').
	 */
	protected function output_pagination( int $offset, int $count, int $total, string $format = 'table', string $item_label = 'items' ): void {
		if ( 'table' !== $format ) {
			return;
		}

		$end = $offset + $count;
		if ( $end < $total ) {
			WP_CLI::log( "Showing {$offset} - {$end} of {$total} {$item_label}. Use --offset to see more." );
		} else {
			WP_CLI::log( "Showing {$offset} - {$end} of {$total} {$item_label}." );
		}
	}
}
