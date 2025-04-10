<?php
/**
 * A simple Service Locator / Dependency Injection Container.
 *
 * Manages the creation and retrieval of shared service instances.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes
 * @since      0.9.0 // Or next version
 */
class Data_Machine_Service_Locator {

	/**
	 * Holds the registered service definitions (callables).
	 * @var array<string, callable>
	 */
	private $definitions = [];

	/**
	 * Holds the created service instances (singletons).
	 * @var array<string, object>
	 */
	private $instances = [];

	/**
	 * Registers a service definition.
	 *
	 * @param string   $key      A unique key for the service (e.g., 'database_modules').
	 * @param callable $callable A function that returns an instance of the service.
	 *                           This callable will receive the locator instance itself as an argument.
	 */
	public function register(string $key, callable $callable): void {
		$this->definitions[$key] = $callable;
	}

	/**
	 * Gets a service instance.
	 *
	 * If the instance hasn't been created yet, it uses the registered callable
	 * to create it and stores it for subsequent requests (singleton).
	 *
	 * @param string $key The unique key of the service to retrieve.
	 * @return object The service instance.
	 * @throws Exception If the service key is not registered.
	 */
	public function get(string $key): object {
		// Return existing instance if already created (singleton)
		if (isset($this->instances[$key])) {
			return $this->instances[$key];
		}

		// Check if the service definition exists
		if (!isset($this->definitions[$key])) {
			throw new Exception("Service '{$key}' not registered in the locator.");
		}

		// Create the instance using the registered callable, passing the locator itself
		$callable = $this->definitions[$key];
		$instance = $callable($this); // Pass locator for dependency resolution

		// Store the created instance
		$this->instances[$key] = $instance;

		return $instance;
	}

	/**
	 * Checks if a service is registered.
	 *
	 * @param string $key The service key.
	 * @return bool True if registered, false otherwise.
	 */
	public function has(string $key): bool {
		return isset($this->definitions[$key]);
	}
}