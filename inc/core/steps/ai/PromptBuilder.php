<?php
/**
 * Output directive bridge for AI model interactions.
 * 
 * Focused class that provides essential output directive functionality as a bridge
 * between Data Machine handlers and AI processing. Connects handler-specific
 * directives via WordPress filters for extensible AI content formatting.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/core/steps/ai
 * @since      NEXT_VERSION
 */

namespace DataMachine\Core\Steps\AI;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class PromptBuilder {

    /**
     * Constructor.
     * 
     * Initializes the output directive bridge.
     */
    public function __construct() {
        // Constructor maintained for compatibility
    }


    /**
     * Build output-specific directive blocks for Data Machine.
     * Pure core framework with filter integration - zero handler knowledge.
     * 
     * @param string $output_type The output type
     * @param array $output_config The output configuration
     * @return string The directive block
     */
    public function build_output_directives(string $output_type, array $output_config): string {
        // Core directive foundation only
        $directive_block = "\n--- RESPONSE FORMATTING AND INSTRUCTIONS ---";
        $directive_block .= "\n1.  **Strict Adherence:** Follow all instructions below precisely.";
        
        // Handler directive integration via filter - NO fallbacks
        $directive_block = apply_filters('dm_get_output_directive', $directive_block, $output_type, $output_config);
        
        return $directive_block . "\n--- END RESPONSE FORMATTING AND INSTRUCTIONS ---";
    }


    /**
     * Register output directive filter for AI HTTP Client library integration.
     * Simplified registration for essential output directive bridge functionality.
     */
    public function register_all_sections(): void {
        // Output formatting directives section - essential bridge functionality
        add_filter('ai_http_client_section_output_directives', function($content, $context, $plugin_context) {
            if ($plugin_context !== 'data-machine') {
                return $content;
            }
            
            $output_type = $context['output_type'] ?? '';
            $output_config = $context['output_config'] ?? [];
            return $this->build_output_directives($output_type, $output_config);
        }, 10, 3);
    }

}