<?php
/**
 * AuthAbilities Tests
 *
 * Tests for authentication-related abilities.
 *
 * @package DataMachine\Tests\Unit\Abilities
 */

namespace DataMachine\Tests\Unit\Abilities;

use DataMachine\Abilities\AuthAbilities;
use WP_UnitTestCase;

class AuthAbilitiesTest extends WP_UnitTestCase {

	private AuthAbilities $auth_abilities;

	public function set_up(): void {
		parent::set_up();

		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$this->auth_abilities = new AuthAbilities();
	}

	public function tear_down(): void {
		parent::tear_down();
	}

	public function test_get_auth_status_ability_registered(): void {
		$ability = wp_get_ability( 'datamachine/get-auth-status' );

		$this->assertNotNull( $ability );
		$this->assertSame( 'datamachine/get-auth-status', $ability->get_name() );
	}

	public function test_disconnect_auth_ability_registered(): void {
		$ability = wp_get_ability( 'datamachine/disconnect-auth' );

		$this->assertNotNull( $ability );
		$this->assertSame( 'datamachine/disconnect-auth', $ability->get_name() );
	}

	public function test_save_auth_config_ability_registered(): void {
		$ability = wp_get_ability( 'datamachine/save-auth-config' );

		$this->assertNotNull( $ability );
		$this->assertSame( 'datamachine/save-auth-config', $ability->get_name() );
	}

	public function test_get_auth_status_returns_error_for_missing_handler_slug(): void {
		$result = $this->auth_abilities->executeGetAuthStatus(
			array( 'handler_slug' => '' )
		);

		$this->assertFalse( $result['success'] );
		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'required', $result['error'] );
	}

	public function test_get_auth_status_returns_error_for_unknown_handler(): void {
		$result = $this->auth_abilities->executeGetAuthStatus(
			array( 'handler_slug' => 'nonexistent_handler' )
		);

		$this->assertFalse( $result['success'] );
		$this->assertArrayHasKey( 'error', $result );
	}

	public function test_disconnect_auth_returns_error_for_missing_handler_slug(): void {
		$result = $this->auth_abilities->executeDisconnectAuth(
			array( 'handler_slug' => '' )
		);

		$this->assertFalse( $result['success'] );
		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'required', $result['error'] );
	}

	public function test_disconnect_auth_returns_error_for_unknown_handler(): void {
		$result = $this->auth_abilities->executeDisconnectAuth(
			array( 'handler_slug' => 'nonexistent_handler' )
		);

		$this->assertFalse( $result['success'] );
		$this->assertArrayHasKey( 'error', $result );
	}

	public function test_save_auth_config_returns_error_for_missing_handler_slug(): void {
		$result = $this->auth_abilities->executeSaveAuthConfig(
			array(
				'handler_slug' => '',
				'config'       => array( 'key' => 'value' ),
			)
		);

		$this->assertFalse( $result['success'] );
		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'required', $result['error'] );
	}

	public function test_save_auth_config_returns_error_for_unknown_handler(): void {
		$result = $this->auth_abilities->executeSaveAuthConfig(
			array(
				'handler_slug' => 'nonexistent_handler',
				'config'       => array( 'key' => 'value' ),
			)
		);

		$this->assertFalse( $result['success'] );
		$this->assertArrayHasKey( 'error', $result );
	}

	public function test_permission_callback(): void {
		wp_set_current_user( 0 );

		$ability = wp_get_ability( 'datamachine/get-auth-status' );
		$this->assertNotNull( $ability );

		$result = $ability->execute(
			array( 'handler_slug' => 'test_handler' )
		);

		$this->assertIsArray( $result );
		$this->assertFalse( $result['success'] ?? true );
		$this->assertArrayHasKey( 'error', $result );

		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
	}
}
