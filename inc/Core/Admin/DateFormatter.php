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
	 * Optionally shows relative time for recent dates.
	 *
	 * @param string|null $mysql_datetime MySQL datetime string (Y-m-d H:i:s)
	 * @param bool        $include_relative Whether to show relative time for recent dates
	 * @return string Formatted datetime string or "Never"
	 */
	public static function format_for_display( ?string $mysql_datetime, bool $include_relative = true ): string {
		if ( empty( $mysql_datetime ) || $mysql_datetime === '0000-00-00 00:00:00' ) {
			return __( 'Never', 'datamachine' );
		}

		$timestamp = strtotime( $mysql_datetime );
		
		if ( false === $timestamp ) {
			return __( 'Invalid date', 'datamachine' );
		}

		if ( $include_relative ) {
			$relative = self::get_relative_time( $timestamp );
			if ( $relative ) {
				return $relative;
			}
		}

		$date_format = get_option( 'date_format' );
		$time_format = get_option( 'time_format' );
		
		return wp_date( "{$date_format} {$time_format}", $timestamp );
	}

	/**
	 * Get relative time string for recent timestamps.
	 *
	 * Returns strings like "Just now", "2 hours ago", "3 days ago".
	 * Returns null for dates older than 7 days (use absolute date instead).
	 *
	 * @param int $timestamp Unix timestamp
	 * @return string|null Relative time string or null for old dates
	 */
	private static function get_relative_time( int $timestamp ): ?string {
		$now = current_time( 'timestamp' );
		$diff = $now - $timestamp;

		if ( $diff < 0 ) {
			return null;
		}

		if ( $diff < 60 ) {
			return __( 'Just now', 'datamachine' );
		}

		if ( $diff < 3600 ) {
			$minutes = floor( $diff / 60 );
			return sprintf(
				_n( '%s minute ago', '%s minutes ago', $minutes, 'datamachine' ),
				number_format_i18n( $minutes )
			);
		}

		if ( $diff < 86400 ) {
			$hours = floor( $diff / 3600 );
			return sprintf(
				_n( '%s hour ago', '%s hours ago', $hours, 'datamachine' ),
				number_format_i18n( $hours )
			);
		}

		if ( $diff < 604800 ) {
			$days = floor( $diff / 86400 );
			return sprintf(
				_n( '%s day ago', '%s days ago', $days, 'datamachine' ),
				number_format_i18n( $days )
			);
		}

		return null;
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

		$timestamp = strtotime( $mysql_datetime );
		
		if ( false === $timestamp ) {
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

		$timestamp = strtotime( $mysql_datetime );
		
		if ( false === $timestamp ) {
			return '';
		}

		$time_format = get_option( 'time_format' );
		return wp_date( $time_format, $timestamp );
	}

	/**
	 * Format a Unix timestamp for display.
	 *
	 * @param int|null $timestamp Unix timestamp
	 * @param bool     $include_relative Whether to show relative time for recent dates
	 * @return string Formatted datetime string or "Never"
	 */
	public static function format_timestamp( ?int $timestamp, bool $include_relative = true ): string {
		if ( empty( $timestamp ) ) {
			return __( 'Never', 'datamachine' );
		}

		if ( $include_relative ) {
			$relative = self::get_relative_time( $timestamp );
			if ( $relative ) {
				return $relative;
			}
		}

		$date_format = get_option( 'date_format' );
		$time_format = get_option( 'time_format' );
		
		return wp_date( "{$date_format} {$time_format}", $timestamp );
	}
}
