<?php
/**
 * Tests for FlowManager service.
 *
 * @package DataMachine\Tests\Unit\Services
 */

namespace DataMachine\Tests\Unit\Services;

use DataMachine\Services\FlowManager;
use DataMachine\Services\PipelineManager;
use WP_UnitTestCase;

class FlowManagerTest extends WP_UnitTestCase {

	private FlowManager $flow_manager;
	private PipelineManager $pipeline_manager;
	private int $test_pipeline_id;

	/**
	 * Set up test fixtures.
	 *
	 * Runs before each test method.
	 */
	public function set_up(): void {
		parent::set_up();

		// Create an admin user and set as current user
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		$this->flow_manager = new FlowManager();
		$this->pipeline_manager = new PipelineManager();

		// Create a test pipeline for flow operations
		$pipeline = $this->pipeline_manager->create( 'Test Pipeline for Flows' );
		$this->test_pipeline_id = $pipeline['pipeline_id'];
	}

	/**
	 * Tear down test fixtures.
	 */
	public function tear_down(): void {
		parent::tear_down();
	}

	public function test_conversation_manager_does_not_truncate_tool_parameters(): void {
		$tool_name = 'api_query';
		$tool_parameters = [
			'endpoint' => '/datamachine/v1/flows/steps/10_39a2031e-2f86-4e0c-b844-5ee925f4028a_63/config',
			'method' => 'PATCH',
		];

		$message = \DataMachine\Engine\AI\ConversationManager::formatToolCallMessage( $tool_name, $tool_parameters, 1 );
		$this->assertIsArray( $message );
		$this->assertSame( 'tool_call', $message['metadata']['type'] ?? null );
		$this->assertSame( $tool_parameters, $message['metadata']['parameters'] ?? null );
		$this->assertStringNotContainsString( '...', $message['content'] );
		$this->assertStringContainsString( $tool_parameters['endpoint'], $message['content'] );
	}

	// -------------------------------------------------------------------------
	// CREATE TESTS
	// -------------------------------------------------------------------------

	/**
	 * Test creating a flow with a valid pipeline.
	 */
	public function test_create_flow_with_valid_pipeline(): void {
		$result = $this->flow_manager->create( $this->test_pipeline_id, 'New Test Flow' );

		$this->assertIsArray( $result, 'create() should return an array on success' );
		$this->assertArrayHasKey( 'flow_id', $result );
		$this->assertArrayHasKey( 'flow_name', $result );
		$this->assertArrayHasKey( 'pipeline_id', $result );
		$this->assertEquals( 'New Test Flow', $result['flow_name'] );
		$this->assertEquals( $this->test_pipeline_id, $result['pipeline_id'] );
		$this->assertGreaterThan( 0, $result['flow_id'] );
	}

	/**
	 * Test creating a flow with custom scheduling configuration.
	 */
	public function test_create_flow_with_scheduling_config(): void {
		$result = $this->flow_manager->create( $this->test_pipeline_id, 'Scheduled Flow', [
			'scheduling_config' => [
				'interval' => 'hourly'
			]
		]);

		$this->assertIsArray( $result );
		$this->assertEquals( 'Scheduled Flow', $result['flow_name'] );

		// Verify the flow has the scheduling config
		$flow = $this->flow_manager->get( $result['flow_id'] );
		$this->assertEquals( 'hourly', $flow['scheduling_config']['interval'] );
	}

	/**
	 * Test creating a flow with empty name defaults to 'Flow'.
	 */
	public function test_create_flow_with_empty_name_uses_default(): void {
		$result = $this->flow_manager->create( $this->test_pipeline_id, '' );

		$this->assertIsArray( $result );
		$this->assertEquals( 'Flow', $result['flow_name'] );
	}

	/**
	 * Test creating a flow for invalid pipeline returns null.
	 */
	public function test_create_flow_for_invalid_pipeline_returns_null(): void {
		$result = $this->flow_manager->create( 999999, 'Invalid Pipeline Flow' );

		$this->assertNull( $result, 'create() should return null for non-existent pipeline' );
	}

	/**
	 * Test creating a flow with zero pipeline ID returns null.
	 */
	public function test_create_flow_with_zero_pipeline_id_returns_null(): void {
		$result = $this->flow_manager->create( 0, 'Zero Pipeline Flow' );

		$this->assertNull( $result, 'create() should return null for zero pipeline_id' );
	}

	/**
	 * Test that non-admin users cannot create flows.
	 */
	public function test_create_flow_without_permission_returns_null(): void {
		$subscriber_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber_id );

		$result = $this->flow_manager->create( $this->test_pipeline_id, 'Unauthorized Flow' );

		$this->assertNull( $result, 'create() should return null when user lacks permissions' );
	}

	// -------------------------------------------------------------------------
	// GET TESTS
	// -------------------------------------------------------------------------

	/**
	 * Test retrieving a flow by ID.
	 */
	public function test_get_flow_returns_data(): void {
		$created = $this->flow_manager->create( $this->test_pipeline_id, 'Flow To Get' );
		$flow_id = $created['flow_id'];

		$result = $this->flow_manager->get( $flow_id );

		$this->assertIsArray( $result );
		$this->assertEquals( $flow_id, $result['flow_id'] );
		$this->assertEquals( 'Flow To Get', $result['flow_name'] );
		$this->assertEquals( $this->test_pipeline_id, $result['pipeline_id'] );
		$this->assertArrayHasKey( 'flow_config', $result );
		$this->assertArrayHasKey( 'scheduling_config', $result );
	}

	/**
	 * Test that getting a non-existent flow returns null.
	 */
	public function test_get_flow_returns_null_for_nonexistent(): void {
		$result = $this->flow_manager->get( 999999 );

		$this->assertNull( $result, 'get() should return null for non-existent flow' );
	}

	/**
	 * Test that flow_config is properly decoded as array.
	 */
	public function test_get_flow_decodes_config_as_array(): void {
		$created = $this->flow_manager->create( $this->test_pipeline_id, 'Config Decode Test' );
		$flow_id = $created['flow_id'];

		$result = $this->flow_manager->get( $flow_id );

		$this->assertIsArray( $result['flow_config'], 'flow_config should be an array' );
		$this->assertIsArray( $result['scheduling_config'], 'scheduling_config should be an array' );
	}

	// -------------------------------------------------------------------------
	// DELETE TESTS
	// -------------------------------------------------------------------------

	/**
	 * Test deleting a flow.
	 */
	public function test_delete_flow(): void {
		$created = $this->flow_manager->create( $this->test_pipeline_id, 'Flow To Delete' );
		$flow_id = $created['flow_id'];

		$success = $this->flow_manager->delete( $flow_id );

		$this->assertTrue( $success, 'delete() should return true on success' );

		// Verify the flow is gone
		$deleted = $this->flow_manager->get( $flow_id );
		$this->assertNull( $deleted, 'Flow should not exist after deletion' );
	}

	/**
	 * Test deleting a non-existent flow returns false.
	 */
	public function test_delete_nonexistent_flow_returns_false(): void {
		$success = $this->flow_manager->delete( 999999 );

		$this->assertFalse( $success, 'delete() should return false for non-existent flow' );
	}

	/**
	 * Test that non-admin users cannot delete flows.
	 */
	public function test_delete_flow_without_permission_returns_false(): void {
		$created = $this->flow_manager->create( $this->test_pipeline_id, 'Permission Delete Test' );
		$flow_id = $created['flow_id'];

		// Switch to subscriber
		$subscriber_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber_id );

		$success = $this->flow_manager->delete( $flow_id );

		$this->assertFalse( $success, 'delete() should return false when user lacks permissions' );
	}

	// -------------------------------------------------------------------------
	// DUPLICATE / COPY TESTS
	// -------------------------------------------------------------------------

	/**
	 * Test duplicating a flow within the same pipeline.
	 */
	public function test_duplicate_flow(): void {
		$original = $this->flow_manager->create( $this->test_pipeline_id, 'Original Flow', [
			'scheduling_config' => [
				'interval' => 'hourly',
			],
		] );
		$original_id = $original['flow_id'];

		$duplicated = $this->flow_manager->duplicate( $original_id );

		$this->assertIsArray( $duplicated );
		$this->assertArrayHasKey( 'new_flow_id', $duplicated );
		$this->assertNotEquals( $original_id, $duplicated['new_flow_id'] );
		$this->assertEquals( $this->test_pipeline_id, $duplicated['target_pipeline_id'] );
		$this->assertStringStartsWith( 'Copy of', $duplicated['flow_name'] );

		$copied_flow = $this->flow_manager->get( $duplicated['new_flow_id'] );
		$this->assertEquals( 'hourly', $copied_flow['scheduling_config']['interval'] );
	}

	/**
	 * Test copying a flow to a different pipeline.
	 */
	public function test_copy_flow_to_different_pipeline(): void {
		// Create another pipeline
		$other_pipeline = $this->pipeline_manager->create( 'Other Pipeline' );
		$other_pipeline_id = $other_pipeline['pipeline_id'];

		// Create flow in original pipeline
		$original = $this->flow_manager->create( $this->test_pipeline_id, 'Flow To Copy' );
		$original_id = $original['flow_id'];

		$result = $this->flow_manager->copyToPipeline( $original_id, $other_pipeline_id, 'Copied Flow' );

		$this->assertTrue( $result['success'] );
		$this->assertEquals( $other_pipeline_id, $result['data']['target_pipeline_id'] );
		$this->assertEquals( 'Copied Flow', $result['data']['flow_name'] );
	}

	/**
	 * Test copying a non-existent flow returns error.
	 */
	public function test_copy_nonexistent_flow_returns_error(): void {
		$result = $this->flow_manager->copyToPipeline( 999999 );

		$this->assertFalse( $result['success'] );
		$this->assertEquals( 'Source flow not found', $result['error'] );
	}

	/**
	 * Test copying to a non-existent pipeline returns error.
	 */
	public function test_copy_to_nonexistent_pipeline_returns_error(): void {
		$original = $this->flow_manager->create( $this->test_pipeline_id, 'Flow To Copy' );

		$result = $this->flow_manager->copyToPipeline( $original['flow_id'], 999999 );

		$this->assertFalse( $result['success'] );
		$this->assertEquals( 'Target pipeline not found', $result['error'] );
	}

	// -------------------------------------------------------------------------
	// SYNC STEPS TESTS
	// -------------------------------------------------------------------------

	/**
	 * Test syncing pipeline steps to a flow.
	 */
	public function test_sync_steps_to_flow(): void {
		$created = $this->flow_manager->create( $this->test_pipeline_id, 'Flow For Sync Test' );
		$flow_id = $created['flow_id'];

		// Define pipeline steps to sync
		$pipeline_step_id = $this->test_pipeline_id . '_step1';
		$steps = [
			[
				'pipeline_step_id' => $pipeline_step_id,
				'step_type' => 'fetch',
				'execution_order' => 1
			]
		];

		$pipeline_config = [
			$pipeline_step_id => [
				'step_type' => 'fetch',
				'execution_order' => 1,
				'enabled_tools' => []
			]
		];

		$success = $this->flow_manager->syncStepsToFlow( $flow_id, $this->test_pipeline_id, $steps, $pipeline_config );

		$this->assertTrue( $success, 'syncStepsToFlow() should return true on success' );

		// Verify the flow config was updated
		$flow = $this->flow_manager->get( $flow_id );
		$this->assertNotEmpty( $flow['flow_config'], 'Flow config should contain synced steps' );
	}

	/**
	 * Test syncing steps to a non-existent flow returns false.
	 */
	public function test_sync_steps_to_nonexistent_flow_returns_false(): void {
		$success = $this->flow_manager->syncStepsToFlow( 999999, $this->test_pipeline_id, [], [] );

		$this->assertFalse( $success, 'syncStepsToFlow() should return false for non-existent flow' );
	}
}
