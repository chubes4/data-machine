<?php
/**
 * JobAbilities Tests
 *
 * Tests for job listing, execution, and monitoring abilities.
 *
 * @package DataMachine\Tests\Unit\Abilities
 */

namespace DataMachine\Tests\Unit\Abilities;

use DataMachine\Abilities\JobAbilities;
use DataMachine\Services\FlowManager;
use DataMachine\Services\JobManager;
use DataMachine\Services\PipelineManager;
use WP_UnitTestCase;

class JobAbilitiesTest extends WP_UnitTestCase {

	private JobAbilities $job_abilities;
	private int $test_pipeline_id;
	private int $test_flow_id;
	private int $test_job_id;

	public function set_up(): void {
		parent::set_up();

		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$this->job_abilities = new JobAbilities();

		$pipeline_manager      = new PipelineManager();
		$flow_manager          = new FlowManager();
		$job_manager           = new JobManager();

		$pipeline              = $pipeline_manager->create( 'Test Pipeline for Jobs' );
		$this->test_pipeline_id = $pipeline['pipeline_id'];

		$flow                  = $flow_manager->create( $this->test_pipeline_id, 'Test Flow for Jobs' );
		$this->test_flow_id    = $flow['flow_id'];

		$this->test_job_id     = $job_manager->create( $this->test_flow_id, $this->test_pipeline_id );
	}

	public function tear_down(): void {
		parent::tear_down();
	}

	public function test_get_jobs_ability_registered(): void {
		$ability = wp_get_ability( 'datamachine/get-jobs' );

		$this->assertNotNull( $ability );
		$this->assertSame( 'datamachine/get-jobs', $ability->get_name() );
	}

	public function test_get_job_ability_registered(): void {
		$ability = wp_get_ability( 'datamachine/get-job' );

		$this->assertNotNull( $ability );
		$this->assertSame( 'datamachine/get-job', $ability->get_name() );
	}

	public function test_delete_jobs_ability_registered(): void {
		$ability = wp_get_ability( 'datamachine/delete-jobs' );

		$this->assertNotNull( $ability );
		$this->assertSame( 'datamachine/delete-jobs', $ability->get_name() );
	}

	public function test_run_flow_ability_registered(): void {
		$ability = wp_get_ability( 'datamachine/run-flow' );

		$this->assertNotNull( $ability );
		$this->assertSame( 'datamachine/run-flow', $ability->get_name() );
	}

	public function test_get_flow_health_ability_registered(): void {
		$ability = wp_get_ability( 'datamachine/get-flow-health' );

		$this->assertNotNull( $ability );
		$this->assertSame( 'datamachine/get-flow-health', $ability->get_name() );
	}

	public function test_get_problem_flows_ability_registered(): void {
		$ability = wp_get_ability( 'datamachine/get-problem-flows' );

		$this->assertNotNull( $ability );
		$this->assertSame( 'datamachine/get-problem-flows', $ability->get_name() );
	}

	public function test_get_jobs_returns_job_list(): void {
		$result = $this->job_abilities->executeGetJobs( array() );

		$this->assertIsArray( $result );
		$this->assertTrue( $result['success'] );
		$this->assertArrayHasKey( 'jobs', $result );
		$this->assertArrayHasKey( 'total', $result );
		$this->assertArrayHasKey( 'per_page', $result );
		$this->assertArrayHasKey( 'offset', $result );
		$this->assertArrayHasKey( 'filters_applied', $result );

		$jobs = $result['jobs'];
		$this->assertIsArray( $jobs );
		$this->assertGreaterThan( 0, count( $jobs ) );

		$first_job = $jobs[0];
		$this->assertArrayHasKey( 'job_id', $first_job );
		$this->assertArrayHasKey( 'flow_id', $first_job );
		$this->assertArrayHasKey( 'pipeline_id', $first_job );
		$this->assertArrayHasKey( 'status', $first_job );
		$this->assertArrayHasKey( 'created_at', $first_job );
		$this->assertArrayHasKey( 'created_at_display', $first_job );
	}

	public function test_get_jobs_filters_by_flow_id(): void {
		$result = $this->job_abilities->executeGetJobs(
			array(
				'flow_id' => $this->test_flow_id,
			)
		);

		$this->assertTrue( $result['success'] );
		$this->assertArrayHasKey( 'jobs', $result );
		$this->assertEquals( $this->test_flow_id, $result['filters_applied']['flow_id'] );

		foreach ( $result['jobs'] as $job ) {
			$this->assertEquals( (string) $this->test_flow_id, $job['flow_id'] );
		}
	}

	public function test_get_jobs_filters_by_pipeline_id(): void {
		$result = $this->job_abilities->executeGetJobs(
			array(
				'pipeline_id' => $this->test_pipeline_id,
			)
		);

		$this->assertTrue( $result['success'] );
		$this->assertEquals( $this->test_pipeline_id, $result['filters_applied']['pipeline_id'] );
	}

	public function test_get_jobs_filters_by_status(): void {
		$result = $this->job_abilities->executeGetJobs(
			array(
				'status' => 'pending',
			)
		);

		$this->assertTrue( $result['success'] );
		$this->assertEquals( 'pending', $result['filters_applied']['status'] );

		foreach ( $result['jobs'] as $job ) {
			$this->assertEquals( 'pending', $job['status'] );
		}
	}

	public function test_get_jobs_with_pagination(): void {
		$result1 = $this->job_abilities->executeGetJobs(
			array(
				'per_page' => 1,
				'offset'   => 0,
			)
		);

		$result2 = $this->job_abilities->executeGetJobs(
			array(
				'per_page' => 1,
				'offset'   => 1,
			)
		);

		$this->assertTrue( $result1['success'] );
		$this->assertTrue( $result2['success'] );

		$this->assertEquals( 1, $result1['per_page'] );
		$this->assertEquals( 0, $result1['offset'] );
		$this->assertEquals( 1, $result2['offset'] );
	}

	public function test_get_job_returns_single_job(): void {
		$result = $this->job_abilities->executeGetJob(
			array(
				'job_id' => $this->test_job_id,
			)
		);

		$this->assertTrue( $result['success'] );
		$this->assertArrayHasKey( 'job', $result );

		$job = $result['job'];
		$this->assertEquals( $this->test_job_id, (int) $job['job_id'] );
		$this->assertArrayHasKey( 'status', $job );
		$this->assertArrayHasKey( 'created_at', $job );
		$this->assertArrayHasKey( 'created_at_display', $job );
	}

	public function test_get_job_with_invalid_id_returns_error(): void {
		$result = $this->job_abilities->executeGetJob(
			array(
				'job_id' => 999999,
			)
		);

		$this->assertFalse( $result['success'] );
		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'not found', $result['error'] );
	}

	public function test_get_job_with_zero_id_returns_error(): void {
		$result = $this->job_abilities->executeGetJob(
			array(
				'job_id' => 0,
			)
		);

		$this->assertFalse( $result['success'] );
		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'positive integer', $result['error'] );
	}

	public function test_delete_jobs_with_all_type(): void {
		$job_manager = new JobManager();
		$job_manager->create( $this->test_flow_id, $this->test_pipeline_id );

		$result = $this->job_abilities->executeDeleteJobs(
			array(
				'type' => 'all',
			)
		);

		$this->assertTrue( $result['success'] );
		$this->assertArrayHasKey( 'deleted_count', $result );
		$this->assertArrayHasKey( 'message', $result );
		$this->assertGreaterThanOrEqual( 1, $result['deleted_count'] );
	}

	public function test_delete_jobs_with_failed_type(): void {
		$db_jobs = new \DataMachine\Core\Database\Jobs\Jobs();
		$job_manager = new JobManager();
		$failed_job_id = $job_manager->create( $this->test_flow_id, $this->test_pipeline_id );
		$db_jobs->complete_job( $failed_job_id, 'failed' );

		$result = $this->job_abilities->executeDeleteJobs(
			array(
				'type' => 'failed',
			)
		);

		$this->assertTrue( $result['success'] );
		$this->assertArrayHasKey( 'deleted_count', $result );
	}

	public function test_delete_jobs_with_invalid_type_returns_error(): void {
		$result = $this->job_abilities->executeDeleteJobs(
			array(
				'type' => 'invalid',
			)
		);

		$this->assertFalse( $result['success'] );
		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'all', $result['error'] );
		$this->assertStringContainsString( 'failed', $result['error'] );
	}

	public function test_run_flow_creates_job(): void {
		$result = $this->job_abilities->executeRunFlow(
			array(
				'flow_id' => $this->test_flow_id,
			)
		);

		$this->assertTrue( $result['success'] );
		$this->assertEquals( $this->test_flow_id, $result['flow_id'] );
		$this->assertArrayHasKey( 'flow_name', $result );
		$this->assertEquals( 'immediate', $result['execution_type'] );
		$this->assertArrayHasKey( 'job_id', $result );
		$this->assertArrayHasKey( 'message', $result );
	}

	public function test_run_flow_with_count_creates_multiple_jobs(): void {
		$result = $this->job_abilities->executeRunFlow(
			array(
				'flow_id' => $this->test_flow_id,
				'count'   => 3,
			)
		);

		$this->assertTrue( $result['success'] );
		$this->assertEquals( 3, $result['count'] );
		$this->assertArrayHasKey( 'job_ids', $result );
		$this->assertCount( 3, $result['job_ids'] );
	}

	public function test_run_flow_with_timestamp_schedules_delayed(): void {
		$future_timestamp = time() + 3600;

		$result = $this->job_abilities->executeRunFlow(
			array(
				'flow_id'   => $this->test_flow_id,
				'timestamp' => $future_timestamp,
			)
		);

		$this->assertTrue( $result['success'] );
		$this->assertEquals( 'delayed', $result['execution_type'] );
		$this->assertArrayHasKey( 'job_id', $result );
	}

	public function test_run_flow_with_timestamp_and_count_returns_error(): void {
		$future_timestamp = time() + 3600;

		$result = $this->job_abilities->executeRunFlow(
			array(
				'flow_id'   => $this->test_flow_id,
				'count'     => 2,
				'timestamp' => $future_timestamp,
			)
		);

		$this->assertFalse( $result['success'] );
		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'Cannot schedule multiple runs', $result['error'] );
	}

	public function test_run_flow_with_invalid_flow_id_returns_error(): void {
		$result = $this->job_abilities->executeRunFlow(
			array(
				'flow_id' => 999999,
			)
		);

		$this->assertFalse( $result['success'] );
		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'not found', $result['error'] );
	}

	public function test_run_flow_with_zero_id_returns_error(): void {
		$result = $this->job_abilities->executeRunFlow(
			array(
				'flow_id' => 0,
			)
		);

		$this->assertFalse( $result['success'] );
		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'positive integer', $result['error'] );
	}

	public function test_get_flow_health_returns_metrics(): void {
		$result = $this->job_abilities->executeGetFlowHealth(
			array(
				'flow_id' => $this->test_flow_id,
			)
		);

		$this->assertTrue( $result['success'] );
		$this->assertEquals( $this->test_flow_id, $result['flow_id'] );
		$this->assertArrayHasKey( 'consecutive_failures', $result );
		$this->assertArrayHasKey( 'consecutive_no_items', $result );
		$this->assertArrayHasKey( 'latest_job', $result );
		$this->assertIsInt( $result['consecutive_failures'] );
		$this->assertIsInt( $result['consecutive_no_items'] );
	}

	public function test_get_flow_health_with_invalid_flow_id_returns_error(): void {
		$result = $this->job_abilities->executeGetFlowHealth(
			array(
				'flow_id' => 999999,
			)
		);

		$this->assertFalse( $result['success'] );
		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'not found', $result['error'] );
	}

	public function test_get_flow_health_with_zero_id_returns_error(): void {
		$result = $this->job_abilities->executeGetFlowHealth(
			array(
				'flow_id' => 0,
			)
		);

		$this->assertFalse( $result['success'] );
		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'positive integer', $result['error'] );
	}

	public function test_get_problem_flows_returns_empty_when_no_problems(): void {
		$result = $this->job_abilities->executeGetProblemFlows(
			array(
				'threshold' => 100,
			)
		);

		$this->assertTrue( $result['success'] );
		$this->assertArrayHasKey( 'failing', $result );
		$this->assertArrayHasKey( 'idle', $result );
		$this->assertArrayHasKey( 'count', $result );
		$this->assertArrayHasKey( 'threshold', $result );
		$this->assertArrayHasKey( 'message', $result );
		$this->assertEquals( 0, $result['count'] );
		$this->assertEquals( 100, $result['threshold'] );
	}

	public function test_get_problem_flows_uses_default_threshold(): void {
		$result = $this->job_abilities->executeGetProblemFlows( array() );

		$this->assertTrue( $result['success'] );
		$this->assertArrayHasKey( 'threshold', $result );
		$this->assertGreaterThan( 0, $result['threshold'] );
	}

	public function test_get_problem_flows_detects_failing_flows(): void {
		$db_jobs = new \DataMachine\Core\Database\Jobs\Jobs();
		$job_manager = new JobManager();

		for ( $i = 0; $i < 3; $i++ ) {
			$job_id = $job_manager->create( $this->test_flow_id, $this->test_pipeline_id );
			$db_jobs->complete_job( $job_id, 'failed' );
		}

		delete_transient( "datamachine_flow_health_{$this->test_flow_id}" );

		$result = $this->job_abilities->executeGetProblemFlows(
			array(
				'threshold' => 3,
			)
		);

		$this->assertTrue( $result['success'] );
		$this->assertGreaterThan( 0, $result['count'] );
		$this->assertGreaterThan( 0, count( $result['failing'] ) );
	}

	public function test_permission_callback_denies_unauthenticated(): void {
		wp_set_current_user( 0 );

		$ability = wp_get_ability( 'datamachine/get-jobs' );
		$this->assertNotNull( $ability );

		$result = $ability->execute( array() );

		$this->assertIsArray( $result );
		$this->assertFalse( $result['success'] ?? true );
		$this->assertArrayHasKey( 'error', $result );

		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
	}
}
