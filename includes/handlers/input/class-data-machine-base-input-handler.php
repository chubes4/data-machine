<?php
/**
 * Base class for Data Machine input handlers.
 * Consolidates common functionality and patterns shared across all input handlers.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/handlers/input
 * @since      0.15.0
 */
class Data_Machine_Base_Input_Handler {
    
    // Universal dependencies required by all input handlers
    protected $db_processed_items;
    protected $db_modules;
    protected $db_projects;
    protected $logger;
    
    /**
     * Constructor with shared dependency injection.
     * Child classes should call parent::__construct() and add their specific dependencies.
     *
     * @param Data_Machine_Database_Modules $db_modules
     * @param Data_Machine_Database_Projects $db_projects  
     * @param Data_Machine_Database_Processed_Items $db_processed_items
     * @param Data_Machine_Logger|null $logger
     */
    public function __construct(
        $db_modules,
        $db_projects,
        $db_processed_items,
        $logger = null
    ) {
        $this->db_modules = $db_modules;
        $this->db_projects = $db_projects;
        $this->db_processed_items = $db_processed_items;
        $this->logger = $logger;
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
        $this->logger && $this->logger->info('Input Handler: Validating basic requirements.', [
            'module_id' => $module->module_id ?? null,
            'user_id' => $user_id
        ]);
        
        // Extract and validate module ID
        $module_id = isset($module->module_id) ? absint($module->module_id) : 0;
        if (empty($module_id)) {
            $this->logger && $this->logger->error('Input Handler: Module ID missing from module object.');
            throw new Exception(esc_html__('Missing module ID.', 'data-machine'));
        }
        
        // Validate user ID
        if (empty($user_id)) {
            $this->logger && $this->logger->error('Input Handler: User ID not provided.', ['module_id' => $module_id]);
            throw new Exception(esc_html__('User ID not provided.', 'data-machine'));
        }
        
        // Validate dependency injection
        if (!$this->db_processed_items || !$this->db_modules || !$this->db_projects) {
            $this->logger && $this->logger->error('Input Handler: Required database service not available.', ['module_id' => $module_id]);
            throw new Exception(esc_html__('Required database service not available in input handler.', 'data-machine'));
        }
        
        // Ownership check (using trait method)
        $project = $this->get_module_with_ownership_check($module, $user_id, $this->db_projects);
        
        return [
            'module_id' => $module_id,
            'project' => $project
        ];
    }
    
    /**
     * Parse common configuration fields shared across handlers.
     * Extracts item_count, timeframe_limit, and search term settings.
     *
     * @param array $source_config Handler configuration
     * @return array Parsed common config values
     */
    protected function parse_common_config($source_config) {
        $config = [
            'process_limit' => max(1, absint($source_config['item_count'] ?? 1)),
            'timeframe_limit' => $source_config['timeframe_limit'] ?? 'all_time',
            'search_term' => trim($source_config['search'] ?? '')
        ];
        
        // Parse search keywords if search term provided
        $config['search_keywords'] = [];
        if (!empty($config['search_term'])) {
            $keywords = array_map('trim', explode(',', $config['search_term']));
            $config['search_keywords'] = array_filter($keywords, function($k) { 
                return !empty($k); 
            });
        }
        
        $this->logger && $this->logger->info('Input Handler: Parsed common config.', [
            'process_limit' => $config['process_limit'],
            'timeframe_limit' => $config['timeframe_limit'],
            'search_keywords_count' => count($config['search_keywords'])
        ]);
        
        return $config;
    }
    
    /**
     * Calculate cutoff timestamp based on timeframe limit.
     * Used for filtering items by date/time.
     *
     * @param string $timeframe_limit Timeframe setting value
     * @return int|null Cutoff timestamp or null for 'all_time'
     */
    protected function calculate_cutoff_timestamp($timeframe_limit) {
        if ($timeframe_limit === 'all_time') {
            return null;
        }
        
        $interval_map = [
            '24_hours' => '-24 hours',
            '72_hours' => '-72 hours',
            '7_days'   => '-7 days',
            '30_days'  => '-30 days'
        ];
        
        if (!isset($interval_map[$timeframe_limit])) {
            $this->logger && $this->logger->warning('Input Handler: Invalid timeframe limit, using all_time.', [
                'timeframe_limit' => $timeframe_limit
            ]);
            return null;
        }
        
        $cutoff = strtotime($interval_map[$timeframe_limit], current_time('timestamp', true));
        $this->logger && $this->logger->info('Input Handler: Calculated timeframe cutoff.', [
            'timeframe_limit' => $timeframe_limit,
            'cutoff_timestamp' => $cutoff,
            'cutoff_date' => $cutoff ? gmdate('Y-m-d H:i:s', $cutoff) : null
        ]);
        
        return $cutoff;
    }
    
    /**
     * Check if an item has already been processed.
     *
     * @param int $module_id Module ID
     * @param string $source_type Handler source type
     * @param string $item_identifier Unique item identifier
     * @return bool True if already processed
     */
    protected function check_if_processed($module_id, $source_type, $item_identifier) {
        return $this->db_processed_items->has_item_been_processed($module_id, $source_type, $item_identifier);
    }
    
    /**
     * Filter content by search terms (keywords).
     *
     * @param string $content Content to search (title + body text)
     * @param array $keywords Array of keywords to search for
     * @return bool True if content matches search criteria (or no keywords)
     */
    protected function filter_by_search_terms($content, $keywords) {
        if (empty($keywords)) {
            return true; // No filter means all content passes
        }
        
        foreach ($keywords as $keyword) {
            if (mb_stripos($content, $keyword) !== false) {
                return true; // Found at least one keyword
            }
        }
        
        return false; // No keywords found
    }
    
    /**
     * Filter by timeframe cutoff.
     *
     * @param int|null $cutoff_timestamp Cutoff timestamp or null for no limit
     * @param int $item_timestamp Item's timestamp
     * @return bool True if item should be included
     */
    protected function filter_by_timeframe($cutoff_timestamp, $item_timestamp) {
        if ($cutoff_timestamp === null) {
            return true; // No time limit
        }
        
        if ($item_timestamp === false || $item_timestamp < $cutoff_timestamp) {
            return false; // Item is too old
        }
        
        return true; // Item passes time filter
    }
    
    /**
     * Create standardized input data packet structure.
     * All handlers should use this method to ensure consistent packet format.
     *
     * @param array $data Data section (content_string, file_info)
     * @param array $metadata Metadata section (source_type, identifiers, dates, etc.)
     * @return array Standardized input data packet
     */
    protected function create_input_data_packet($data, $metadata) {
        return [
            'data' => $data,
            'metadata' => $metadata
        ];
    }
    
    /**
     * Get common settings fields shared across handlers.
     * Child classes should merge these with handler-specific fields.
     *
     * @return array Common settings field definitions
     */
    protected function get_common_settings_fields() {
        return [
            'item_count' => [
                'type' => 'number',
                'label' => __('Items to Process', 'data-machine'),
                'description' => __('Number of items to fetch and process in this run.', 'data-machine'),
                'default' => 1,
                'min' => 1,
                'max' => 100,
                'required' => true
            ],
            'timeframe_limit' => [
                'type' => 'select',
                'label' => __('Process Items Within', 'data-machine'),
                'description' => __('Only process items published within this timeframe.', 'data-machine'),
                'options' => [
                    'all_time' => __('All Time', 'data-machine'),
                    '24_hours' => __('Last 24 Hours', 'data-machine'),
                    '72_hours' => __('Last 72 Hours', 'data-machine'),
                    '7_days' => __('Last 7 Days', 'data-machine'),
                    '30_days' => __('Last 30 Days', 'data-machine')
                ],
                'default' => 'all_time'
            ],
            'search' => [
                'type' => 'text',
                'label' => __('Search Term Filter', 'data-machine'),
                'description' => __('Optional: Only process items containing these keywords (comma-separated).', 'data-machine'),
                'default' => '',
                'placeholder' => __('keyword1, keyword2, phrase...', 'data-machine')
            ]
        ];
    }
    
    /**
     * Sanitize common settings fields.
     * Child classes should call this and merge with handler-specific sanitization.
     *
     * @param array $raw_settings Raw form input
     * @return array Sanitized common settings
     */
    protected function get_common_sanitized_settings($raw_settings) {
        return [
            'item_count' => max(1, min(100, absint($raw_settings['item_count'] ?? 1))),
            'timeframe_limit' => in_array($raw_settings['timeframe_limit'] ?? 'all_time', 
                ['all_time', '24_hours', '72_hours', '7_days', '30_days']) 
                ? $raw_settings['timeframe_limit'] : 'all_time',
            'search' => sanitize_text_field($raw_settings['search'] ?? '')
        ];
    }
    
    /**
     * Checks module ownership for the given user and returns the project if valid.
     * Moved from trait - common validation needed by all input handlers.
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
}