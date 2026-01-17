<?php
/**
 * Flows Command Tests
 *
 * Tests for flows CLI command.
 *
 * @package DataMachine\Tests\Unit\Cli
 */

namespace DataMachine\Tests\Unit\Cli;

use DataMachine\Cli\Commands\FlowsCommand;
use WP_UnitTestCase;

class FlowsCommandTest extends WP_UnitTestCase {

	private int $test_pipeline_id;
	private int $test_flow_id;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {
		parent::set_up();

		$user_id = self::factory()->user->create(['role' => 'administrator']);
		wp_set_current_user($user_id);

		$pipeline_manager = new \DataMachine\Services\PipelineManager();
		$flow_manager = new \DataMachine\Services\FlowManager();

		$pipeline = $pipeline_manager->create('Test Pipeline for CLI');
		$this->test_pipeline_id = $pipeline['pipeline_id'];

		$flow = $flow_manager->create($this->test_pipeline_id, 'Test Flow for CLI');
		$this->test_flow_id = $flow['flow_id'];
	}

	/**
	 * Tear down test fixtures.
	 */
	public function tear_down(): void {
		parent::tear_down();
	}

	public function test_command_registered(): void {
		$command = \WP_CLI::get_runner()->find_command('datamachine flows');

		$this->assertNotNull($command);
		$this->assertSame(FlowsCommand::class, $command['callable']);
	}

	public function test_list_all_flows(): void {
		ob_start();
		$command = new FlowsCommand();
		$command->__invoke([], []);
		$output = ob_get_clean();

		$this->assertStringContainsString('Flow ID', $output);
		$this->assertStringContainsString('Flow Name', $output);
		$this->assertStringContainsString('Pipeline ID', $output);
	}

	public function test_list_by_pipeline(): void {
		ob_start();
		$command = new FlowsCommand();
		$command->__invoke([$this->test_pipeline_id], []);
		$output = ob_get_clean();

		$this->assertStringContainsString((string)$this->test_pipeline_id, $output);
		$this->assertStringContainsString('Test Flow for CLI', $output);
	}

	public function test_list_subcommand_form(): void {
		ob_start();
		$command = new FlowsCommand();
		$command->__invoke(['list'], []);
		$output = ob_get_clean();

		$this->assertStringContainsString('Flow ID', $output);
	}

	public function test_json_format(): void {
		ob_start();
		$command = new FlowsCommand();
		$command->__invoke([], ['format' => 'json']);
		$output = ob_get_clean();

		$this->assertStringContainsString('"flows"', $output);
		$this->assertStringContainsString('"total"', $output);
		$this->assertStringContainsString('"per_page"', $output);
		$this->assertStringContainsString('"offset"', $output);
	}

	public function test_pagination(): void {
		ob_start();
		$command = new FlowsCommand();
		$command->__invoke([], ['per_page' => '1', 'offset' => '0']);
		$output = ob_get_clean();

		$this->assertStringContainsString('Showing 0 - 1', $output);
	}

	public function test_error_handling(): void {
		wp_set_current_user(0);

		ob_start();
		$command = new FlowsCommand();
		$command->__invoke([], []);
		$output = ob_get_clean();

		$this->assertStringContainsString('Error:', $output);
	}

	public function test_per_page_bounds(): void {
		$command = new FlowsCommand();

		ob_start();
		$command->__invoke([], ['per_page' => '150']);
		$output1 = ob_get_clean();

		$this->assertStringContainsString('Showing 0 -', $output1);

		ob_start();
		$command->__invoke([], ['per_page' => '0']);
		$output2 = ob_get_clean();

		$this->assertStringContainsString('Showing 0 -', $output2);
	}
}
