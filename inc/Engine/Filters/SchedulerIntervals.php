<?php
/**
 * Scheduler Intervals Filter
 *
 * Defines available scheduling intervals for flow execution.
 * Single source of truth for all recurring interval options.
 *
 * @package DataMachine\Engine\Filters
 * @since 0.8.9
 */

defined( 'ABSPATH' ) || exit;

/**
 * Get default scheduler intervals.
 *
 * @return array Interval definitions with label and seconds.
 */
function datamachine_get_default_scheduler_intervals(): array {
	return array(
		'every_5_minutes' => array(
			'label'   => 'Every 5 Minutes',
			'seconds' => 300,
		),
		'hourly'          => array(
			'label'   => 'Hourly',
			'seconds' => HOUR_IN_SECONDS,
		),
		'every_2_hours'   => array(
			'label'   => 'Every 2 Hours',
			'seconds' => HOUR_IN_SECONDS * 2,
		),
		'every_4_hours'   => array(
			'label'   => 'Every 4 Hours',
			'seconds' => HOUR_IN_SECONDS * 4,
		),
		'qtrdaily'        => array(
			'label'   => 'Every 6 Hours',
			'seconds' => HOUR_IN_SECONDS * 6,
		),
		'twicedaily'      => array(
			'label'   => 'Twice Daily',
			'seconds' => HOUR_IN_SECONDS * 12,
		),
		'daily'           => array(
			'label'   => 'Daily',
			'seconds' => DAY_IN_SECONDS,
		),
		'every_3_days'    => array(
			'label'   => 'Every 3 Days',
			'seconds' => DAY_IN_SECONDS * 3,
		),
		'weekly'          => array(
			'label'   => 'Weekly',
			'seconds' => WEEK_IN_SECONDS,
		),
		'monthly'         => array(
			'label'   => 'Monthly',
			'seconds' => DAY_IN_SECONDS * 30,
		),
	);
}

/**
 * Register scheduler intervals filter.
 */
function datamachine_register_scheduler_intervals_filter(): void {
	add_filter(
		'datamachine_scheduler_intervals',
		function ( $intervals ) {
			return array_merge( datamachine_get_default_scheduler_intervals(), $intervals );
		},
		10
	);
}

datamachine_register_scheduler_intervals_filter();
