<?php
/**
 * FlowAbilities Tests
 *
 * Tests for flow listing ability.
 *
 * @package DataMachine\Tests\Unit\Abilities
 */

namespace DataMachine\Tests\Unit\Abilities;

use DataMachine\Abilities\FlowAbilities;
use WP_UnitTestCase;

class FlowAbilitiesTest extends WP_UnitTestCase {

	private FlowAbilities $flow_abilities;
	private int $test_pipeline_id;
	private int $test_flow_id;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {
		parent::set_up();

		$user_id = self::factory()->user->create(['role' => 'administrator']);
		wp_set_current_user($user_id);

		$this->flow_abilities = new FlowAbilities();

		$pipeline_manager = new \DataMachine\Services\PipelineManager();
		$flow_manager = new \DataMachine\Services\FlowManager();

		$pipeline = $pipeline_manager->create('Test Pipeline for Abilities');
		$this->test_pipeline_id = $pipeline['pipeline_id'];

		$flow = $flow_manager->create($this->test_pipeline_id, 'Test Flow for Abilities');
		$this->test_flow_id = $flow['flow_id'];
	}

	/**
	 * Tear down test fixtures.
	 */
	public function tear_down(): void {
		parent::tear_down();
	}

	public function test_ability_registered(): void {
		$ability = wp_get_ability('datamachine/list-flows');

		$this->assertNotNull($ability);
		$this->assertSame('datamachine/list-flows', $ability->get_name());
	}

	public function test_list_all_flows(): void {
		$result = $this->flow_abilities->executeAbility([
			'pipeline_id' => null,
			'handler_slug' => null,
			'per_page' => 20,
			'offset' => 0
		]);

		$this->assertIsArray($result);
		$this->assertTrue($result['success']);
		$this->assertArrayHasKey('flows', $result);
		$this->assertArrayHasKey('total', $result);
		$this->assertArrayHasKey('per_page', $result);
		$this->assertArrayHasKey('offset', $result);
		$this->assertArrayHasKey('filters_applied', $result);

		$flows = $result['flows'];
		$this->assertIsArray($flows);
		$this->assertGreaterThan(0, count($flows));

		$first_flow = $flows[0];
		$this->assertArrayHasKey('flow_id', $first_flow);
		$this->assertArrayHasKey('flow_name', $first_flow);
		$this->assertArrayHasKey('pipeline_id', $first_flow);
		$this->assertArrayHasKey('flow_config', $first_flow);
		$this->assertArrayHasKey('scheduling_config', $first_flow);
		$this->assertArrayHasKey('last_run', $first_flow);
		$this->assertArrayHasKey('last_run_status', $first_flow);
		$this->assertArrayHasKey('next_run', $first_flow);
	}

	public function test_list_flows_by_pipeline_id(): void {
		$result = $this->flow_abilities->executeAbility([
			'pipeline_id' => $this->test_pipeline_id,
			'per_page' => 20,
			'offset' => 0
		]);

		$this->assertTrue($result['success']);
		$this->assertArrayHasKey('flows', $result);

		$flows = $result['flows'];
		$this->assertGreaterThan(0, count($flows));

		foreach ($flows as $flow) {
			$this->assertEquals($this->test_pipeline_id, $flow['pipeline_id']);
		}

		$this->assertEquals($this->test_pipeline_id, $result['filters_applied']['pipeline_id']);
	}

	public function test_list_flows_by_handler_slug(): void {
		$flow_manager = new \DataMachine\Services\FlowManager();

		$flow = $flow_manager->create($this->test_pipeline_id, 'RSS Test Flow', [
			'scheduling_config' => ['interval' => 'manual']
		]);

		$result = $this->flow_abilities->executeAbility([
			'pipeline_id' => $this->test_pipeline_id,
			'handler_slug' => 'rss',
			'per_page' => 20,
			'offset' => 0
		]);

		$this->assertTrue($result['success']);

		$flows = $result['flows'];
		$this->assertGreaterThan(0, count($flows));

		$found_test_flow = false;
		foreach ($flows as $flow_data) {
			if ($flow_data['flow_id'] === $flow['flow_id']) {
				$found_test_flow = true;
				break;
			}
		}

		$this->assertTrue($found_test_flow, 'Test flow should be in results when filtered by handler_slug');
		$this->assertEquals('rss', $result['filters_applied']['handler_slug']);
	}

	public function test_handler_slug_any_step_match(): void {
		$flow_manager = new \DataMachine\Services\FlowManager();
		$pipeline_manager = new \DataMachine\Services\PipelineManager();

		$pipeline = $pipeline_manager->create('Multi-Handler Pipeline', [
			'pipeline_config' => [
				'0' => [
					'pipeline_step_id' => 'step1',
					'step_type' => 'fetch',
					'execution_order' => 1
				],
				'1' => [
					'pipeline_step_id' => 'step2',
					'step_type' => 'publish',
					'execution_order' => 2
				]
			]
		]);

		$flow = $flow_manager->create($pipeline['pipeline_id'], 'Multi-Handler Flow', [
			'flow_config' => [
				'step1_' . $flow['flow_id'] => [
					'step_type' => 'fetch',
					'handler_slug' => 'rss',
					'pipeline_step_id' => 'step1'
				],
				'step2_' . $flow['flow_id'] => [
					'step_type' => 'publish',
					'handler_slug' => 'wordpress_publish',
					'pipeline_step_id' => 'step2'
				]
			]
		]);

		$result = $this->flow_abilities->executeAbility([
			'pipeline_id' => $pipeline['pipeline_id'],
			'handler_slug' => 'wordpress_publish',
			'per_page' => 20,
			'offset' => 0
		]);

		$this->assertTrue($result['success']);
		$this->assertGreaterThan(0, count($result['flows']));

		$found_flow = null;
		foreach ($result['flows'] as $flow_data) {
			if ($flow_data['flow_id'] === $flow['flow_id']) {
				$found_flow = $flow_data;
				break;
			}
		}

		$this->assertNotNull($found_flow);
		$this->assertEquals('Multi-Handler Flow', $found_flow['flow_name']);
	}

	public function test_list_flows_with_pagination(): void {
		$result1 = $this->flow_abilities->executeAbility([
			'per_page' => 1,
			'offset' => 0
		]);

		$result2 = $this->flow_abilities->executeAbility([
			'per_page' => 1,
			'offset' => 1
		]);

		$this->assertTrue($result1['success']);
		$this->assertTrue($result2['success']);

		$this->assertEquals(1, count($result1['flows']));
		$this->assertEquals(1, count($result2['flows']));

		$this->assertEquals(0, $result1['offset']);
		$this->assertEquals(1, $result2['offset']);
	}

	public function test_list_flows_with_both_filters(): void {
		$result = $this->flow_abilities->executeAbility([
			'pipeline_id' => $this->test_pipeline_id,
			'handler_slug' => null,
			'per_page' => 20,
			'offset' => 0
		]);

		$this->assertTrue($result['success']);
		$this->assertGreaterThan(0, count($result['flows']));
	}

	public function test_empty_results(): void {
		$result = $this->flow_abilities->executeAbility([
			'pipeline_id' => 999999,
			'per_page' => 20,
			'offset' => 0
		]);

		$this->assertTrue($result['success']);
		$this->assertIsArray($result['flows']);
		$this->assertEquals(0, count($result['flows']));
		$this->assertEquals(0, $result['total']);
	}

	public function test_permission_callback(): void {
		wp_set_current_user(0);

		$ability = wp_get_ability('datamachine/list-flows');
		$this->assertNotNull($ability);

		$result = $ability->execute([
			'pipeline_id' => null,
			'per_page' => 20,
			'offset' => 0
		]);

		$this->assertIsArray($result);
		$this->assertFalse($result['success'] ?? true);
		$this->assertArrayHasKey('error', $result);

		$user_id = self::factory()->user->create(['role' => 'administrator']);
		wp_set_current_user($user_id);
	}

	public function test_ability_not_found(): void {
		$ability = wp_get_ability('datamachine/non-existent-ability');
		$this->assertNull($ability);
	}
}
