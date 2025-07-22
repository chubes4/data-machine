<?php

/**
 * Class Data_Machine_Memory_Guard
 *
 * Provides memory usage monitoring and protection against memory exhaustion.
 * Prevents server crashes from processing large files or data sets.
 *
 * @package Data_Machine
 * @subpackage Helpers
 */
class Data_Machine_Memory_Guard {

	/**
	 * Default memory safety margin (20% of available memory)
	 */
	const DEFAULT_SAFETY_MARGIN = 0.2;

	/**
	 * Logger instance
	 * @var Data_Machine_Logger
	 */
	private $logger;

	/**
	 * Constructor
	 *
	 * @param Data_Machine_Logger $logger Logger instance
	 */
	public function __construct( Data_Machine_Logger $logger = null ) {
		$this->logger = $logger;
	}

	/**
	 * Check if there's enough memory available for an operation.
	 *
	 * @param int $required_bytes Estimated memory needed for operation
	 * @param float $safety_margin Safety margin (0.2 = 20% buffer)
	 * @return bool True if enough memory available
	 */
	public function has_enough_memory( $required_bytes, $safety_margin = self::DEFAULT_SAFETY_MARGIN ) {
		$current_usage = memory_get_usage( true );
		$memory_limit = $this->get_memory_limit_bytes();
		
		// Calculate available memory with safety margin
		$available_memory = $memory_limit * ( 1 - $safety_margin ) - $current_usage;
		
		$has_enough = $available_memory >= $required_bytes;
		
		$this->logger?->info( 'Memory check performed', [
			'current_usage' => $this->format_bytes( $current_usage ),
			'memory_limit' => $this->format_bytes( $memory_limit ),
			'required' => $this->format_bytes( $required_bytes ),
			'available' => $this->format_bytes( $available_memory ),
			'has_enough' => $has_enough
		] );
		
		return $has_enough;
	}

	/**
	 * Get current memory usage statistics.
	 *
	 * @return array Memory usage information
	 */
	public function get_memory_stats() {
		$current_usage = memory_get_usage( true );
		$current_usage_real = memory_get_usage( false );
		$peak_usage = memory_get_peak_usage( true );
		$memory_limit = $this->get_memory_limit_bytes();
		
		return [
			'current_usage' => $current_usage,
			'current_usage_formatted' => $this->format_bytes( $current_usage ),
			'current_usage_real' => $current_usage_real,
			'current_usage_real_formatted' => $this->format_bytes( $current_usage_real ),
			'peak_usage' => $peak_usage,
			'peak_usage_formatted' => $this->format_bytes( $peak_usage ),
			'memory_limit' => $memory_limit,
			'memory_limit_formatted' => $this->format_bytes( $memory_limit ),
			'usage_percentage' => round( ( $current_usage / $memory_limit ) * 100, 2 )
		];
	}

	/**
	 * Estimate memory needed to load a file.
	 *
	 * @param string $file_path Path to file
	 * @param float $multiplier Memory multiplier (1.5 = 150% of file size)
	 * @return int Estimated memory needed in bytes
	 */
	public function estimate_file_memory_usage( $file_path, $multiplier = 1.5 ) {
		if ( ! file_exists( $file_path ) ) {
			return 0;
		}
		
		$file_size = filesize( $file_path );
		if ( $file_size === false ) {
			return 0;
		}
		
		// Estimate memory usage (file size + processing overhead)
		return (int) ( $file_size * $multiplier );
	}

	/**
	 * Check if a file can be safely loaded into memory.
	 *
	 * @param string $file_path Path to file
	 * @param float $multiplier Memory multiplier for processing overhead
	 * @param float $safety_margin Safety margin percentage
	 * @return bool True if file can be safely loaded
	 */
	public function can_load_file( $file_path, $multiplier = 1.5, $safety_margin = self::DEFAULT_SAFETY_MARGIN ) {
		$estimated_memory = $this->estimate_file_memory_usage( $file_path, $multiplier );
		
		if ( $estimated_memory === 0 ) {
			return false;
		}
		
		return $this->has_enough_memory( $estimated_memory, $safety_margin );
	}

	/**
	 * Get safe chunk size for file processing.
	 *
	 * @param float $memory_percentage Percentage of available memory to use
	 * @return int Safe chunk size in bytes
	 */
	public function get_safe_chunk_size( $memory_percentage = 0.1 ) {
		$available_memory = $this->get_available_memory();
		$chunk_size = (int) ( $available_memory * $memory_percentage );
		
		// Ensure minimum 1KB, maximum 10MB chunks
		$chunk_size = max( 1024, min( $chunk_size, 10 * 1024 * 1024 ) );
		
		return $chunk_size;
	}

	/**
	 * Get available memory for operations.
	 *
	 * @param float $safety_margin Safety margin percentage
	 * @return int Available memory in bytes
	 */
	public function get_available_memory( $safety_margin = self::DEFAULT_SAFETY_MARGIN ) {
		$current_usage = memory_get_usage( true );
		$memory_limit = $this->get_memory_limit_bytes();
		
		return (int) ( $memory_limit * ( 1 - $safety_margin ) - $current_usage );
	}

	/**
	 * Get PHP memory limit in bytes.
	 *
	 * @return int Memory limit in bytes
	 */
	private function get_memory_limit_bytes() {
		$memory_limit = ini_get( 'memory_limit' );
		
		if ( $memory_limit === '-1' ) {
			// No limit set, use reasonable default (256MB)
			return 256 * 1024 * 1024;
		}
		
		return $this->parse_memory_string( $memory_limit );
	}

	/**
	 * Parse memory string (e.g., "256M", "1G") to bytes.
	 *
	 * @param string $memory_string Memory string from PHP configuration
	 * @return int Memory in bytes
	 */
	private function parse_memory_string( $memory_string ) {
		$memory_string = trim( $memory_string );
		$last_char = strtolower( substr( $memory_string, -1 ) );
		$numeric_value = (int) substr( $memory_string, 0, -1 );
		
		switch ( $last_char ) {
			case 'g':
				return $numeric_value * 1024 * 1024 * 1024;
			case 'm':
				return $numeric_value * 1024 * 1024;
			case 'k':
				return $numeric_value * 1024;
			default:
				return (int) $memory_string;
		}
	}

	/**
	 * Format bytes into human-readable string.
	 *
	 * @param int $bytes Number of bytes
	 * @return string Formatted string (e.g., "2.5 MB")
	 */
	private function format_bytes( $bytes ) {
		$units = [ 'B', 'KB', 'MB', 'GB', 'TB' ];
		$power = floor( log( $bytes, 1024 ) );
		$power = min( $power, count( $units ) - 1 );
		
		return round( $bytes / pow( 1024, $power ), 2 ) . ' ' . $units[ $power ];
	}

	/**
	 * Monitor memory usage during operation execution.
	 *
	 * @param callable $operation Operation to execute
	 * @param string $operation_name Name for logging
	 * @return mixed Operation result
	 * @throws Exception If memory usage becomes dangerous
	 */
	public function monitor_operation( callable $operation, $operation_name = 'operation' ) {
		$start_memory = memory_get_usage( true );
		$start_stats = $this->get_memory_stats();
		
		$this->logger?->info( "Starting monitored operation: {$operation_name}", $start_stats );
		
		try {
			$result = $operation();
			
			$end_memory = memory_get_usage( true );
			$memory_used = $end_memory - $start_memory;
			$end_stats = $this->get_memory_stats();
			
			$this->logger?->info( "Completed monitored operation: {$operation_name}", [
				'memory_used' => $this->format_bytes( $memory_used ),
				'final_stats' => $end_stats
			] );
			
			return $result;
			
		} catch ( Exception $e ) {
			$error_memory = memory_get_usage( true );
			$memory_used = $error_memory - $start_memory;
			
			$this->logger?->error( "Failed monitored operation: {$operation_name}", [
				'error' => $e->getMessage(),
				'memory_used' => $this->format_bytes( $memory_used ),
				'error_stats' => $this->get_memory_stats()
			] );
			
			throw $e;
		}
	}

	/**
	 * Create a memory-safe file reader that processes files in chunks.
	 *
	 * @param string $file_path Path to file
	 * @param callable $chunk_processor Function to process each chunk
	 * @param int $chunk_size Optional custom chunk size
	 * @return mixed Result from chunk processor
	 * @throws Exception If file cannot be processed safely
	 */
	public function process_file_safely( $file_path, callable $chunk_processor, $chunk_size = null ) {
		if ( ! file_exists( $file_path ) ) {
			throw new Exception( esc_html( "File not found: {$file_path}" ) );
		}
		
		if ( $chunk_size === null ) {
			$chunk_size = $this->get_safe_chunk_size();
		}
		
		$file_handle = fopen( $file_path, 'rb' );
		if ( $file_handle === false ) {
			throw new Exception( esc_html( "Cannot open file for reading: {$file_path}" ) );
		}
		
		try {
			$chunk_count = 0;
			$total_processed = 0;
			
			while ( ! feof( $file_handle ) ) {
				// Check memory before processing each chunk
				if ( ! $this->has_enough_memory( $chunk_size * 2 ) ) {
					throw new Exception( 'Insufficient memory to continue file processing safely' );
				}
				
				$chunk = fread( $file_handle, $chunk_size );
				if ( $chunk === false ) {
					throw new Exception( 'Failed to read file chunk' );
				}
				
				$chunk_processor( $chunk, $chunk_count );
				
				$chunk_count++;
				$total_processed += strlen( $chunk );
				
				// Log progress periodically
				if ( $chunk_count % 100 === 0 ) {
					$this->logger?->info( "File processing progress", [
						'file' => basename( $file_path ),
						'chunks_processed' => $chunk_count,
						'bytes_processed' => $this->format_bytes( $total_processed ),
						'memory_stats' => $this->get_memory_stats()
					] );
				}
			}
			
			return $total_processed;
			
		} finally {
			fclose( $file_handle );
		}
	}
}