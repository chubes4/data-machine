<?php
/**
 * FileAbilities Tests
 *
 * Tests for file listing, retrieval, deletion, and cleanup abilities.
 *
 * @package DataMachine\Tests\Unit\Abilities
 */

namespace DataMachine\Tests\Unit\Abilities;

use DataMachine\Abilities\FileAbilities;
use DataMachine\Core\Database\Jobs\Jobs;
use DataMachine\Core\FilesRepository\FileStorage;
use WP_UnitTestCase;

class FileAbilitiesTest extends WP_UnitTestCase {

	private FileAbilities $file_abilities;
	private int $test_pipeline_id;
	private int $test_flow_id;
	private string $test_flow_step_id;

	public function set_up(): void {
		parent::set_up();

		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$this->file_abilities = new FileAbilities();

		$pipeline_ability       = wp_get_ability( 'datamachine/create-pipeline' );
		$flow_ability           = wp_get_ability( 'datamachine/create-flow' );

		$pipeline               = $pipeline_ability->execute( array( 'pipeline_name' => 'Test Pipeline for Files' ) );
		$this->test_pipeline_id = $pipeline['pipeline_id'];

		$flow                   = $flow_ability->execute( array( 'pipeline_id' => $this->test_pipeline_id, 'flow_name' => 'Test Flow for Files' ) );
		$this->test_flow_id     = $flow['flow_id'];

		$this->test_flow_step_id = $this->test_pipeline_id . '-' . $this->test_flow_id;
	}

	public function tear_down(): void {
		parent::tear_down();
	}

	public function test_list_files_ability_registered(): void {
		$ability = wp_get_ability( 'datamachine/list-files' );

		$this->assertNotNull( $ability );
		$this->assertSame( 'datamachine/list-files', $ability->get_name() );
	}

	public function test_get_file_ability_registered(): void {
		$ability = wp_get_ability( 'datamachine/get-file' );

		$this->assertNotNull( $ability );
		$this->assertSame( 'datamachine/get-file', $ability->get_name() );
	}

	public function test_delete_file_ability_registered(): void {
		$ability = wp_get_ability( 'datamachine/delete-file' );

		$this->assertNotNull( $ability );
		$this->assertSame( 'datamachine/delete-file', $ability->get_name() );
	}

	public function test_cleanup_files_ability_registered(): void {
		$ability = wp_get_ability( 'datamachine/cleanup-files' );

		$this->assertNotNull( $ability );
		$this->assertSame( 'datamachine/cleanup-files', $ability->get_name() );
	}

	public function test_list_files_requires_scope(): void {
		$result = $this->file_abilities->executeListFiles( array() );

		$this->assertFalse( $result['success'] );
		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'Must provide either', $result['error'] );
	}

	public function test_list_files_rejects_both_scopes(): void {
		$result = $this->file_abilities->executeListFiles(
			array(
				'flow_step_id' => $this->test_flow_step_id,
				'pipeline_id'  => $this->test_pipeline_id,
			)
		);

		$this->assertFalse( $result['success'] );
		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'Cannot provide both', $result['error'] );
	}

	public function test_list_files_with_pipeline_id(): void {
		$result = $this->file_abilities->executeListFiles(
			array(
				'pipeline_id' => $this->test_pipeline_id,
			)
		);

		$this->assertTrue( $result['success'] );
		$this->assertArrayHasKey( 'files', $result );
		$this->assertArrayHasKey( 'scope', $result );
		$this->assertEquals( 'pipeline', $result['scope'] );
		$this->assertIsArray( $result['files'] );
	}

	public function test_list_files_with_invalid_pipeline_id(): void {
		$result = $this->file_abilities->executeListFiles(
			array(
				'pipeline_id' => 999999,
			)
		);

		$this->assertFalse( $result['success'] );
		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'not found', $result['error'] );
	}

	public function test_get_file_requires_filename(): void {
		$result = $this->file_abilities->executeGetFile(
			array(
				'pipeline_id' => $this->test_pipeline_id,
			)
		);

		$this->assertFalse( $result['success'] );
		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'filename is required', $result['error'] );
	}

	public function test_get_file_requires_scope(): void {
		$result = $this->file_abilities->executeGetFile(
			array(
				'filename' => 'test.txt',
			)
		);

		$this->assertFalse( $result['success'] );
		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'Must provide either', $result['error'] );
	}

	public function test_get_file_rejects_both_scopes(): void {
		$result = $this->file_abilities->executeGetFile(
			array(
				'filename'     => 'test.txt',
				'flow_step_id' => $this->test_flow_step_id,
				'pipeline_id'  => $this->test_pipeline_id,
			)
		);

		$this->assertFalse( $result['success'] );
		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'Cannot provide both', $result['error'] );
	}

	public function test_get_file_not_found_in_pipeline(): void {
		$result = $this->file_abilities->executeGetFile(
			array(
				'filename'    => 'nonexistent.txt',
				'pipeline_id' => $this->test_pipeline_id,
			)
		);

		$this->assertFalse( $result['success'] );
		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'not found', $result['error'] );
	}

	public function test_delete_file_requires_filename(): void {
		$result = $this->file_abilities->executeDeleteFile(
			array(
				'pipeline_id' => $this->test_pipeline_id,
			)
		);

		$this->assertFalse( $result['success'] );
		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'filename is required', $result['error'] );
	}

	public function test_delete_file_requires_scope(): void {
		$result = $this->file_abilities->executeDeleteFile(
			array(
				'filename' => 'test.txt',
			)
		);

		$this->assertFalse( $result['success'] );
		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'Must provide either', $result['error'] );
	}

	public function test_delete_file_rejects_both_scopes(): void {
		$result = $this->file_abilities->executeDeleteFile(
			array(
				'filename'     => 'test.txt',
				'flow_step_id' => $this->test_flow_step_id,
				'pipeline_id'  => $this->test_pipeline_id,
			)
		);

		$this->assertFalse( $result['success'] );
		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'Cannot provide both', $result['error'] );
	}

	public function test_delete_file_not_found_in_pipeline(): void {
		$result = $this->file_abilities->executeDeleteFile(
			array(
				'filename'    => 'nonexistent.txt',
				'pipeline_id' => $this->test_pipeline_id,
			)
		);

		$this->assertFalse( $result['success'] );
		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'not found', $result['error'] );
	}

	public function test_cleanup_files_requires_scope(): void {
		$result = $this->file_abilities->executeCleanupFiles( array() );

		$this->assertFalse( $result['success'] );
		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'Must provide either', $result['error'] );
	}

	public function test_cleanup_files_job_requires_flow(): void {
		$result = $this->file_abilities->executeCleanupFiles(
			array(
				'job_id' => 1,
			)
		);

		$this->assertFalse( $result['success'] );
		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'flow_id is required', $result['error'] );
	}

	public function test_cleanup_files_with_invalid_flow_id(): void {
		$result = $this->file_abilities->executeCleanupFiles(
			array(
				'flow_id' => 999999,
			)
		);

		$this->assertFalse( $result['success'] );
		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'not found', $result['error'] );
	}

	public function test_cleanup_files_with_valid_flow(): void {
		$result = $this->file_abilities->executeCleanupFiles(
			array(
				'flow_id' => $this->test_flow_id,
			)
		);

		$this->assertTrue( $result['success'] );
		$this->assertArrayHasKey( 'deleted_count', $result );
		$this->assertArrayHasKey( 'message', $result );
		$this->assertIsInt( $result['deleted_count'] );
	}

	public function test_cleanup_files_with_job_and_flow(): void {
		$db_jobs = new Jobs();
		$job_id  = $db_jobs->create_job(
			array(
				'pipeline_id' => $this->test_pipeline_id,
				'flow_id'     => $this->test_flow_id,
			)
		);

		$result = $this->file_abilities->executeCleanupFiles(
			array(
				'job_id'  => $job_id,
				'flow_id' => $this->test_flow_id,
			)
		);

		$this->assertTrue( $result['success'] );
		$this->assertArrayHasKey( 'deleted_count', $result );
		$this->assertArrayHasKey( 'message', $result );
		$this->assertStringContainsString( 'job', $result['message'] );
	}

	public function test_permission_callback_denies_unauthenticated(): void {
		wp_set_current_user( 0 );

		$ability = wp_get_ability( 'datamachine/list-files' );
		$this->assertNotNull( $ability );

		$result = $ability->execute(
			array(
				'pipeline_id' => $this->test_pipeline_id,
			)
		);

		$this->assertIsArray( $result );
		$this->assertFalse( $result['success'] ?? true );
		$this->assertArrayHasKey( 'error', $result );

		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
	}

	public function test_permission_callback_allows_admin(): void {
		$result = $this->file_abilities->checkPermission();
		$this->assertTrue( $result );
	}

	public function test_filename_is_sanitized(): void {
		$result = $this->file_abilities->executeGetFile(
			array(
				'filename'    => '../../../etc/passwd',
				'pipeline_id' => $this->test_pipeline_id,
			)
		);

		$this->assertFalse( $result['success'] );
		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringNotContainsString( '../', $result['error'] );
	}
}
