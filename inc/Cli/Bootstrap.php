<?php
/**
 * WP-CLI Bootstrap
 *
 * Registers WP-CLI commands for Data Machine.
 *
 * @package DataMachine\Cli
 * @since 0.11.0
 */

namespace DataMachine\Cli;

use WP_CLI;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

WP_CLI::add_command( 'datamachine agent', Commands\AgentCommand::class );
WP_CLI::add_command( 'datamachine settings', Commands\SettingsCommand::class );
WP_CLI::add_command( 'datamachine flows', Commands\FlowsCommand::class );
WP_CLI::add_command( 'datamachine jobs', Commands\JobsCommand::class );
WP_CLI::add_command( 'datamachine pipelines', Commands\PipelinesCommand::class );
WP_CLI::add_command( 'datamachine posts', Commands\PostsCommand::class );
