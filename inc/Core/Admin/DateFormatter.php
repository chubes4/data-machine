<?php
/**
 * Date Formatter Utility
 *
 * Centralized date/time formatting for all display contexts.
 * Uses WordPress native functions to respect timezone and format settings.
 *
 * @package DataMachine\Core\Admin
 */

namespace DataMachine\Core\Admin;

defined('ABSPATH') || exit;

class DateFormatter {

	/**
	 * Format a MySQL datetime string for display.
	 *
	 * Uses WordPress timezone and date/time format settings.
	 *
	 * @param string|null $mysql_datetime MySQL datetime string (Y-m-d H:i:s)
	 * @param string|null $status Run status ('completed', 'failed', 'completed_no_items')
	 * @return string Formatted datetime string
	 */
	public static function format_for_display( ?string $mysql_datetime, ?string $status = null ): string {
		if ( empty( $mysql_datetime ) || $mysql_datetime === '0000-00-00 00:00:00' ) {
			return __( 'Never', 'datamachine' );
		}

		try {
			$timestamp = ( new \DateTime( $mysql_datetime, new \DateTimeZone( 'UTC' ) ) )->getTimestamp();
		} catch ( \Exception $e ) {
			return __( 'Invalid date', 'datamachine' );
		}

		$date_format = get_option( 'date_format' );
		$time_format = get_option( 'time_format' );
		$display = wp_date( "{$date_format} {$time_format}", $timestamp );

		if ( $status === 'failed' ) {
			$display .= ' ' . __( '(error)', 'datamachine' );
		} elseif ( $status === 'completed_no_items' ) {
			$display .= ' ' . __( '(no items)', 'datamachine' );
		}

		return $display;
	}

	/**
	 * Format a MySQL datetime string for display (date only, no time).
	 *
	 * @param string|null $mysql_datetime MySQL datetime string
	 * @return string Formatted date string or "Never"
	 */
	public static function format_date_only( ?string $mysql_datetime ): string {
		if ( empty( $mysql_datetime ) || $mysql_datetime === '0000-00-00 00:00:00' ) {
			return __( 'Never', 'datamachine' );
		}

		try {
			$timestamp = ( new \DateTime( $mysql_datetime, new \DateTimeZone( 'UTC' ) ) )->getTimestamp();
		} catch ( \Exception $e ) {
			return __( 'Invalid date', 'datamachine' );
		}

		$date_format = get_option( 'date_format' );
		return wp_date( $date_format, $timestamp );
	}

	/**
	 * Format a MySQL datetime string for display (time only, no date).
	 *
	 * @param string|null $mysql_datetime MySQL datetime string
	 * @return string Formatted time string or empty string
	 */
	public static function format_time_only( ?string $mysql_datetime ): string {
		if ( empty( $mysql_datetime ) || $mysql_datetime === '0000-00-00 00:00:00' ) {
			return '';
		}

		try {
			$timestamp = ( new \DateTime( $mysql_datetime, new \DateTimeZone( 'UTC' ) ) )->getTimestamp();
		} catch ( \Exception $e ) {
			return '';
		}

		$time_format = get_option( 'time_format' );
		return wp_date( $time_format, $timestamp );
	}

	/**
	 * Format a Unix timestamp for display.
	 *
	 * @param int|null $timestamp Unix timestamp
	 * @return string Formatted datetime string or "Never"
	 */
	public static function format_timestamp( ?int $timestamp ): string {
		if ( empty( $timestamp ) ) {
			return __( 'Never', 'datamachine' );
		}

		$date_format = get_option( 'date_format' );
		$time_format = get_option( 'time_format' );
		
		return wp_date( "{$date_format} {$time_format}", $timestamp );
	}
}
