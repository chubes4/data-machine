<?php
/**
 * WP-CLI Agent Command
 *
 * Provides CLI access to the Data Machine chat agent with the same
 * tools and capabilities as the web chat interface.
 *
 * @package DataMachine\Cli\Commands
 * @since 0.11.0
 */

namespace DataMachine\Cli\Commands;

use WP_CLI;
use WP_CLI_Command;
use DataMachine\Engine\AI\AgentType;
use DataMachine\Engine\AI\AgentContext;
use DataMachine\Engine\AI\AIConversationLoop;
use DataMachine\Engine\AI\Tools\ToolManager;
use DataMachine\Core\Database\Chat\Chat as ChatDatabase;
use DataMachine\Core\PluginSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interact with the Data Machine AI agent from the command line.
 *
 * ## EXAMPLES
 *
 *     # Send a message to the agent
 *     wp datamachine agent "list my pipelines"
 *
 *     # Continue an existing session
 *     wp datamachine agent "what did I just ask?" --session=abc-123
 *
 *     # Use a specific provider and model
 *     wp datamachine agent "create a new pipeline" --provider=anthropic --model=claude-sonnet-4-20250514
 *
 *     # Output raw markdown instead of JSON
 *     wp datamachine agent "hello" --format=text
 */
class AgentCommand extends WP_CLI_Command {

	/**
	 * Send a message to the Data Machine AI agent.
	 *
	 * ## OPTIONS
	 *
	 * <message>
	 * : The message to send to the agent.
	 *
	 * [--session=<uuid>]
	 * : Session ID to continue an existing conversation.
	 *
	 * [--provider=<id>]
	 * : AI provider to use (anthropic, openai, google, etc). Defaults to site settings.
	 *
	 * [--model=<id>]
	 * : AI model to use. Defaults to site settings.
	 *
	 * [--selected-pipeline-id=<id>]
	 * : Pipeline ID for prioritized context.
	 *
	 * [--max-turns=<n>]
	 * : Maximum conversation turns. Default 12.
	 *
	 * [--format=<format>]
	 * : Output format: json (default) or text (raw markdown).
	 * ---
	 * default: json
	 * options:
	 *   - json
	 *   - text
	 * ---
	 *
	 * [--verbose]
	 * : Show detailed tool execution information.
	 *
	 * ## EXAMPLES
	 *
	 *     wp datamachine agent "list my pipelines"
	 *     wp datamachine agent "create a flow for RSS to WordPress" --format=text
	 *     wp datamachine agent "what pipelines exist?" --session=abc-123
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$message = $args[0] ?? '';

		if ( empty( $message ) ) {
			WP_CLI::error( 'Message is required.' );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			WP_CLI::error( 'You must have manage_options capability to use this command.' );
		}

		AgentContext::set( AgentType::CLI );

		try {
			$result = $this->execute_agent( $message, $assoc_args );
			$this->output_result( $result, $assoc_args );
		} catch ( \Exception $e ) {
			WP_CLI::error( $e->getMessage() );
		} finally {
			AgentContext::clear();
		}
	}

	/**
	 * Execute the agent conversation loop.
	 *
	 * @param string $message    User message.
	 * @param array  $assoc_args Command arguments.
	 * @return array Result data.
	 */
	private function execute_agent( string $message, array $assoc_args ): array {
		$user_id              = get_current_user_id();
		$session_id           = $assoc_args['session'] ?? null;
		$provider             = $assoc_args['provider'] ?? PluginSettings::get( 'default_provider', 'anthropic' );
		$model                = $assoc_args['model'] ?? PluginSettings::get( 'default_model', 'claude-sonnet-4-20250514' );
		$max_turns            = (int) ( $assoc_args['max-turns'] ?? 12 );
		$selected_pipeline_id = $assoc_args['selected-pipeline-id'] ?? null;
		$verbose              = isset( $assoc_args['verbose'] );

		$chat_db        = new ChatDatabase();
		$messages       = array();
		$is_new_session = false;

		if ( $session_id ) {
			$session = $chat_db->get_session( $session_id );
			if ( ! $session ) {
				throw new \Exception( "Session not found: {$session_id}" );
			}
			if ( (int) $session['user_id'] !== $user_id ) {
				throw new \Exception( 'You do not have access to this session.' );
			}
			$messages = $session['messages'] ?? array();

			if ( $verbose ) {
				WP_CLI::log( "Continuing session: {$session_id}" );
				WP_CLI::log( 'Messages in history: ' . count( $messages ) );
			}
		} else {
			$session_id = $chat_db->create_session(
				$user_id,
				array(
					'started_at' => current_time( 'mysql', true ),
					'source'     => 'cli',
				),
				AgentType::CLI
			);

			if ( empty( $session_id ) ) {
				throw new \Exception( 'Failed to create session.' );
			}

			$is_new_session = true;

			if ( $verbose ) {
				WP_CLI::log( "Created new session: {$session_id}" );
			}
		}

		$messages[] = $this->build_user_message( $message );

		$chat_db->update_session(
			$session_id,
			$messages,
			array(
				'status'        => 'processing',
				'last_activity' => current_time( 'mysql', true ),
			),
			$provider,
			$model
		);

		if ( $verbose ) {
			WP_CLI::log( "Provider: {$provider}" );
			WP_CLI::log( "Model: {$model}" );
			WP_CLI::log( 'Loading tools...' );
		}

		$tool_manager = new ToolManager();
		$tools        = $tool_manager->getAvailableToolsForChat();

		if ( $verbose ) {
			WP_CLI::log( 'Available tools: ' . count( $tools ) );
		}

		$payload = array(
			'session_id' => $session_id,
		);

		if ( $selected_pipeline_id ) {
			$payload['selected_pipeline_id'] = (int) $selected_pipeline_id;
		}

		if ( $verbose ) {
			WP_CLI::log( "Executing conversation loop (max turns: {$max_turns})..." );
		}

		$loop   = new AIConversationLoop();
		$result = $loop->execute(
			$messages,
			$tools,
			$provider,
			$model,
			AgentType::CLI,
			$payload,
			$max_turns
		);

		if ( isset( $result['error'] ) ) {
			$chat_db->update_session(
				$session_id,
				$result['messages'] ?? $messages,
				array(
					'status'        => 'error',
					'error_message' => $result['error'],
					'last_activity' => current_time( 'mysql', true ),
				),
				$provider,
				$model
			);

			throw new \Exception( $result['error'] );
		}

		$final_messages = $result['messages'] ?? $messages;

		$chat_db->update_session(
			$session_id,
			$final_messages,
			array(
				'status'        => 'completed',
				'message_count' => count( $final_messages ),
				'last_activity' => current_time( 'mysql', true ),
				'turn_count'    => $result['turn_count'] ?? 0,
			),
			$provider,
			$model
		);

		if ( $verbose && ! empty( $result['tool_execution_results'] ) ) {
			WP_CLI::log( "\n--- Tool Executions ---" );
			foreach ( $result['tool_execution_results'] as $tool_result ) {
				$tool_name = $tool_result['tool_name'] ?? 'unknown';
				$success   = ( $tool_result['success'] ?? false ) ? 'SUCCESS' : 'FAILED';
				WP_CLI::log( "  [{$success}] {$tool_name}" );
			}
			WP_CLI::log( "--- End Tool Executions ---\n" );
		}

		return array(
			'session_id'     => $session_id,
			'response'       => $result['final_content'] ?? '',
			'completed'      => $result['completed'] ?? true,
			'turn_count'     => $result['turn_count'] ?? 0,
			'tool_calls'     => $result['last_tool_calls'] ?? array(),
			'is_new_session' => $is_new_session,
		);
	}

	/**
	 * Build a user message in the standard format.
	 *
	 * @param string $content Message content.
	 * @return array Message array.
	 */
	private function build_user_message( string $content ): array {
		return array(
			'role'     => 'user',
			'content'  => $content,
			'metadata' => array(
				'timestamp' => gmdate( 'c' ),
				'type'      => 'text',
				'source'    => 'cli',
			),
		);
	}

	/**
	 * Output the result in the requested format.
	 *
	 * @param array $result     Result data.
	 * @param array $assoc_args Command arguments.
	 */
	private function output_result( array $result, array $assoc_args ): void {
		$format = $assoc_args['format'] ?? 'json';

		if ( 'text' === $format ) {
			WP_CLI::log( $result['response'] );
		} else {
			WP_CLI::log( wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
		}
	}
}
