<?php
/**
 * AI HTTP Client - Prompt Manager
 * 
 * Single Responsibility: Provide generic prompt building utilities
 * Extension points for plugins to customize prompt construction
 *
 * @package AIHttpClient\Components
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_Prompt_Manager {
    
    /**
     * Build system prompt with filters
     *
     * @param string $base_prompt Base system prompt
     * @param array $context Context data
     * @return string Enhanced system prompt
     */
    public static function build_system_prompt($base_prompt, $context = []) {
        $prompt = apply_filters('ai_http_client_system_prompt', $base_prompt, $context);
        return apply_filters('ai_http_client_inject_context', $prompt, $context);
    }
    
    /**
     * Build user prompt with filters
     *
     * @param string $base_prompt Base user prompt
     * @param array $context Context data
     * @return string Enhanced user prompt
     */
    public static function build_user_prompt($base_prompt, $context = []) {
        $prompt = apply_filters('ai_http_client_user_prompt', $base_prompt, $context);
        return apply_filters('ai_http_client_replace_variables', $prompt, $context);
    }
    
    /**
     * Build message history array
     *
     * @param string $system_prompt System prompt
     * @param string $user_prompt User prompt
     * @param array $history Previous message history
     * @return array Complete message array
     */
    public static function build_messages($system_prompt, $user_prompt, $history = []) {
        $messages = [];
        
        // Add system message if provided
        if (!empty($system_prompt)) {
            $messages[] = ['role' => 'system', 'content' => $system_prompt];
        }
        
        // Add history messages
        if (!empty($history)) {
            $messages = array_merge($messages, $history);
        }
        
        // Add user message
        if (!empty($user_prompt)) {
            $messages[] = ['role' => 'user', 'content' => $user_prompt];
        }
        
        return apply_filters('ai_http_client_build_messages', $messages);
    }
    
    /**
     * Generic variable replacement
     *
     * @param string $text Text with variables
     * @param array $variables Variable replacements
     * @return string Text with variables replaced
     */
    public static function replace_variables($text, $variables = []) {
        if (empty($variables)) {
            return $text;
        }
        
        // Allow plugins to modify variables
        $variables = apply_filters('ai_http_client_prompt_variables', $variables);
        
        // Perform replacement
        return str_replace(
            array_keys($variables),
            array_values($variables),
            $text
        );
    }
    
    /**
     * Generic context injection
     *
     * @param string $prompt Original prompt
     * @param array $context Context data
     * @return string Prompt with context injected
     */
    public static function inject_context($prompt, $context = []) {
        if (empty($context)) {
            return $prompt;
        }
        
        // Allow plugins to format context
        $context_string = apply_filters('ai_http_client_format_context', '', $context);
        
        if (!empty($context_string)) {
            $prompt .= "\n\n" . $context_string;
        }
        
        return $prompt;
    }
    
    /**
     * Add response formatting instructions
     *
     * @param string $prompt Original prompt
     * @param array $format_config Format configuration
     * @return string Prompt with format instructions
     */
    public static function add_response_formatting($prompt, $format_config = []) {
        if (empty($format_config)) {
            return $prompt;
        }
        
        $format_instructions = apply_filters('ai_http_client_format_instructions', '', $format_config);
        
        if (!empty($format_instructions)) {
            $prompt .= "\n\n" . $format_instructions;
        }
        
        return $prompt;
    }
    
    /**
     * Build complete prompt configuration
     *
     * @param array $config Prompt configuration
     * @return array Complete prompt setup
     */
    public static function build_prompt_config($config = []) {
        $defaults = [
            'system_prompt' => '',
            'user_prompt' => '',
            'context' => [],
            'variables' => [],
            'format_config' => [],
            'history' => []
        ];
        
        $config = array_merge($defaults, $config);
        
        // Build system prompt
        if (!empty($config['system_prompt'])) {
            $config['system_prompt'] = self::build_system_prompt(
                $config['system_prompt'],
                $config['context']
            );
        }
        
        // Build user prompt
        if (!empty($config['user_prompt'])) {
            $config['user_prompt'] = self::build_user_prompt(
                $config['user_prompt'],
                $config['context']
            );
            
            // Apply variable replacement
            $config['user_prompt'] = self::replace_variables(
                $config['user_prompt'],
                $config['variables']
            );
            
            // Add response formatting
            $config['user_prompt'] = self::add_response_formatting(
                $config['user_prompt'],
                $config['format_config']
            );
        }
        
        // Build final messages
        $config['messages'] = self::build_messages(
            $config['system_prompt'],
            $config['user_prompt'],
            $config['history']
        );
        
        return $config;
    }
    
    /**
     * Default context formatter
     *
     * @param array $context Context data
     * @return string Formatted context string
     */
    public static function format_context($context) {
        if (empty($context)) {
            return '';
        }
        
        $formatted = "Context:\n";
        
        foreach ($context as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value);
            }
            
            $formatted .= "- {$key}: {$value}\n";
        }
        
        return $formatted;
    }
    
    /**
     * Default variable formatter
     *
     * @param array $variables Variables array
     * @return array Formatted variables with brackets
     */
    public static function format_variables($variables) {
        $formatted = [];
        
        foreach ($variables as $key => $value) {
            // Add brackets if not already present
            $formatted_key = strpos($key, '{') === false ? "{{$key}}" : $key;
            $formatted[$formatted_key] = $value;
        }
        
        return $formatted;
    }
    
    /**
     * Register tool definition for modular prompts
     *
     * @param string $tool_name Tool identifier
     * @param string $definition Tool description/instructions
     * @param array $config Optional tool configuration
     */
    public static function register_tool_definition($tool_name, $definition, $config = []) {
        $tool_definitions = get_option('ai_http_client_tool_definitions', []);
        
        $tool_definitions[$tool_name] = [
            'definition' => $definition,
            'config' => $config,
            'registered_by' => apply_filters('ai_http_client_current_plugin', 'unknown')
        ];
        
        update_option('ai_http_client_tool_definitions', $tool_definitions);
        
        // Allow plugins to hook into tool registration
        do_action('ai_http_client_tool_registered', $tool_name, $definition, $config);
    }
    
    /**
     * Build tool section based on enabled tools
     *
     * @param array $enabled_tools Array of enabled tool names
     * @return string Formatted tool section
     */
    public static function build_tool_section($enabled_tools = []) {
        if (empty($enabled_tools)) {
            $enabled_tools = self::get_enabled_tools();
        }
        
        if (empty($enabled_tools)) {
            return '';
        }
        
        $tool_definitions = get_option('ai_http_client_tool_definitions', []);
        $enabled_definitions = [];
        
        foreach ($enabled_tools as $tool_name) {
            if (isset($tool_definitions[$tool_name])) {
                $enabled_definitions[$tool_name] = $tool_definitions[$tool_name]['definition'];
            }
        }
        
        if (empty($enabled_definitions)) {
            return '';
        }
        
        $tool_section = "# Available Tools\n\n" . implode("\n\n", $enabled_definitions);
        
        // Allow plugins to modify tool section
        return apply_filters('ai_http_client_tool_section', $tool_section, $enabled_tools, $enabled_definitions);
    }
    
    /**
     * Get enabled tools from options
     *
     * @param string $context Optional context for different tool sets
     * @return array Array of enabled tool names
     */
    public static function get_enabled_tools($context = 'default') {
        $option_key = $context === 'default' ? 'ai_http_client_enabled_tools' : "ai_http_client_enabled_tools_{$context}";
        $enabled_tools = get_option($option_key, []);
        
        // Allow plugins to modify enabled tools
        return apply_filters('ai_http_client_enabled_tools', $enabled_tools, $context);
    }
    
    /**
     * Set enabled tools
     *
     * @param array $tools Array of tool names to enable
     * @param string $context Optional context for different tool sets
     */
    public static function set_enabled_tools($tools, $context = 'default') {
        $option_key = $context === 'default' ? 'ai_http_client_enabled_tools' : "ai_http_client_enabled_tools_{$context}";
        update_option($option_key, $tools);
        
        // Allow plugins to hook into tool enabling
        do_action('ai_http_client_tools_enabled', $tools, $context);
    }
    
    /**
     * Get all registered tool definitions
     *
     * @return array All tool definitions
     */
    public static function get_all_tool_definitions() {
        $tool_definitions = get_option('ai_http_client_tool_definitions', []);
        
        // Allow plugins to add runtime tool definitions
        return apply_filters('ai_http_client_all_tool_definitions', $tool_definitions);
    }
    
    /**
     * Build enhanced system prompt with modular sections
     *
     * @param string $base_prompt Base system prompt
     * @param array $context Context data
     * @param array $options Modular prompt options
     * @return string Complete system prompt
     */
    public static function build_modular_system_prompt($base_prompt, $context = [], $options = []) {
        $defaults = [
            'include_tools' => true,
            'enabled_tools' => [],
            'tool_context' => 'default',
            'sections' => []
        ];
        
        $options = array_merge($defaults, $options);
        
        // Start with base prompt processing
        $prompt = self::build_system_prompt($base_prompt, $context);
        
        // Add custom sections
        if (!empty($options['sections'])) {
            foreach ($options['sections'] as $section_name => $section_content) {
                $section_content = apply_filters("ai_http_client_section_{$section_name}", $section_content, $context);
                if (!empty($section_content)) {
                    $prompt .= "\n\n" . $section_content;
                }
            }
        }
        
        // Add tools section if enabled
        if ($options['include_tools']) {
            $enabled_tools = !empty($options['enabled_tools']) ? 
                $options['enabled_tools'] : 
                self::get_enabled_tools($options['tool_context']);
                
            $tool_section = self::build_tool_section($enabled_tools);
            if (!empty($tool_section)) {
                $prompt .= "\n\n" . $tool_section;
            }
        }
        
        // Allow final prompt modification
        return apply_filters('ai_http_client_modular_system_prompt', $prompt, $base_prompt, $context, $options);
    }
}