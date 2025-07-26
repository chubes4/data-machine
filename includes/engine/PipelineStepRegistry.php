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
     * @return array Array of pipeline steps with their configurations.
     */
    public function get_registered_steps(): array {
        return apply_filters('dm_register_pipeline_steps', []);
    }
    
    /**
     * Get prompt field requirements for all registered pipeline steps.
     *
     * @return array Array of prompt fields grouped by step name.
     */
    public function get_all_prompt_fields(): array {
        $pipeline_steps = $this->get_registered_steps();
        $prompt_fields = [];
        
        foreach ($pipeline_steps as $step_name => $step_config) {
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
                $logger = apply_filters('dm_get_service', null, 'logger');
                if ($logger) {
                    $logger->error('Error getting prompt fields for step', [
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
     * @param string $step_name The name of the pipeline step.
     * @return array Array of prompt field definitions, or empty array if not found.
     */
    public function get_step_prompt_fields(string $step_name): array {
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
            $logger = apply_filters('dm_get_service', null, 'logger');
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
}