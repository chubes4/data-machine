<?php
/**
 * PostQueryAbilities Tests
 *
 * Tests for post query abilities registration.
 *
 * @package DataMachine\Tests\Unit\Abilities
 */

namespace DataMachine\Tests\Unit\Abilities;

use DataMachine\Abilities\PostQueryAbilities;
use WP_UnitTestCase;

class PostQueryAbilitiesTest extends WP_UnitTestCase {

	private PostQueryAbilities $post_query_abilities;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {
		parent::set_up();

		$user_id = self::factory()->user->create(['role' => 'administrator']);
		wp_set_current_user($user_id);

		$this->post_query_abilities = new PostQueryAbilities();
	}

	/**
	 * Tear down test fixtures.
	 */
	public function tear_down(): void {
		parent::tear_down();
	}

	public function test_query_posts_by_handler_ability_registered(): void {
		$ability = wp_get_ability('datamachine/query-posts-by-handler');

		$this->assertNotNull($ability);
		$this->assertSame('datamachine/query-posts-by-handler', $ability->get_name());
	}

	public function test_query_posts_by_flow_ability_registered(): void {
		$ability = wp_get_ability('datamachine/query-posts-by-flow');

		$this->assertNotNull($ability);
		$this->assertSame('datamachine/query-posts-by-flow', $ability->get_name());
	}

	public function test_query_posts_by_pipeline_ability_registered(): void {
		$ability = wp_get_ability('datamachine/query-posts-by-pipeline');

		$this->assertNotNull($ability);
		$this->assertSame('datamachine/query-posts-by-pipeline', $ability->get_name());
	}

	public function test_query_by_handler_with_valid_data(): void {
		$post_id = self::factory()->post->create([
			'post_type' => 'post',
			'post_status' => 'publish',
			'post_title' => 'Test Post for Query'
		]);

		update_post_meta($post_id, 'datamachine_handler_slug', 'universal_web_scraper');
		update_post_meta($post_id, 'datamachine_flow_id', 123);
		update_post_meta($post_id, 'datamachine_pipeline_id', 456);

		$result = $this->post_query_abilities->executeByHandler([
			'handler_slug' => 'universal_web_scraper',
			'post_type' => 'post',
			'post_status' => 'publish',
			'per_page' => 20,
			'offset' => 0
		]);

		$this->assertIsArray($result);
		$this->assertArrayHasKey('posts', $result);
		$this->assertArrayHasKey('total', $result);
		$this->assertGreaterThan(0, $result['total']);

		$first_post = $result['posts'][0];
		$this->assertEquals($post_id, $first_post['id']);
		$this->assertEquals('universal_web_scraper', $first_post['handler_slug']);
		$this->assertEquals(123, $first_post['flow_id']);
		$this->assertEquals(456, $first_post['pipeline_id']);
	}

	public function test_query_by_flow_with_valid_data(): void {
		$post_id = self::factory()->post->create([
			'post_type' => 'post',
			'post_status' => 'publish',
			'post_title' => 'Test Post for Flow Query'
		]);

		update_post_meta($post_id, 'datamachine_flow_id', 789);
		update_post_meta($post_id, 'datamachine_handler_slug', 'rss');

		$result = $this->post_query_abilities->executeByFlow([
			'flow_id' => 789,
			'post_type' => 'post',
			'post_status' => 'publish',
			'per_page' => 20,
			'offset' => 0
		]);

		$this->assertIsArray($result);
		$this->assertArrayHasKey('posts', $result);
		$this->assertGreaterThan(0, $result['total']);

		$first_post = $result['posts'][0];
		$this->assertEquals($post_id, $first_post['id']);
		$this->assertEquals(789, $first_post['flow_id']);
	}

	public function test_query_by_pipeline_with_valid_data(): void {
		$post_id = self::factory()->post->create([
			'post_type' => 'post',
			'post_status' => 'publish',
			'post_title' => 'Test Post for Pipeline Query'
		]);

		update_post_meta($post_id, 'datamachine_pipeline_id', 999);
		update_post_meta($post_id, 'datamachine_flow_id', 100);
		update_post_meta($post_id, 'datamachine_handler_slug', 'ics_feed');

		$result = $this->post_query_abilities->executeByPipeline([
			'pipeline_id' => 999,
			'post_type' => 'post',
			'post_status' => 'publish',
			'per_page' => 20,
			'offset' => 0
		]);

		$this->assertIsArray($result);
		$this->assertArrayHasKey('posts', $result);
		$this->assertGreaterThan(0, $result['total']);

		$first_post = $result['posts'][0];
		$this->assertEquals($post_id, $first_post['id']);
		$this->assertEquals(999, $first_post['pipeline_id']);
	}

	public function test_query_by_handler_empty_handler_slug(): void {
		$result = $this->post_query_abilities->executeByHandler([
			'handler_slug' => '',
			'post_type' => 'post',
			'post_status' => 'publish',
			'per_page' => 20,
			'offset' => 0
		]);

		$this->assertIsArray($result);
		$this->assertEquals(0, $result['total']);
		$this->assertArrayHasKey('error', $result);
	}

	public function test_query_by_flow_invalid_flow_id(): void {
		$result = $this->post_query_abilities->executeByFlow([
			'flow_id' => 0,
			'post_type' => 'post',
			'post_status' => 'publish',
			'per_page' => 20,
			'offset' => 0
		]);

		$this->assertIsArray($result);
		$this->assertEquals(0, $result['total']);
		$this->assertArrayHasKey('error', $result);
	}

	public function test_query_by_pipeline_invalid_pipeline_id(): void {
		$result = $this->post_query_abilities->executeByPipeline([
			'pipeline_id' => -1,
			'post_type' => 'post',
			'post_status' => 'publish',
			'per_page' => 20,
			'offset' => 0
		]);

		$this->assertIsArray($result);
		$this->assertEquals(0, $result['total']);
		$this->assertArrayHasKey('error', $result);
	}

	public function test_query_no_results(): void {
		$result = $this->post_query_abilities->executeByHandler([
			'handler_slug' => 'nonexistent_handler',
			'post_type' => 'post',
			'post_status' => 'publish',
			'per_page' => 20,
			'offset' => 0
		]);

		$this->assertIsArray($result);
		$this->assertEquals(0, $result['total']);
		$this->assertCount(0, $result['posts']);
	}

	public function test_query_pagination(): void {
		for ($i = 0; $i < 5; $i++) {
			$post_id = self::factory()->post->create([
				'post_type' => 'post',
				'post_status' => 'publish',
				'post_title' => "Test Post $i"
			]);
			update_post_meta($post_id, 'datamachine_handler_slug', 'rss');
		}

		$result1 = $this->post_query_abilities->executeByHandler([
			'handler_slug' => 'rss',
			'post_type' => 'post',
			'post_status' => 'publish',
			'per_page' => 2,
			'offset' => 0
		]);

		$result2 = $this->post_query_abilities->executeByHandler([
			'handler_slug' => 'rss',
			'post_type' => 'post',
			'post_status' => 'publish',
			'per_page' => 2,
			'offset' => 2
		]);

		$this->assertEquals(2, count($result1['posts']));
		$this->assertEquals(2, count($result2['posts']));
		$this->assertEquals(5, $result1['total']);
	}

	public function test_format_post_result(): void {
		$post_id = self::factory()->post->create([
			'post_type' => 'post',
			'post_status' => 'publish',
			'post_title' => 'Test Post Format'
		]);

		update_post_meta($post_id, 'datamachine_handler_slug', 'universal_web_scraper');
		update_post_meta($post_id, 'datamachine_flow_id', 123);
		update_post_meta($post_id, 'datamachine_pipeline_id', 456);

		$result = $this->post_query_abilities->executeByHandler([
			'handler_slug' => 'universal_web_scraper',
			'post_type' => 'post',
			'post_status' => 'publish',
			'per_page' => 1,
			'offset' => 0
		]);

		$this->assertArrayHasKey('posts', $result);
		$this->assertCount(1, $result['posts']);

		$post = $result['posts'][0];
		$this->assertArrayHasKey('id', $post);
		$this->assertArrayHasKey('title', $post);
		$this->assertArrayHasKey('post_type', $post);
		$this->assertArrayHasKey('post_status', $post);
		$this->assertArrayHasKey('post_date', $post);
		$this->assertArrayHasKey('post_date_gmt', $post);
		$this->assertArrayHasKey('post_modified', $post);
		$this->assertArrayHasKey('handler_slug', $post);
		$this->assertArrayHasKey('flow_id', $post);
		$this->assertArrayHasKey('pipeline_id', $post);
		$this->assertArrayHasKey('post_url', $post);
	}

	public function test_permission_callback_with_cli(): void {
		define('WP_CLI', true);
		$ability = wp_get_ability('datamachine/query-posts-by-handler');

		$this->assertNotNull($ability);

		$callback = $ability->get_permission_callback();
		$this->assertTrue(call_user_func($callback));
	}

	public function test_permission_callback_without_permissions(): void {
		wp_set_current_user(0);

		$ability = wp_get_ability('datamachine/query-posts-by-handler');
		$this->assertNotNull($ability);

		$callback = $ability->get_permission_callback();
		$this->assertFalse(call_user_func($callback));

		$user_id = self::factory()->user->create(['role' => 'administrator']);
		wp_set_current_user($user_id);
	}
}
