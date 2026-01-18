<?php
/**
 * REST API Files Endpoint
 *
 * Unified file API supporting both flow-level files and pipeline-level context.
 *
 * @package DataMachine\Api
 */

namespace DataMachine\Api;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Files {

	/**
	 * Register REST API routes.
	 */
	public static function register() {
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
	}

	/**
	 * Register /datamachine/v1/files endpoints.
	 */
	public static function register_routes() {
		// POST /files - Upload file (flow or pipeline context)
		register_rest_route(
			'datamachine/v1',
			'/files',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'handle_upload' ),
				'permission_callback' => array( self::class, 'check_permission' ),
				'args'                => array(
					'flow_step_id' => array(
						'required'          => false,
						'type'              => 'string',
						'description'       => __( 'Flow step ID for flow-level files', 'data-machine' ),
						'sanitize_callback' => function ( $param ) {
							return sanitize_text_field( $param );
						},
					),
					'pipeline_id'  => array(
						'required'          => false,
						'type'              => 'integer',
						'description'       => __( 'Pipeline ID for pipeline context files', 'data-machine' ),
						'sanitize_callback' => function ( $param ) {
							return absint( $param );
						},
					),
				),
			)
		);

		// GET /files - List files (flow or pipeline context)
		register_rest_route(
			'datamachine/v1',
			'/files',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'list_files' ),
				'permission_callback' => array( self::class, 'check_permission' ),
				'args'                => array(
					'flow_step_id' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => function ( $param ) {
							return sanitize_text_field( $param );
						},
					),
					'pipeline_id'  => array(
						'required'          => false,
						'type'              => 'integer',
						'sanitize_callback' => function ( $param ) {
							return absint( $param );
						},
					),
				),
			)
		);

		// DELETE /files/{filename} - Delete file (flow or pipeline context)
		register_rest_route(
			'datamachine/v1',
			'/files/(?P<filename>[^/]+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( self::class, 'delete_file' ),
				'permission_callback' => array( self::class, 'check_permission' ),
				'args'                => array(
					'filename'     => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => function ( $param ) {
							return sanitize_file_name( $param );
						},
					),
					'flow_step_id' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => function ( $param ) {
							return sanitize_text_field( $param );
						},
					),
					'pipeline_id'  => array(
						'required'          => false,
						'type'              => 'integer',
						'sanitize_callback' => function ( $param ) {
							return absint( $param );
						},
					),
				),
			)
		);
	}

	/**
	 * Check user permission
	 */
	public static function check_permission( $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to manage files.', 'data-machine' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * List files for flow or pipeline context
	 */
	public static function list_files( WP_REST_Request $request ) {
		$flow_step_id = $request->get_param( 'flow_step_id' );
		$pipeline_id  = $request->get_param( 'pipeline_id' );

		$file_storage = new \DataMachine\Core\FilesRepository\FileStorage();

		// Pipeline context files
		if ( $pipeline_id ) {
			$pipeline_id  = absint( $pipeline_id );
			$db_pipelines = new \DataMachine\Core\Database\Pipelines\Pipelines();
			$pipeline     = $db_pipelines->get_pipeline( $pipeline_id );
			if ( ! $pipeline ) {
				return new WP_Error(
					'pipeline_not_found',
					__( 'Pipeline not found.', 'data-machine' ),
					array( 'status' => 404 )
				);
			}

			$pipeline_name = sanitize_text_field( $pipeline['pipeline_name'] ?? '' );
			if ( $pipeline_name === '' ) {
				return new WP_Error(
					'invalid_pipeline_name',
					__( 'Invalid pipeline name.', 'data-machine' ),
					array( 'status' => 400 )
				);
			}

			$files = $file_storage->get_pipeline_files( $pipeline_id, $pipeline_name );

			return rest_ensure_response(
				array(
					'success' => true,
					'data'    => array_map( array( self::class, 'sanitize_file_entry' ), $files ),
				)
			);
		}

		// Flow step files
		if ( $flow_step_id ) {
			$flow_step = apply_filters( 'datamachine_get_flow_step_config', array(), $flow_step_id );
			if ( empty( $flow_step ) ) {
				return new WP_Error(
					'flow_step_not_found',
					__( 'Flow step not found.', 'data-machine' ),
					array( 'status' => 404 )
				);
			}

			$flow_id     = apply_filters( 'datamachine_get_flow_id_from_step', null, $flow_step_id );
			$flow        = apply_filters( 'datamachine_get_flow_config', array(), $flow_id );
			$pipeline_id = $flow['pipeline_id'] ?? null;

			if ( ! $pipeline_id || ! $flow_id ) {
				return new WP_Error(
					'invalid_flow_config',
					__( 'Invalid flow configuration.', 'data-machine' ),
					array( 'status' => 400 )
				);
			}

			$files = $file_storage->get_all_files(
				array(
					'pipeline_id' => $pipeline_id,
					'flow_id'     => $flow_id,
				)
			);

			return rest_ensure_response(
				array(
					'success' => true,
					'data'    => array_map( array( self::class, 'sanitize_file_entry' ), $files ),
				)
			);
		}

		return new WP_Error(
			'missing_scope',
			__( 'Must provide either flow_step_id or pipeline_id.', 'data-machine' ),
			array( 'status' => 400 )
		);
	}

	/**
	 * Handle file upload for flow files or pipeline context
	 */
	public static function handle_upload( WP_REST_Request $request ) {
		$flow_step_id = $request->get_param( 'flow_step_id' );
		$pipeline_id  = $request->get_param( 'pipeline_id' );

		// Validate: one or the other required
		if ( ! $flow_step_id && ! $pipeline_id ) {
			return new WP_Error(
				'missing_scope',
				__( 'Must provide either flow_step_id or pipeline_id.', 'data-machine' ),
				array( 'status' => 400 )
			);
		}

		if ( $flow_step_id && $pipeline_id ) {
			return new WP_Error(
				'conflicting_scope',
				__( 'Cannot provide both flow_step_id and pipeline_id.', 'data-machine' ),
				array( 'status' => 400 )
			);
		}

		$files = $request->get_file_params();
		if ( empty( $files['file'] ) ) {
			return new WP_Error(
				'missing_file',
				__( 'File upload is required.', 'data-machine' ),
				array( 'status' => 400 )
			);
		}

		$uploaded = $files['file'];
		if ( ! is_array( $uploaded ) ) {
			return new WP_Error(
				'invalid_file_structure',
				__( 'Invalid file upload payload.', 'data-machine' ),
				array( 'status' => 400 )
			);
		}

		$upload_error = intval( $uploaded['error'] ?? UPLOAD_ERR_NO_FILE );
		if ( $upload_error !== UPLOAD_ERR_OK ) {
			return new WP_Error(
				'upload_failed',
				__( 'File upload failed.', 'data-machine' ),
				array( 'status' => 400 )
			);
		}

		$tmp_name = $uploaded['tmp_name'] ?? '';
		if ( empty( $tmp_name ) || ! is_uploaded_file( $tmp_name ) ) {
			return new WP_Error(
				'invalid_tmp_name',
				__( 'Invalid temporary file.', 'data-machine' ),
				array( 'status' => 400 )
			);
		}

		$file_name = sanitize_file_name( $uploaded['name'] ?? '' );
		$file_type = sanitize_mime_type( $uploaded['type'] ?? '' );

		if ( $file_name === '' ) {
			return new WP_Error(
				'invalid_file_name',
				__( 'Invalid file name.', 'data-machine' ),
				array( 'status' => 400 )
			);
		}

		$file = array(
			'name'     => $file_name,
			'type'     => $file_type,
			'tmp_name' => $tmp_name,
			'error'    => $upload_error,
			'size'     => intval( $uploaded['size'] ?? 0 ),
		);

		try {
			self::validate_file_with_wordpress( $file );
		} catch ( \Exception $e ) {
			return new WP_Error(
				'file_validation_failed',
				$e->getMessage(),
				array( 'status' => 400 )
			);
		}

		$storage = new \DataMachine\Core\FilesRepository\FileStorage();

		// PIPELINE CONTEXT
		if ( $pipeline_id ) {
			$pipeline_id    = absint( $pipeline_id );
			$db_pipelines   = new \DataMachine\Core\Database\Pipelines\Pipelines();
			$context_files  = $db_pipelines->get_pipeline_context_files( $pipeline_id );
			$uploaded_files = $context_files['uploaded_files'] ?? array();

			return rest_ensure_response(
				array(
					'success' => true,
					'data'    => array(
						'files' => is_array( $uploaded_files ) ? array_map( array( self::class, 'sanitize_file_entry' ), $uploaded_files ) : array(),
						'scope' => 'pipeline',
					),
				)
			);
		}

		// FLOW FILES
		if ( $flow_step_id ) {
			$parts = apply_filters( 'datamachine_split_flow_step_id', null, $flow_step_id );
			if ( ! $parts || empty( $parts['flow_id'] ) ) {
				return new WP_Error(
					'invalid_flow_step_id',
					__( 'Invalid flow step ID format.', 'data-machine' ),
					array( 'status' => 400 )
				);
			}

			$context = self::get_file_context( (int) $parts['flow_id'] );

			$stored = $storage ? $storage->store_file( $file['tmp_name'], $file['name'], $context ) : false;

			if ( ! $stored ) {
				return new WP_Error(
					'file_store_failed',
					__( 'Failed to store file.', 'data-machine' ),
					array( 'status' => 500 )
				);
			}

			$files = $storage->get_all_files( $context );

			return rest_ensure_response(
				array(
					'success' => true,
					'data'    => array(
						'files' => array_map( array( self::class, 'sanitize_file_entry' ), $files ),
						'scope' => 'flow',
					),
				)
			);
		}

		return new WP_Error(
			'invalid_request',
			__( 'Invalid file upload request.', 'data-machine' ),
			array( 'status' => 400 )
		);
	}

	/**
	 * Delete file (flow or pipeline context)
	 */
	public static function delete_file( WP_REST_Request $request ) {
		$filename     = sanitize_file_name( wp_unslash( $request['filename'] ) );
		$flow_step_id = $request->get_param( 'flow_step_id' );
		$pipeline_id  = $request->get_param( 'pipeline_id' );

		if ( ! $flow_step_id && ! $pipeline_id ) {
			return new WP_Error(
				'missing_scope',
				__( 'Must provide either flow_step_id or pipeline_id.', 'data-machine' ),
				array( 'status' => 400 )
			);
		}

		$storage = new \DataMachine\Core\FilesRepository\FileStorage();

		// PIPELINE CONTEXT
		if ( $pipeline_id ) {
			$pipeline_id    = absint( $pipeline_id );
			$db_pipelines   = new \DataMachine\Core\Database\Pipelines\Pipelines();
			$context_files  = $db_pipelines->get_pipeline_context_files( $pipeline_id );
			$uploaded_files = $context_files['uploaded_files'] ?? array();

			foreach ( $uploaded_files as $index => $file ) {
				if ( ( $file['original_name'] ?? '' ) === $filename ) {
					$persistent_path = $file['persistent_path'] ?? '';
					if ( $persistent_path && file_exists( $persistent_path ) ) {
						wp_delete_file( $persistent_path );
					}
					unset( $uploaded_files[ $index ] );
					break;
				}
			}

			$context_files['uploaded_files'] = array_values( $uploaded_files );
			$db_pipelines->update_pipeline_context_files( $pipeline_id, $context_files );

			return rest_ensure_response(
				array(
					'success' => true,
					'data'    => array(
						'scope' => 'pipeline',
					),
				)
			);
		}

		// FLOW CONTEXT
		if ( $flow_step_id ) {
			$parts = apply_filters( 'datamachine_split_flow_step_id', null, $flow_step_id );
			if ( ! $parts || empty( $parts['flow_id'] ) ) {
				return new WP_Error(
					'invalid_flow_step_id',
					__( 'Invalid flow step ID format.', 'data-machine' ),
					array( 'status' => 400 )
				);
			}

			$context = self::get_file_context( (int) $parts['flow_id'] );
			$deleted = $storage ? $storage->delete_file( $filename, $context ) : false;

			return rest_ensure_response(
				array(
					'success' => (bool) $deleted,
					'data'    => array(
						'scope' => 'flow',
					),
				)
			);
		}

		return new WP_Error(
			'invalid_request',
			__( 'Invalid file delete request.', 'data-machine' ),
			array( 'status' => 400 )
		);
	}

	/**
	 * Get file context array from flow ID.
	 *
	 * Supports both database flows (numeric ID) and direct execution ('direct').
	 *
	 * @param int|string $flow_id Flow ID or 'direct' for ephemeral workflows
	 * @return array Context array with pipeline_id and flow_id
	 */
	public static function get_file_context( int|string $flow_id ): array {
		// Direct execution mode - no database lookup needed
		if ( $flow_id === 'direct' ) {
			return array(
				'pipeline_id' => 'direct',
				'flow_id'     => 'direct',
			);
		}

		$db_flows  = new \DataMachine\Core\Database\Flows\Flows();
		$flow_data = $db_flows->get_flow( (int) $flow_id );

		if ( ! isset( $flow_data['pipeline_id'] ) || empty( $flow_data['pipeline_id'] ) ) {
			throw new \InvalidArgumentException( 'Flow data missing required pipeline_id' );
		}

		$pipeline_id = $flow_data['pipeline_id'];

		return array(
			'pipeline_id' => $pipeline_id,
			'flow_id'     => $flow_id,
		);
	}

	/**
	 * Normalize and escape file response entry.
	 */
	private static function sanitize_file_entry( array $file ): array {
		$sanitized = $file;
		if ( isset( $sanitized['filename'] ) ) {
			$sanitized['filename'] = sanitize_file_name( $sanitized['filename'] );
		}
		if ( isset( $sanitized['original_name'] ) ) {
			$sanitized['original_name'] = sanitize_file_name( $sanitized['original_name'] );
		}
		if ( isset( $sanitized['url'] ) ) {
			$sanitized['url'] = esc_url_raw( $sanitized['url'] );
		}
		if ( isset( $sanitized['size'] ) ) {
			$sanitized['size'] = esc_html( size_format( (int) $sanitized['size'] ) );
		}
		return $sanitized;
	}

	/**
	 * Validate uploaded file using WordPress native security functions
	 *
	 * @throws \Exception When validation fails
	 */
	private static function validate_file_with_wordpress( array $file ): void {
		$file_size = filesize( $file['tmp_name'] );
		if ( $file_size === false ) {
			throw new \Exception( esc_html__( 'Cannot determine file size.', 'data-machine' ) );
		}

		$max_file_size = wp_max_upload_size();
		if ( $file_size > $max_file_size ) {
			throw new \Exception(
				sprintf(
				/* translators: %1$s: Current file size, %2$s: Maximum allowed file size */
					esc_html__( 'File too large: %1$s. Maximum allowed size: %2$s', 'data-machine' ),
					esc_html( size_format( $file_size ) ),
					esc_html( size_format( $max_file_size ) )
				)
			);
		}

		// Use WordPress's native file type validation
		$wp_filetype = wp_check_filetype( $file['name'] );
		if ( ! $wp_filetype['type'] ) {
			throw new \Exception( esc_html__( 'File type not allowed.', 'data-machine' ) );
		}

		// Additional path traversal protection (WordPress style)
		$filename = sanitize_file_name( $file['name'] );
		if ( $filename !== $file['name'] ) {
			throw new \Exception( esc_html__( 'Invalid file name detected.', 'data-machine' ) );
		}

		// Check for path traversal attempts
		if ( strpos( $file['name'], '..' ) !== false || strpos( $file['name'], '/' ) !== false || strpos( $file['name'], '\\' ) !== false ) {
			throw new \Exception( esc_html__( 'Invalid file name detected.', 'data-machine' ) );
		}

		// Verify MIME type matches file extension (additional security layer)
		$finfo = finfo_open( FILEINFO_MIME_TYPE );
		if ( $finfo ) {
			$detected_mime = finfo_file( $finfo, $file['tmp_name'] );
			finfo_close( $finfo );

			if ( $detected_mime && $wp_filetype['type'] && $detected_mime !== $wp_filetype['type'] ) {
				// Allow some common variations but block obvious mismatches
				$allowed_mime_variations = array(
					'text/plain'               => array( 'text/csv', 'text/tab-separated-values' ),
					'application/octet-stream' => array( 'application/zip', 'application/x-zip-compressed' ),
				);

				$is_allowed_variation = isset( $allowed_mime_variations[ $wp_filetype['type'] ] ) &&
										in_array( $detected_mime, $allowed_mime_variations[ $wp_filetype['type'] ], true );

				if ( ! $is_allowed_variation ) {
					throw new \Exception( esc_html__( 'File content does not match file type.', 'data-machine' ) );
				}
			}
		}
	}
}
