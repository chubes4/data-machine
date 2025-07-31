<?php
/**
 * Unified WordPress Handler - Multi-Step Pattern
 *
 * Handles both WordPress input and output operations:
 * - Input: Fetches content from local/remote WordPress sites
 * - Output: Publishes content to local/remote WordPress sites
 *
 * This establishes the pattern for handlers that work across multiple step types,
 * sharing authentication and settings while maintaining specialized logic.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/inc/core/handlers/wordpress
 * @since      1.0.0
 */

namespace DataMachine\Core\Handlers\WordPress;

// DataPacket is engine-only - handlers work with simple arrays

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class WordPress {

    /**
     * Parameter-less constructor for pure filter-based architecture.
     */
    public function __construct() {
        // No parameters needed - all services accessed via filters
    }

    /**
     * Handle input data collection from WordPress sources.
     * 
     * @param int $pipeline_id Pipeline ID
     * @param array $handler_config Handler configuration
     * @param int $user_id User ID
     * @return object DataPacket object with collected content
     */
    public function get_input_data(int $pipeline_id, array $handler_config, int $user_id): object {
        // Delegate to input-specific logic
        $input_handler = $this->get_input_handler();
        return $input_handler->get_input_data($pipeline_id, $handler_config, $user_id);
    }

    /**
     * Handle output publishing to WordPress destinations.
     * 
     * @param object $data_packet Universal DataPacket JSON object
     * @param int $user_id User ID
     * @return array Result array
     */
    public function handle_output($data_packet, int $user_id): array {
        // Delegate to output-specific logic
        $output_handler = $this->get_output_handler();
        return $output_handler->handle_output($data_packet, $user_id);
    }

    /**
     * Get input handler instance.
     * 
     * @return WordPressInput Input handler instance
     */
    private function get_input_handler(): WordPressInput {
        static $input_handler = null;
        if ($input_handler === null) {
            $input_handler = new WordPressInput();
        }
        return $input_handler;
    }

    /**
     * Get output handler instance.
     * 
     * @return WordPressOutput Output handler instance
     */
    private function get_output_handler(): WordPressOutput {
        static $output_handler = null;
        if ($output_handler === null) {
            $output_handler = new WordPressOutput();
        }
        return $output_handler;
    }

    /**
     * Get user-friendly label for this handler.
     *
     * @return string Handler label
     */
    public static function get_label(): string {
        return __('WordPress', 'data-machine');
    }
}

