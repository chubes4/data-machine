<?php
/**
 * Tests for PipelineManager service.
 *
 * @package DataMachine\Tests\Unit\Services
 */

namespace DataMachine\Tests\Unit\Services;

use DataMachine\Services\PipelineManager;
use WP_UnitTestCase;

class PipelineManagerTest extends WP_UnitTestCase {

	private PipelineManager $manager;

	/**
	 * Set up test fixtures.
	 *
	 * Runs before each test method.
	 */
	public function set_up(): void {
		parent::set_up();

		// Create an admin user and set as current user
		// This is required because PipelineManager checks current_user_can('manage_options')
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		$this->manager = new PipelineManager();
	}

	/**
	 * Tear down test fixtures.
	 *
	 * Runs after each test method.
	 */
	public function tear_down(): void {
		parent::tear_down();
	}

	// -------------------------------------------------------------------------
	// CREATE TESTS
	// -------------------------------------------------------------------------

	/**
	 * Test creating a pipeline with a valid name.
	 *
	 * Verifies:
	 * - Pipeline is created successfully
	 * - Returns array with expected keys
	 * - A default flow is automatically created
	 */
	public function test_create_pipeline_with_valid_name(): void {
		$result = $this->manager->create( 'Test Pipeline' );

		$this->assertIsArray( $result, 'create() should return an array on success' );
		$this->assertArrayHasKey( 'pipeline_id', $result );
		$this->assertArrayHasKey( 'pipeline_name', $result );
		$this->assertArrayHasKey( 'flows', $result );
		$this->assertEquals( 'Test Pipeline', $result['pipeline_name'] );
		$this->assertGreaterThan( 0, $result['pipeline_id'] );

		// Verify a default flow was created
		$this->assertIsArray( $result['flows'] );
		$this->assertCount( 1, $result['flows'], 'A default flow should be created with the pipeline' );
	}

	/**
	 * Test creating a pipeline with flow configuration options.
	 */
	public function test_create_pipeline_with_flow_config(): void {
		$result = $this->manager->create( 'Pipeline With Config', [
			'flow_config' => [
				'flow_name' => 'Custom Flow Name',
				'scheduling_config' => [ 'interval' => 'hourly' ]
			]
		]);

		$this->assertIsArray( $result );
		$this->assertEquals( 'Pipeline With Config', $result['pipeline_name'] );

		// Check that the flow was created with custom name
		$this->assertNotEmpty( $result['flows'] );
		$first_flow = $result['flows'][0];
		$this->assertEquals( 'Custom Flow Name', $first_flow['flow_name'] );
	}

	/**
	 * Test that creating a pipeline with empty name returns null.
	 */
	public function test_create_pipeline_with_empty_name_returns_null(): void {
		$result = $this->manager->create( '' );

		$this->assertNull( $result, 'create() should return null for empty name' );
	}

	/**
	 * Test that creating a pipeline with whitespace-only name returns null.
	 */
	public function test_create_pipeline_with_whitespace_name_returns_null(): void {
		$result = $this->manager->create( '   ' );

		$this->assertNull( $result, 'create() should return null for whitespace-only name' );
	}

	/**
	 * Test that non-admin users cannot create pipelines.
	 */
	public function test_create_pipeline_without_permission_returns_null(): void {
		// Switch to a subscriber (non-admin) user
		$subscriber_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber_id );

		$result = $this->manager->create( 'Unauthorized Pipeline' );

		$this->assertNull( $result, 'create() should return null when user lacks permissions' );
	}

	// -------------------------------------------------------------------------
	// GET TESTS
	// -------------------------------------------------------------------------

	/**
	 * Test retrieving a pipeline by ID.
	 */
	public function test_get_pipeline_returns_data(): void {
		// First create a pipeline
		$created = $this->manager->create( 'Pipeline To Get' );
		$pipeline_id = $created['pipeline_id'];

		// Now retrieve it
		$result = $this->manager->get( $pipeline_id );

		$this->assertIsArray( $result );
		$this->assertEquals( $pipeline_id, $result['pipeline_id'] );
		$this->assertEquals( 'Pipeline To Get', $result['pipeline_name'] );
	}

	/**
	 * Test that getting a non-existent pipeline returns null.
	 */
	public function test_get_pipeline_returns_null_for_nonexistent(): void {
		$result = $this->manager->get( 999999 );

		$this->assertNull( $result, 'get() should return null for non-existent pipeline' );
	}

	/**
	 * Test retrieving a pipeline with its flows.
	 */
	public function test_get_with_flows_returns_pipeline_and_flows(): void {
		// Create a pipeline (which auto-creates a flow)
		$created = $this->manager->create( 'Pipeline With Flows' );
		$pipeline_id = $created['pipeline_id'];

		$result = $this->manager->getWithFlows( $pipeline_id );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'pipeline', $result );
		$this->assertArrayHasKey( 'flows', $result );
		$this->assertEquals( 'Pipeline With Flows', $result['pipeline']['pipeline_name'] );
		$this->assertIsArray( $result['flows'] );
		$this->assertGreaterThanOrEqual( 1, count( $result['flows'] ) );
	}

	// -------------------------------------------------------------------------
	// UPDATE TESTS
	// -------------------------------------------------------------------------

	/**
	 * Test updating a pipeline name.
	 */
	public function test_update_pipeline_name(): void {
		// Create a pipeline
		$created = $this->manager->create( 'Original Name' );
		$pipeline_id = $created['pipeline_id'];

		// Update the name
		$success = $this->manager->update( $pipeline_id, [
			'pipeline_name' => 'Updated Name'
		]);

		$this->assertTrue( $success, 'update() should return true on success' );

		// Verify the update
		$updated = $this->manager->get( $pipeline_id );
		$this->assertEquals( 'Updated Name', $updated['pipeline_name'] );
	}

	/**
	 * Test updating a pipeline config.
	 */
	public function test_update_pipeline_config(): void {
		$created = $this->manager->create( 'Config Test Pipeline' );
		$pipeline_id = $created['pipeline_id'];

		$config = [
			'step_1' => [
				'step_type' => 'fetch',
				'execution_order' => 1
			]
		];

		$success = $this->manager->update( $pipeline_id, [
			'pipeline_config' => $config
		]);

		$this->assertTrue( $success );

		$updated = $this->manager->get( $pipeline_id );
		$this->assertIsArray( $updated['pipeline_config'] );
		$this->assertArrayHasKey( 'step_1', $updated['pipeline_config'] );
	}

	/**
	 * Test that updating with no valid data returns false.
	 */
	public function test_update_pipeline_with_empty_data_returns_false(): void {
		$created = $this->manager->create( 'Empty Update Test' );
		$pipeline_id = $created['pipeline_id'];

		$success = $this->manager->update( $pipeline_id, [] );

		$this->assertFalse( $success, 'update() should return false when no valid data provided' );
	}

	/**
	 * Test that non-admin users cannot update pipelines.
	 */
	public function test_update_pipeline_without_permission_returns_false(): void {
		// Create pipeline as admin
		$created = $this->manager->create( 'Permission Test Pipeline' );
		$pipeline_id = $created['pipeline_id'];

		// Switch to subscriber
		$subscriber_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber_id );

		$success = $this->manager->update( $pipeline_id, [
			'pipeline_name' => 'Unauthorized Update'
		]);

		$this->assertFalse( $success, 'update() should return false when user lacks permissions' );
	}

	// -------------------------------------------------------------------------
	// DELETE TESTS
	// -------------------------------------------------------------------------

	/**
	 * Test deleting a pipeline.
	 */
	public function test_delete_pipeline(): void {
		$created = $this->manager->create( 'Pipeline To Delete' );
		$pipeline_id = $created['pipeline_id'];

		$result = $this->manager->delete( $pipeline_id );

		$this->assertIsArray( $result, 'delete() should return array on success' );
		$this->assertArrayHasKey( 'message', $result );
		$this->assertArrayHasKey( 'pipeline_id', $result );
		$this->assertArrayHasKey( 'deleted_flows', $result );
		$this->assertEquals( $pipeline_id, $result['pipeline_id'] );

		// Verify the pipeline is gone
		$deleted = $this->manager->get( $pipeline_id );
		$this->assertNull( $deleted, 'Pipeline should not exist after deletion' );
	}

	/**
	 * Test that deleting a pipeline also deletes its flows.
	 */
	public function test_delete_pipeline_cascades_to_flows(): void {
		$created = $this->manager->create( 'Cascade Delete Test' );
		$pipeline_id = $created['pipeline_id'];
		$flow_id = $created['flows'][0]['flow_id'] ?? null;

		$this->assertNotNull( $flow_id, 'Pipeline should have a flow' );

		$result = $this->manager->delete( $pipeline_id );

		$this->assertIsArray( $result );
		$this->assertGreaterThanOrEqual( 1, $result['deleted_flows'], 'At least one flow should be deleted' );
	}

	/**
	 * Test deleting a non-existent pipeline returns WP_Error.
	 */
	public function test_delete_nonexistent_pipeline_returns_error(): void {
		$result = $this->manager->delete( 999999 );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'pipeline_not_found', $result->get_error_code() );
	}

	/**
	 * Test that non-admin users cannot delete pipelines.
	 */
	public function test_delete_pipeline_without_permission_returns_error(): void {
		// Create pipeline as admin
		$created = $this->manager->create( 'Permission Delete Test' );
		$pipeline_id = $created['pipeline_id'];

		// Switch to subscriber
		$subscriber_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber_id );

		$result = $this->manager->delete( $pipeline_id );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'rest_forbidden', $result->get_error_code() );
	}

	/**
	 * Test deleting with invalid (zero) pipeline ID returns error.
	 */
	public function test_delete_with_zero_id_returns_error(): void {
		$result = $this->manager->delete( 0 );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'invalid_pipeline_id', $result->get_error_code() );
	}
}
