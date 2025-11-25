<?php
/**
 * Plugin Settings Accessor
 *
 * Centralized access point for datamachine_settings option.
 * Provides caching and type-safe getters for plugin-wide settings.
 *
 * @package DataMachine\Core
 * @since 0.2.10
 */

namespace DataMachine\Core;

if (!defined('ABSPATH')) {
	exit;
}

class PluginSettings {

	private static ?array $cache = null;

	/**
	 * Get all plugin settings.
	 *
	 * @return array
	 */
	public static function all(): array {
		if (self::$cache === null) {
			self::$cache = get_option('datamachine_settings', []);
		}
		return self::$cache;
	}

	/**
	 * Get a specific setting value.
	 *
	 * @param string $key     Setting key
	 * @param mixed  $default Default value if key not found
	 * @return mixed
	 */
	public static function get(string $key, mixed $default = null): mixed {
		$settings = self::all();
		return $settings[$key] ?? $default;
	}

	/**
	 * Clear the settings cache.
	 * Called automatically when datamachine_settings option is updated.
	 *
	 * @return void
	 */
	public static function clearCache(): void {
		self::$cache = null;
	}
}
