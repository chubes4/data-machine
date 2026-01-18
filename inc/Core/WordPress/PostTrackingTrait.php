<?php
/**
 * Data Machine Post Tracking Trait
 *
 * Provides post tracking functionality for handlers creating WordPress posts.
 * Stores handler_slug, flow_id, and pipeline_id as post meta.
 *
 * Use by adding `use PostTrackingTrait;` to handler classes,
 * then call $this->storePostTrackingMeta($post_id, $handler_config);
 * after creating/updating posts.
 *
 * @package DataMachine\Core\WordPress
 * @since 0.12.0
 */

namespace DataMachine\Core\WordPress;

defined( 'ABSPATH' ) || exit;

const DATAMACHINE_POST_HANDLER_META_KEY     = '_datamachine_post_handler';
const DATAMACHINE_POST_FLOW_ID_META_KEY     = '_datamachine_post_flow_id';
const DATAMACHINE_POST_PIPELINE_ID_META_KEY = '_datamachine_post_pipeline_id';

trait PostTrackingTrait {

	protected function storePostTrackingMeta( int $post_id, array $handler_config ): void {
		$handler_slug = $handler_config['handler_slug'] ?? '';
		$flow_id      = (int) ( $handler_config['flow_id'] ?? 0 );
		$pipeline_id  = (int) ( $handler_config['pipeline_id'] ?? 0 );

		if ( ! empty( $handler_slug ) ) {
			update_post_meta( $post_id, DATAMACHINE_POST_HANDLER_META_KEY, sanitize_text_field( $handler_slug ) );
		}

		if ( $flow_id > 0 ) {
			update_post_meta( $post_id, DATAMACHINE_POST_FLOW_ID_META_KEY, $flow_id );
		}

		if ( $pipeline_id > 0 ) {
			update_post_meta( $post_id, DATAMACHINE_POST_PIPELINE_ID_META_KEY, $pipeline_id );
		}

		do_action(
			'datamachine_log',
			'debug',
			'Post tracking meta stored',
			array(
				'post_id'      => $post_id,
				'handler_slug' => $handler_slug,
				'flow_id'      => $flow_id,
				'pipeline_id'  => $pipeline_id,
			)
		);
	}
}
