<?php
/**
 * General AI Tools - Filter Registration
 * 
 * Register general-purpose AI tools that are available to all AI steps
 * regardless of the next step's handler. These tools provide capabilities
 * like search, data processing, analysis, etc.
 *
 * @package DataMachine\Core\Steps\AI\Tools
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

/**
 * Register general AI tools filter
 * 
 * General tools do NOT have a 'handler' property, making them available
 * to all AI steps regardless of what the next pipeline step is.
 * 
 * Example tool registration:
 * 
 * add_filter('ai_tools', function($tools) {
 *     $tools['my_general_tool'] = [
 *         'class' => 'DataMachine\\Core\\Steps\\AI\\Tools\\MyTool',
 *         'method' => 'handle_tool_call',
 *         'description' => 'Description of what this tool does',
 *         'parameters' => [
 *             'input' => [
 *                 'type' => 'string',
 *                 'required' => true,
 *                 'description' => 'Input parameter description'
 *             ]
 *         ]
 *         // NOTE: No 'handler' property - this makes it a general tool
 *     ];
 *     return $tools;
 * });
 */
add_filter('ai_tools', function($tools) {
    // General tools will be registered here
    // Currently no tools implemented - ready for development
    return $tools;
});