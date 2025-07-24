<?php
/**
 * Data Machine Encryption Helper
 *
 * Handles encryption and decryption of sensitive data.
 *
 * @package Data_Machine
 * @subpackage Helpers
 */

namespace DataMachine\Helpers;

use DataMachine\Constants;
use DataMachine\Helpers\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class EncryptionHelper
 */
class EncryptionHelper {

	private const ENCRYPTION_METHOD = 'aes-256-cbc';

	/**
	 * Encrypts data using OpenSSL with a unique IV.
	 *
	 * @param string $data The data to encrypt.
	 * @return string|false The encrypted data (Base64 encoded with IV prepended) or false on failure.
	 */
	public static function encrypt( $data ) {
		if ( ! function_exists( 'openssl_encrypt' ) || ! function_exists('openssl_random_pseudo_bytes') || ! function_exists('openssl_cipher_iv_length') ) {
			return false;
		}
		if ( empty( $data ) ) {
			return ''; // Return empty string if input is empty
		}

		$key = Constants::get_encryption_key(); // Get the key from Constants
		$iv_length = openssl_cipher_iv_length(self::ENCRYPTION_METHOD);
		if (false === $iv_length) {
			return false;
		}

		$iv = openssl_random_pseudo_bytes($iv_length);
		if (false === $iv) {
			return false;
		}

		// Encrypt using OPENSSL_RAW_DATA
		$encrypted_raw = openssl_encrypt( $data, self::ENCRYPTION_METHOD, $key, OPENSSL_RAW_DATA, $iv );

		if ( false === $encrypted_raw ) {
			// Optionally log the OpenSSL error
			return false;
		}

		// Prepend the IV to the raw encrypted data and Base64 encode
		return base64_encode( $iv . $encrypted_raw ); 
	}

	/**
	 * Decrypts data using OpenSSL, extracting the prepended IV.
	 *
	 * @param string $encrypted_data_with_iv The Base64 encoded encrypted data (with IV prepended).
	 * @return string|false The decrypted data or false on failure.
	 */
	public static function decrypt( $encrypted_data_with_iv ) {
		if ( ! function_exists( 'openssl_decrypt' ) || ! function_exists('openssl_cipher_iv_length') ) {
			return false;
		}
		if ( empty( $encrypted_data_with_iv ) ) {
			return ''; // Return empty string if input is empty
		}

		$decoded_data = base64_decode( $encrypted_data_with_iv );
		if ( false === $decoded_data ) {
			return false; // Invalid Base64 string
		}

		$key = Constants::get_encryption_key(); // Get the key from Constants
		$iv_length = openssl_cipher_iv_length(self::ENCRYPTION_METHOD);
		if (false === $iv_length) {
			return false;
		}

		// Check if decoded data is long enough to contain the IV
		if (strlen($decoded_data) < $iv_length) {
			return false;
		}

		// Extract the IV from the beginning
		$iv = substr($decoded_data, 0, $iv_length);
		// Extract the actual ciphertext
		$ciphertext = substr($decoded_data, $iv_length);

		// Decrypt using OPENSSL_RAW_DATA and the extracted IV
		$decrypted = openssl_decrypt( $ciphertext, self::ENCRYPTION_METHOD, $key, OPENSSL_RAW_DATA, $iv );

		if ( false === $decrypted ) {
			// Optionally log the OpenSSL error - this is where the 'bad decrypt' often shows up if key/IV is wrong
			return false;
		}

		return $decrypted;
	}
} 