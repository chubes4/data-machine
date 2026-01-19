<?php
/**
 * Centralized HTTP client for Data Machine
 *
 * Provides standardized HTTP request handling with consistent headers,
 * error handling, and logging across the entire ecosystem.
 *
 * @package DataMachine\Core
 * @since 0.5.0
 */

namespace DataMachine\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HttpClient {

	private const VALID_METHODS = array( 'GET', 'POST', 'PUT', 'DELETE', 'PATCH' );

	private const SUCCESS_CODES = array(
		'GET'    => array( 200, 202 ),
		'POST'   => array( 200, 201, 202 ),
		'PUT'    => array( 200, 201, 204 ),
		'PATCH'  => array( 200, 204 ),
		'DELETE' => array( 200, 202, 204 ),
	);

	private const ERROR_KEYS = array( 'message', 'error', 'error_description', 'detail' );

	private const BROWSER_USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

	/**
	 * Perform HTTP request with standardized handling
	 *
	 * @param string $method  HTTP method (GET, POST, PUT, DELETE, PATCH)
	 * @param string $url     Request URL
	 * @param array  $options Request options:
	 *                        - headers: array - Additional headers to merge
	 *                        - body: string|array - Request body (for POST/PUT/PATCH)
	 *                        - timeout: int - Request timeout (default 120)
	 *                        - browser_mode: bool - Use browser-like headers (default false)
	 *                        - context: string - Context for logging (default 'HTTP Request')
	 * @return array{success: bool, data?: string, status_code?: int, headers?: array, response?: array, error?: string}
	 */
	public static function request( string $method, string $url, array $options = array() ): array {
		$method  = strtoupper( $method );
		$context = $options['context'] ?? 'HTTP Request';

		if ( ! in_array( $method, self::VALID_METHODS, true ) ) {
			do_action(
				'datamachine_log',
				'error',
				'HTTP Request: Invalid method',
				array(
					'method'  => $method,
					'context' => $context,
				)
			);
			return array(
				'success' => false,
				'error'   => 'Invalid HTTP method',
			);
		}

		$args = self::buildRequestArgs( $method, $options );

		$response = ( 'GET' === $method )
			? wp_remote_get( $url, $args )
			: wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return self::handleWpError( $response, $method, $url, $context );
		}

		$status_code   = wp_remote_retrieve_response_code( $response );
		$body          = wp_remote_retrieve_body( $response );
		$success_codes = self::SUCCESS_CODES[ $method ] ?? array( 200 );

		if ( ! in_array( $status_code, $success_codes, true ) ) {
			return self::handleHttpError( $status_code, $body, $method, $url, $context );
		}

		return array(
			'success'     => true,
			'data'        => $body,
			'status_code' => $status_code,
			'headers'     => wp_remote_retrieve_headers( $response ),
			'response'    => $response,
		);
	}

	/**
	 * Perform HTTP GET request
	 *
	 * @param string $url     Request URL
	 * @param array  $options Request options
	 * @return array Response array
	 */
	public static function get( string $url, array $options = array() ): array {
		return self::request( 'GET', $url, $options );
	}

	/**
	 * Perform HTTP POST request
	 *
	 * @param string $url     Request URL
	 * @param array  $options Request options
	 * @return array Response array
	 */
	public static function post( string $url, array $options = array() ): array {
		return self::request( 'POST', $url, $options );
	}

	/**
	 * Perform HTTP PUT request
	 *
	 * @param string $url     Request URL
	 * @param array  $options Request options
	 * @return array Response array
	 */
	public static function put( string $url, array $options = array() ): array {
		return self::request( 'PUT', $url, $options );
	}

	/**
	 * Perform HTTP PATCH request
	 *
	 * @param string $url     Request URL
	 * @param array  $options Request options
	 * @return array Response array
	 */
	public static function patch( string $url, array $options = array() ): array {
		return self::request( 'PATCH', $url, $options );
	}

	/**
	 * Perform HTTP DELETE request
	 *
	 * @param string $url     Request URL
	 * @param array  $options Request options
	 * @return array Response array
	 */
	public static function delete( string $url, array $options = array() ): array {
		return self::request( 'DELETE', $url, $options );
	}

	/**
	 * Build request arguments from options
	 */
	private static function buildRequestArgs( string $method, array $options ): array {
		$browser_mode = $options['browser_mode'] ?? false;
		$timeout      = $options['timeout'] ?? 120;

		$default_user_agent = sprintf(
			'DataMachine/%s (+%s)',
			defined( 'DATAMACHINE_VERSION' ) ? DATAMACHINE_VERSION : '1.0',
			home_url()
		);

		$default_headers = $browser_mode
			? array(
				'User-Agent'                => self::BROWSER_USER_AGENT,
				'Accept'                    => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
				'Accept-Language'           => 'en-US,en;q=0.9',
				'Cache-Control'             => 'no-cache',
				'Pragma'                    => 'no-cache',
				'Upgrade-Insecure-Requests' => '1',
				'Sec-Fetch-Dest'            => 'document',
				'Sec-Fetch-Mode'            => 'navigate',
				'Sec-Fetch-Site'            => 'none',
				'Sec-Fetch-User'            => '?1',
				'Connection'                => 'keep-alive',
			)
			: array(
				'User-Agent' => $default_user_agent,
			);

		$headers = array_merge( $default_headers, $options['headers'] ?? array() );

		$args = array(
			'timeout' => $timeout,
			'headers' => $headers,
		);

		if ( 'GET' !== $method ) {
			$args['method'] = $method;
		}

		if ( isset( $options['body'] ) ) {
			$args['body'] = $options['body'];
		}

		return $args;
	}

	/**
	 * Handle WP_Error response
	 */
	private static function handleWpError( \WP_Error $response, string $method, string $url, string $context ): array {
		$error_message = sprintf(
			'Failed to connect to %1$s: %2$s',
			$context,
			$response->get_error_message()
		);

		do_action(
			'datamachine_log',
			'error',
			'HTTP Request: Connection failed',
			array(
				'context'    => $context,
				'url'        => $url,
				'method'     => $method,
				'error'      => $response->get_error_message(),
				'error_code' => $response->get_error_code(),
			)
		);

		return array(
			'success' => false,
			'error'   => $error_message,
		);
	}

	/**
	 * Handle non-success HTTP status code
	 */
	private static function handleHttpError( int $status_code, string $body, string $method, string $url, string $context ): array {
		$error_message = sprintf(
			'%1$s %2$s returned HTTP %3$d',
			$context,
			$method,
			$status_code
		);

		$error_details = self::extractErrorDetails( $body );

		if ( $error_details ) {
			$error_message .= ': ' . $error_details;
		}

		do_action(
			'datamachine_log',
			'error',
			'HTTP Request: Error response',
			array(
				'context'      => $context,
				'url'          => $url,
				'method'       => $method,
				'status_code'  => $status_code,
				'body_preview' => substr( $body, 0, 200 ),
			)
		);

		return array(
			'success' => false,
			'error'   => $error_message,
		);
	}

	/**
	 * Extract error details from response body
	 */
	private static function extractErrorDetails( string $body ): ?string {
		if ( empty( $body ) ) {
			return null;
		}

		$decoded = json_decode( $body, true );
		if ( is_array( $decoded ) ) {
			foreach ( self::ERROR_KEYS as $key ) {
				if ( isset( $decoded[ $key ] ) && is_string( $decoded[ $key ] ) ) {
					return $decoded[ $key ];
				}
			}
		}

		$first_line = strtok( $body, "\n" );
		return strlen( $first_line ) > 100 ? substr( $first_line, 0, 97 ) . '...' : $first_line;
	}
}
