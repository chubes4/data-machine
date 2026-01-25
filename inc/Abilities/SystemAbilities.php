<?php
/**
 * System Abilities
 *
 * WordPress 6.9 Abilities API primitives for system infrastructure operations.
 * Handles session title generation and other system-level tasks.
 *
 * @package DataMachine\Abilities
 * @since   0.13.7
 */

namespace DataMachine\Abilities;

use DataMachine\Engine\AI\RequestBuilder;
use DataMachine\Engine\AI\AgentType;
use DataMachine\Core\Database\Chat\Chat as ChatDatabase;
use DataMachine\Core\PluginSettings;

defined('ABSPATH') || exit;

class SystemAbilities
{

    private static bool $registered = false;

    public function __construct()
    {
        if (! class_exists('WP_Ability') ) {
            return;
        }

        if (self::$registered ) {
            return;
        }

        $this->registerAbilities();
        self::$registered = true;
    }

    private function registerAbilities(): void
    {
        $register_callback = function () {
            wp_register_ability(
                'datamachine/generate-session-title',
                array(
                'label'               => 'Generate Session Title',
                'description'         => 'Generate an AI-powered title for a chat session based on conversation content',
                'category'            => 'datamachine',
                'input_schema'        => array(
                'type'       => 'object',
                'properties' => array(
                'session_id' => array(
                                'type'        => 'string',
                                'description' => 'UUID of the chat session to generate title for',
                ),
                'force'      => array(
                'type'        => 'boolean',
                'description' => 'Force regeneration even if title already exists',
                'default'     => false,
                ),
                ),
                'required'   => array( 'session_id' ),
                    ),
                    'output_schema'       => array(
                        'type'       => 'object',
                        'properties' => array(
                            'success' => array( 'type' => 'boolean' ),
                            'title'   => array( 'type' => 'string' ),
                            'method'  => array(
                                'type' => 'string',
                                'enum' => array( 'ai', 'fallback' ),
                            ),
                            'message' => array( 'type' => 'string' ),
                            'error'   => array( 'type' => 'string' ),
                    ),
                    ),
                    'execute_callback'    => array( self::class, 'generateSessionTitle' ),
                    'permission_callback' => function () {
                        // Allow internal calls from system hooks, or admin access
                        if (defined('WP_CLI') && WP_CLI ) {
                            return true;
                        }
                        return current_user_can('manage_options');
                    },
                    'meta'                => array( 'show_in_rest' => false ), // Internal system operation
                )
            );
        };

        if (did_action('wp_abilities_api_init') ) {
            $register_callback();
        } else {
            add_action('wp_abilities_api_init', $register_callback);
        }
    }

    public static function generateSessionTitle( array $input ): array
    {
        $session_id = $input['session_id'];
        $force      = $input['force'] ?? false;

        $chat_db = new ChatDatabase();
        $session = $chat_db->get_session($session_id);

        if (! $session ) {
            return array(
            'success' => false,
            'error'   => 'Session not found',
            'message' => 'Unable to find chat session',
            );
        }

        // Check if title already exists and we're not forcing regeneration
        if (! empty($session['title']) && ! $force ) {
            return array(
            'success' => true,
            'title'   => $session['title'],
            'method'  => 'existing',
            'message' => 'Title already exists',
            );
        }

        $messages = $session['messages'] ?? array();
        if (empty($messages) ) {
            return array(
            'success' => false,
            'error'   => 'No messages found',
            'message' => 'Session has no conversation messages',
            );
        }

        // Extract first user message and first assistant response
        $first_user_message       = null;
        $first_assistant_response = null;

        foreach ( $messages as $msg ) {
            $role    = $msg['role'] ?? '';
            $content = $msg['content'] ?? '';

            if ('user' === $role && null === $first_user_message && ! empty($content) ) {
                $first_user_message = $content;
            } elseif ('assistant' === $role && null === $first_assistant_response && ! empty($content) ) {
                $first_assistant_response = $content;
            }

            if (null !== $first_user_message && null !== $first_assistant_response ) {
                break;
            }
        }

        if (null === $first_user_message ) {
            return array(
            'success' => false,
            'error'   => 'No user message found',
            'message' => 'Session has no user messages to generate title from',
            );
        }

        // Check if AI titles are enabled
        $ai_titles_enabled = PluginSettings::get('chat_ai_titles_enabled', true);

        if (! $ai_titles_enabled ) {
            $title = self::generateTruncatedTitle($first_user_message);
            $success = $chat_db->update_title($session_id, $title);

            return array(
            'success' => $success,
            'title'   => $title,
            'method'  => 'fallback',
            'message' => $success ? 'Title generated using fallback method' : 'Failed to update session title',
            );
        }

        // Try AI generation
        $title = self::generateAITitle($first_user_message, $first_assistant_response);

        if (null === $title ) {
            $title = self::generateTruncatedTitle($first_user_message);
            $method = 'fallback';
        } else {
            $method = 'ai';
        }

        $success = $chat_db->update_title($session_id, $title);

        if ($success ) {
            do_action(
                'datamachine_log',
                'debug',
                'Session title generated',
                array(
                'session_id' => $session_id,
                'title'      => $title,
                'method'     => $method,
                'agent_type' => 'system',
                )
            );
        }

        return array(
        'success' => $success,
        'title'   => $title,
        'method'  => $method,
        'message' => $success ? 'Title generated successfully' : 'Failed to update session title',
        );
    }

    private static function generateAITitle( string $first_user_message, ?string $first_assistant_response ): ?string
    {
        $provider = PluginSettings::get('default_provider', '');
        $model    = PluginSettings::get('default_model', '');

        if (empty($provider) || empty($model) ) {
            do_action(
                'datamachine_log',
                'warning',
                'Session title AI generation skipped - no default provider/model configured',
                array( 'agent_type' => 'system' )
            );
            return null;
        }

        $context = 'User: ' . mb_substr($first_user_message, 0, 500);
        if ($first_assistant_response ) {
            $context .= "\n\nAssistant: " . mb_substr($first_assistant_response, 0, 500);
        }

        $messages = array(
        array(
        'role'    => 'user',
        'content' => "Generate a concise title (3-6 words) for this conversation. Return ONLY the title text, nothing else.\n\n" . $context,
        ),
        );

        $request = array(
        'model'      => $model,
        'messages'   => $messages,
        'max_tokens' => 50,
        );

        try {
            $response = RequestBuilder::build(
                $messages,
                $provider,
                $model,
                array(), // No tools for title generation
                'system', // Agent type
                array() // No payload needed
            );

            if (! $response['success'] ) {
                do_action(
                    'datamachine_log',
                    'error',
                    'Session title AI generation failed',
                    array(
                    'error'      => $response['error'] ?? 'Unknown error',
                    'agent_type' => 'system',
                    )
                );
                   return null;
            }

            $content = $response['data']['content'] ?? '';
            if (empty($content) ) {
                return null;
            }

            // Clean up the response - remove quotes, trim, limit length
            $title = trim($content);
            $title = trim($title, '"\'');
            $title = mb_substr($title, 0, 100); // Max title length

            return $title;
        } catch ( \Exception $e ) {
            do_action(
                'datamachine_log',
                'error',
                'Session title AI generation exception',
                array(
                'exception'  => $e->getMessage(),
                'agent_type' => 'system',
                )
            );
            return null;
        }
    }

    private static function generateTruncatedTitle( string $first_message ): string
    {
        $title = trim($first_message);

        // Remove newlines and excessive whitespace
        $title = preg_replace('/\s+/', ' ', $title);

        // Truncate to max length
        if (mb_strlen($title) > 97 ) { // Leave room for "..."
            $title = mb_substr($title, 0, 97) . '...';
        }

        return $title;
    }
}