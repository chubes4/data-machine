<?php
/**
 * Trait for shared logic among Data Machine input handlers.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/input
 * @since      0.14.0
 */
trait Data_Machine_Base_Input_Handler {
    /**
     * Standardized input data packet structure:
     * [
     *   'data' => [
     *     'content_string' => (string), // Main content for processing
     *     'file_info' => (array|null),  // Optional file info if relevant
     *   ],
     *   'metadata' => [
     *     'source_type' => (string),
     *     'item_identifier_to_log' => (string),
     *     'original_id' => (string|int),
     *     'source_url' => (string|null),
     *     'original_title' => (string|null),
     *     'original_date_gmt' => (string|null),
     *     'original_date_raw' => (string|null),
     *     'image_source_url' => (string|null),
     *     'api_endpoint' => (string|null),
     *     'raw_item_data' => (array),
     *     // ...other handler-specific metadata
     *   ]
     * ]
     */

    /**
     * Checks module ownership for the given user and returns the project if valid.
     * Throws Exception if not valid.
     *
     * @param object $module
     * @param int $user_id
     * @param Data_Machine_Database_Projects $db_projects
     * @return object $project
     * @throws Exception
     */
    protected function get_module_with_ownership_check(object $module, int $user_id, $db_projects) {
        if (!isset($module->project_id)) {
            throw new Exception(__('Invalid module provided (missing project ID).', 'data-machine'));
        }
        $project = $db_projects->get_project($module->project_id, $user_id);
        if (!$project) {
            throw new Exception(__('Permission denied for this module.', 'data-machine'));
        }
        return $project;
    }

    /**
     * Recursively finds the first array of objects in a nested array structure.
     *
     * @param mixed $data
     * @return array
     */
    protected static function find_first_array_of_objects($data): array {
        if (!is_array($data)) {
            return [];
        }
        if (isset($data[0]) && (is_array($data[0]) || is_object($data[0]))) {
            return $data;
        }
        foreach ($data as $value) {
            if (is_array($value)) {
                $found = self::find_first_array_of_objects($value);
                if (!empty($found)) {
                    return $found;
                }
            }
        }
        return [];
    }
} 