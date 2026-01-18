<?php
/**
 * Cache Manager
 *
 * Centralized cache invalidation for all cached services.
 * Provides single entry point for clearing caches when handlers,
 * step types, or tools are dynamically registered.
 *
 * @package DataMachine\Services
 * @since 0.8.1
 */

namespace DataMachine\Services;

defined( 'ABSPATH' ) || exit;

class CacheManager {

	/**
	 * Clear all service caches.
	 *
	 * Call when major changes occur that could affect multiple cached systems.
	 */
	public static function clearAll(): void {
		self::clearStepTypeCaches();
		self::clearHandlerCaches();
		self::clearToolCaches();
	}

	/**
	 * Clear handler-related caches.
	 *
	 * Call when handlers are dynamically registered.
	 */
	public static function clearHandlerCaches(): void {
		HandlerService::clearCache();
		AuthProviderService::clearCache();

		if ( class_exists( '\DataMachine\Api\Chat\Tools\HandlerDocumentation' ) ) {
			\DataMachine\Api\Chat\Tools\HandlerDocumentation::clearCache();
		}

		self::clearToolCaches();
	}

	/**
	 * Clear step type caches.
	 *
	 * Call when step types are dynamically registered.
	 */
	public static function clearStepTypeCaches(): void {
		StepTypeService::clearCache();

		if ( class_exists( '\DataMachine\Api\Chat\Tools\HandlerDocumentation' ) ) {
			\DataMachine\Api\Chat\Tools\HandlerDocumentation::clearCache();
		}

		self::clearToolCaches();
	}

	/**
	 * Clear tool definition caches.
	 *
	 * Call when tool definitions need to be rebuilt.
	 */
	public static function clearToolCaches(): void {
		if ( class_exists( '\DataMachine\Engine\AI\Tools\ToolManager' ) ) {
			\DataMachine\Engine\AI\Tools\ToolManager::clearCache();
		}
	}
}
