<?php
/**
 * Base class for Data Machine output handlers.
 * Consolidates common functionality and patterns shared across all output handlers.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/handlers/output
 * @since      0.15.0
 */

namespace DataMachine\Core\Handlers\Output;

use DataMachine\Database\{Modules, Projects};
use DataMachine\Core\DataPacket;

if (!defined('ABSPATH')) {
    exit;
}

abstract class BaseOutputHandler {
    
    /**
     * Get the logger service.
     *
     * @return mixed The logger service.
     */
    protected function get_logger() {
        return apply_filters('dm_get_logger', null);
    }
    
    /**
     * Get the modules database service.
     *
     * @return Modules The modules database service.
     */
    protected function get_db_modules() {
        return apply_filters('dm_get_db_modules', null);
    }
    
    /**
     * Get the projects database service.
     *
     * @return Projects The projects database service.
     */
    protected function get_db_projects() {
        return apply_filters('dm_get_db_projects', null);
    }
    
    /**
     * Perform basic validation and setup required by all handlers.
     * Validates module ID, user ID, dependencies, and performs ownership check.
     *
     * @param object $module Module object
     * @param int $user_id User ID
     * @return array Contains validated module_id and project object
     * @throws Exception If validation fails
     */
    protected function validate_basic_requirements($module, $user_id) {
        $logger = $this->get_logger();
        $db_modules = $this->get_db_modules();
        $db_projects = $this->get_db_projects();
        
        $logger && $logger->info('Output Handler: Validating basic requirements.', [
            'module_id' => $module->module_id ?? null,
            'user_id' => $user_id
        ]);
        
        // Extract and validate module ID
        $module_id = isset($module->module_id) ? absint($module->module_id) : 0;
        if (empty($module_id)) {
            $logger && $logger->error('Output Handler: Module ID missing from module object.');
            throw new Exception(esc_html__('Missing module ID.', 'data-machine'));
        }
        
        // Validate user ID
        if (empty($user_id)) {
            $logger && $logger->error('Output Handler: User ID not provided.', ['module_id' => $module_id]);
            throw new Exception(esc_html__('User ID not provided.', 'data-machine'));
        }
        
        // Validate dependencies
        if (!$db_modules || !$db_projects) {
            $logger && $logger->error('Output Handler: Required database service not available.', ['module_id' => $module_id]);
            throw new Exception(esc_html__('Required database service not available in output handler.', 'data-machine'));
        }
        
        // Ownership check
        $project = $this->get_module_with_ownership_check($module, $user_id, $db_projects);
        
        return [
            'module_id' => $module_id,
            'project' => $project
        ];
    }
    
    /**
     * Checks module ownership for the given user and returns the project if valid.
     * Common validation needed by all output handlers.
     *
     * @param object $module
     * @param int $user_id
     * @param object $db_projects Database projects service
     * @return object $project
     * @throws Exception If validation fails
     */
    protected function get_module_with_ownership_check($module, $user_id, $db_projects) {
        if (!isset($module->project_id)) {
            throw new Exception(esc_html__('Invalid module provided (missing project ID).', 'data-machine'));
        }
        $project = $db_projects->get_project($module->project_id, $user_id);
        if (!$project) {
            throw new Exception(esc_html__('Permission denied for this module.', 'data-machine'));
        }
        return $project;
    }
    
    
    abstract public function handle_output(DataPacket $finalized_data, object $module, int $user_id): array;
    
    abstract public static function get_settings_fields(array $current_config = []): array;
}