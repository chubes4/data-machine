<?php
/**
 * SystemAbilities Tests
 *
 * Tests for system infrastructure abilities.
 *
 * @package DataMachine\Tests\Unit\Abilities
 */

namespace DataMachine\Tests\Unit\Abilities;

use DataMachine\Abilities\SystemAbilities;
use WP_UnitTestCase;

class SystemAbilitiesTest extends WP_UnitTestCase
{

    private SystemAbilities $system_abilities;

    public function set_up(): void
    {
        parent::set_up();

        $user_id = self::factory()->user->create(array( 'role' => 'administrator' ));
        wp_set_current_user($user_id);

        $this->system_abilities = new SystemAbilities();
    }

    public function tear_down(): void
    {
        parent::tear_down();
    }

    public function test_generate_session_title_ability_registered(): void
    {
        $ability = wp_get_ability('datamachine/generate-session-title');

        $this->assertNotNull($ability);
        $this->assertSame('datamachine/generate-session-title', $ability->get_name());
    }

    public function test_generate_session_title_ability_schema(): void
    {
        $ability = wp_get_ability('datamachine/generate-session-title');

        $this->assertNotNull($ability);

        $input_schema = $ability->get_input_schema();
        $this->assertArrayHasKey('properties', $input_schema);
        $this->assertArrayHasKey('session_id', $input_schema['properties']);
        $this->assertArrayHasKey('force', $input_schema['properties']);
        $this->assertContains('session_id', $input_schema['required']);

        $output_schema = $ability->get_output_schema();
        $this->assertArrayHasKey('properties', $output_schema);
        $this->assertArrayHasKey('success', $output_schema['properties']);
        $this->assertArrayHasKey('title', $output_schema['properties']);
        $this->assertArrayHasKey('method', $output_schema['properties']);
    }
}