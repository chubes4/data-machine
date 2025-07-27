<?php
/**
 * Pipeline Step Registry Service
 *
 * Manages the registration and querying of pipeline steps and their configuration requirements.
 * This service bridges the gap between the pipeline step registration system and the 
 * dynamic form generation system.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/engine
 * @since      NEXT_VERSION
 */

namespace DataMachine\Engine;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class PipelineStepRegistry {
    
    /**
     * Get all registered pipeline steps with their configuration.
     * 
     * This method now supports both the new data-driven dm_register_step_types system
     * and the legacy dm_register_pipeline_steps system for backward compatibility.
     *
     * @return array Array of pipeline steps with their configurations.
     */
    public function get_registered_steps(): array {
        // Get steps from new data-driven system
        $step_types = apply_filters('dm_register_step_types', []);
        
        // Convert step types to pipeline steps format
        $pipeline_steps = [];
        foreach ($step_types as $step_name => $step_config) {
            $pipeline_steps[$step_name] = [
                'class' => $step_config['class'],
                'label' => $step_config['label'],
                'description' => $step_config['description'] ?? '',
                'type' => $step_config['type'],
                'config_type' => $step_config['config_type'] ?? 'project_level',
                'category' => $step_config['category'] ?? 'core',
                'icon' => $step_config['icon'] ?? 'dashicons-admin-generic',
                'supports' => $step_config['supports'] ?? [],
                'next' => $step_config['next'] ?? null
            ];
        }
        
        // Merge with legacy system for backward compatibility
        $legacy_steps = apply_filters('dm_register_pipeline_steps', []);
        $pipeline_steps = array_merge($pipeline_steps, $legacy_steps);
        
        return $pipeline_steps;
    }
    
    /**
     * Get prompt field requirements for all registered pipeline steps.
     * 
     * Supports both the new data-driven system (prompt_fields defined in step config)
     * and legacy system (get_prompt_fields() method on step class).
     *
     * @return array Array of prompt fields grouped by step name.
     */
    public function get_all_prompt_fields(): array {
        // Get step types directly from the new system for prompt fields
        $step_types = apply_filters('dm_register_step_types', []);
        $prompt_fields = [];
        
        foreach ($step_types as $step_name => $step_config) {
            // Check if prompt fields are defined directly in step config (new system)
            if (isset($step_config['prompt_fields']) && !empty($step_config['prompt_fields'])) {
                $prompt_fields[$step_name] = [
                    'step_config' => $step_config,
                    'prompt_fields' => $step_config['prompt_fields']
                ];
                continue;
            }
            
            // Fall back to legacy method for backward compatibility
            $step_class = $step_config['class'] ?? null;
            
            if (!$step_class || !class_exists($step_class)) {
                continue;
            }
            
            if (!method_exists($step_class, 'get_prompt_fields')) {
                continue;
            }
            
            try {
                $fields = $step_class::get_prompt_fields();
                if (!empty($fields)) {
                    $prompt_fields[$step_name] = [
                        'step_config' => $step_config,
                        'prompt_fields' => $fields
                    ];
                }
            } catch (\Exception $e) {
                // Log error but continue processing other steps
                $logger = apply_filters('dm_get_logger', null);
                if ($logger) {
                    $logger->error('Error getting prompt fields for step', [
                        'step_name' => $step_name,
                        'step_class' => $step_class,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
        
        // Also check legacy pipeline steps system
        $legacy_steps = apply_filters('dm_register_pipeline_steps', []);
        foreach ($legacy_steps as $step_name => $step_config) {
            // Skip if already processed from new system
            if (isset($prompt_fields[$step_name])) {
                continue;
            }
            
            $step_class = $step_config['class'] ?? null;
            
            if (!$step_class || !class_exists($step_class)) {
                continue;
            }
            
            if (!method_exists($step_class, 'get_prompt_fields')) {
                continue;
            }
            
            try {
                $fields = $step_class::get_prompt_fields();
                if (!empty($fields)) {
                    $prompt_fields[$step_name] = [
                        'step_config' => $step_config,
                        'prompt_fields' => $fields
                    ];
                }
            } catch (\Exception $e) {
                // Log error but continue processing other steps
                $logger = apply_filters('dm_get_logger', null);
                if ($logger) {
                    $logger->error('Error getting prompt fields for legacy step', [
                        'step_name' => $step_name,
                        'step_class' => $step_class,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
        
        return $prompt_fields;
    }
    
    /**
     * Get prompt fields for a specific pipeline step.
     * 
     * Supports both the new data-driven system (prompt_fields defined in step config)
     * and legacy system (get_prompt_fields() method on step class).
     *
     * @param string $step_name The name of the pipeline step.
     * @return array Array of prompt field definitions, or empty array if not found.
     */
    public function get_step_prompt_fields(string $step_name): array {
        // First check new data-driven system
        $step_types = apply_filters('dm_register_step_types', []);
        
        if (isset($step_types[$step_name]['prompt_fields'])) {
            return $step_types[$step_name]['prompt_fields'];
        }
        
        // Fall back to checking both systems for step class method
        $pipeline_steps = $this->get_registered_steps();
        
        if (!isset($pipeline_steps[$step_name])) {
            return [];
        }
        
        $step_config = $pipeline_steps[$step_name];
        $step_class = $step_config['class'] ?? null;
        
        if (!$step_class || !class_exists($step_class)) {
            return [];
        }
        
        if (!method_exists($step_class, 'get_prompt_fields')) {
            return [];
        }
        
        try {
            return $step_class::get_prompt_fields();
        } catch (\Exception $e) {
            $logger = apply_filters('dm_get_logger', null);
            if ($logger) {
                $logger->error('Error getting prompt fields for specific step', [
                    'step_name' => $step_name,
                    'step_class' => $step_class,
                    'error' => $e->getMessage()
                ]);
            }
            return [];
        }
    }
    
    /**
     * Check if a pipeline step requires prompt configuration.
     *
     * @param string $step_name The name of the pipeline step.
     * @return bool True if the step requires prompts, false otherwise.
     */
    public function step_requires_prompts(string $step_name): bool {
        $prompt_fields = $this->get_step_prompt_fields($step_name);
        return !empty($prompt_fields);
    }
    
    /**
     * Get pipeline step execution order.
     *
     * @return array Array of step names in execution order.
     */
    public function get_step_execution_order(): array {
        $pipeline_steps = $this->get_registered_steps();
        $execution_order = [];
        
        // Find the first step (no previous step points to it)
        $all_next_steps = array_filter(array_column($pipeline_steps, 'next'));
        $first_step = null;
        
        foreach (array_keys($pipeline_steps) as $step_name) {
            if (!in_array($step_name, $all_next_steps)) {
                $first_step = $step_name;
                break;
            }
        }
        
        if (!$first_step) {
            // Fallback: return steps in registration order
            return array_keys($pipeline_steps);
        }
        
        // Build execution order by following 'next' pointers
        $current_step = $first_step;
        while ($current_step && isset($pipeline_steps[$current_step])) {
            $execution_order[] = $current_step;
            $current_step = $pipeline_steps[$current_step]['next'] ?? null;
        }
        
        return $execution_order;
    }
    
    /**
     * Get pipeline steps that have prompt requirements, ordered by execution sequence.
     *
     * @return array Array of steps with prompt requirements in execution order.
     */
    public function get_prompt_steps_in_order(): array {
        $execution_order = $this->get_step_execution_order();
        $prompt_fields = $this->get_all_prompt_fields();
        $prompt_steps = [];
        
        foreach ($execution_order as $step_name) {
            if (isset($prompt_fields[$step_name])) {
                $prompt_steps[$step_name] = $prompt_fields[$step_name];
            }
        }
        
        return $prompt_steps;
    }
    
    /**
     * Get available next steps based on current pipeline configuration and flow rules.
     *
     * Flow rules:
     * - Input steps → Any processing step (ai, custom processing)
     * - AI steps → Other AI steps or output steps  
     * - Output steps → Terminal (no next steps allowed)
     * - Adjacent steps (same type) or forward steps (next type) only
     * - No backward flow or circular dependencies
     *
     * @param int   $project_id      The project ID to analyze.
     * @param array $existing_steps  Current pipeline steps array.
     * @param int   $current_position Position of current step (0-based).
     * @return array Array of available next step types with metadata.
     */
    public function get_available_next_steps(int $project_id, array $existing_steps, int $current_position): array {
        $flow_engine = apply_filters('dm_get_flow_validation_engine', null);
        if (!$flow_engine) {
            return [];
        }
        
        $logger = apply_filters('dm_get_logger', null);
        $logger->info('Analyzing available next steps', [
            'project_id' => $project_id,
            'current_position' => $current_position,
            'existing_step_count' => count($existing_steps)
        ]);
        
        // Get current step context
        $current_step = $existing_steps[$current_position] ?? null;
        if (!$current_step) {
            return [];
        }
        
        $current_step_type = $current_step['type'] ?? '';
        
        // Get all registered steps
        $all_steps = $this->get_registered_steps();
        $available_next = [];
        
        foreach ($all_steps as $step_name => $step_config) {
            $step_type = $this->get_step_type_from_name($step_name);
            
            // Validate flow rules
            if ($flow_engine->validate_flow_rules($current_step_type, $step_type)) {
                // Check for circular dependencies
                if (!$this->would_create_circular_dependency($existing_steps, $current_position, $step_type)) {
                    $available_next[$step_name] = [
                        'name' => $step_name,
                        'type' => $step_type,
                        'label' => $step_config['label'] ?? ucfirst(str_replace('_', ' ', $step_name)),
                        'class' => $step_config['class'] ?? '',
                        'description' => $step_config['description'] ?? '',
                        'flow_type' => $this->get_flow_type($current_step_type, $step_type)
                    ];
                }
            }
        }
        
        $logger->info('Found available next steps', [
            'project_id' => $project_id,
            'current_step_type' => $current_step_type,
            'available_count' => count($available_next),
            'step_names' => array_keys($available_next)
        ]);
        
        return $available_next;
    }
    
    /**
     * Validate flow rules between two step types.
     *
     * @param string $from_step The source step type.
     * @param string $to_step   The target step type.
     * @return bool True if flow is valid, false otherwise.
     */
    public function validate_flow_rules(string $from_step, string $to_step): bool {
        $flow_engine = apply_filters('dm_get_flow_validation_engine', null);
        if (!$flow_engine) {
            return false;
        }
        
        return $flow_engine->validate_flow_rules($from_step, $to_step);
    }
    
    /**
     * Get step type constraints for pipeline building.
     * Now dynamically builds constraints from registered step types.
     *
     * @return array Array of step type constraints and rules.
     */
    public function get_step_constraints(): array {
        $registered_step_types = apply_filters('dm_register_step_types', []);
        
        // Build dynamic constraints based on registered step types
        $flow_rules = [];
        $terminal_types = [];
        $entry_types = [];
        $processing_types = [];
        
        foreach ($registered_step_types as $step_name => $step_config) {
            $step_type = $step_config['type'] ?? $step_name;
            $category = $step_config['category'] ?? 'custom';
            $supports = $step_config['supports'] ?? [];
            
            // Determine step type classification
            if (strpos($step_type, 'input') !== false || in_array('source_handlers', $supports)) {
                $entry_types[] = $step_type;
                $flow_rules[$step_type] = ['ai', 'processing', 'custom'];
            } elseif (strpos($step_type, 'output') !== false || in_array('destination_handlers', $supports)) {
                $terminal_types[] = $step_type;
                $flow_rules[$step_type] = []; // Terminal - no next steps
            } elseif (strpos($step_type, 'ai') !== false || in_array('ai_providers', $supports)) {
                $processing_types[] = $step_type;
                $flow_rules[$step_type] = ['ai', 'output', 'processing'];
            } else {
                // Custom processing step
                $processing_types[] = $step_type;
                $flow_rules[$step_type] = ['ai', 'output', 'processing', 'custom'];
            }
        }
        
        // Remove duplicates
        $terminal_types = array_unique($terminal_types);
        $entry_types = array_unique($entry_types);
        $processing_types = array_unique($processing_types);
        
        return [
            'flow_rules' => $flow_rules,
            'terminal_types' => $terminal_types,
            'entry_types' => $entry_types,
            'processing_types' => $processing_types,
            'max_consecutive_same_type' => 10, // Reasonable limit
            'circular_dependency_check' => true
        ];
    }
    
    /**
     * Extract step type from step name using registered step types.
     * Now fully data-driven from registered step types.
     *
     * @param string $step_name The step name.
     * @return string The step type.
     */
    private function get_step_type_from_name(string $step_name): string {
        // Get registered step types
        $registered_step_types = apply_filters('dm_register_step_types', []);
        
        // Direct lookup first
        if (isset($registered_step_types[$step_name])) {
            return $registered_step_types[$step_name]['type'] ?? $step_name;
        }
        
        // Pattern matching against registered types
        foreach ($registered_step_types as $registered_name => $step_config) {
            $step_type = $step_config['type'] ?? $registered_name;
            
            // Check if step name starts with or contains the registered type
            if (strpos($step_name, $step_type) === 0 || strpos($step_name, $registered_name) === 0) {
                return $step_type;
            }
        }
        
        // Fallback: use the step name as type
        return $step_name;
    }
    
    /**
     * Check if adding a step would create a circular dependency.
     *
     * @param array  $existing_steps  Current pipeline steps.
     * @param int    $current_position Current step position.
     * @param string $new_step_type   Type of step to add.
     * @return bool True if would create circular dependency, false otherwise.
     */
    private function would_create_circular_dependency(array $existing_steps, int $current_position, string $new_step_type): bool {
        // Simple check: look for the same step type occurring earlier in pipeline
        for ($i = 0; $i <= $current_position; $i++) {
            $step = $existing_steps[$i] ?? null;
            if ($step && isset($step['type']) && $step['type'] === $new_step_type) {
                // Allow same type if it's a processing type - get from constraints
                $constraints = $this->get_step_constraints();
                $processing_types = $constraints['processing_types'] ?? [];
                if (in_array($new_step_type, $processing_types)) {
                    continue; // Allow multiple processing steps
                }
                return true; // Circular dependency detected
            }
        }
        
        return false;
    }
    
    /**
     * Get flow type classification (adjacent vs forward).
     *
     * @param string $from_step Source step type.
     * @param string $to_step   Target step type.
     * @return string Flow type ('adjacent', 'forward', 'invalid').
     */
    private function get_flow_type(string $from_step, string $to_step): string {
        if ($from_step === $to_step) {
            return 'adjacent'; // Same type
        }
        
        // Get dynamic type hierarchy from registered step types
        $registered_step_types = apply_filters('dm_register_step_types', []);
        $type_hierarchy = [];
        
        // Build hierarchy based on step category and natural flow order
        foreach ($registered_step_types as $step_name => $step_config) {
            $step_type = $step_config['type'] ?? $step_name;
            if (!in_array($step_type, $type_hierarchy)) {
                $type_hierarchy[] = $step_type;
            }
        }
        
        // Ensure standard flow order if available
        $standard_order = ['input', 'ai', 'processing', 'output'];
        $ordered_hierarchy = [];
        foreach ($standard_order as $standard_type) {
            if (in_array($standard_type, $type_hierarchy)) {
                $ordered_hierarchy[] = $standard_type;
            }
        }
        // Add any additional types not in standard order
        foreach ($type_hierarchy as $type) {
            if (!in_array($type, $ordered_hierarchy)) {
                $ordered_hierarchy[] = $type;
            }
        }
        
        $from_index = array_search($from_step, $ordered_hierarchy);
        $to_index = array_search($to_step, $ordered_hierarchy);
        
        if ($from_index !== false && $to_index !== false) {
            if ($to_index > $from_index) {
                return 'forward';
            }
        }
        
        return 'invalid';
    }
}