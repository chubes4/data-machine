<?php
/**
 * Handles the admin menu registration and asset enqueueing for the Data Machine plugin.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/admin/utilities
 * @since      NEXT_VERSION
 */

namespace DataMachine\Admin;

use DataMachine\Core\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Manages admin menus and assets.
 */
class AdminMenuAssets {

    /**
     * The plugin version.
     *
     * @since    NEXT_VERSION
     * @access   private
     * @var      string    $version    The current plugin version.
     */
    private $version;

    /**
     * Dynamic storage for all registered page hook suffixes.
     * Replaces individual properties with unified storage system.
     *
     * @since    NEXT_VERSION
     * @access   private
     * @var      array
     */
    private $page_hook_suffixes = [];

    /**
     * Storage for page configurations for unified rendering.
     *
     * @since    NEXT_VERSION
     * @access   private
     * @var      array
     */
    private $page_configs = [];

    /**
     * Initialize the class with zero constructor dependencies.
     * 
     * Uses filter-based service access for pure filter-based architecture.
     *
     * @since    NEXT_VERSION
     */
    public function __construct() {
        $this->version = DATA_MACHINE_VERSION;
    }

    /**
     * Register hooks for admin menu and assets.
     *
     * @since NEXT_VERSION
     */
    public function init_hooks() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
    }

    /**
     * Register admin pages via direct parameter-based discovery system.
     * 
     * Uses consistent parameter-based pattern matching handlers, database services, 
     * and all other architectural components. Direct component registration without bridge.
     * Pages are ordered by position parameter with alphabetical fallback.
     *
     * @since    NEXT_VERSION
     */
    public function add_admin_menu() {
        // Direct parameter-based discovery - consistent with architectural standards
        $registered_pages = [];
        $known_slugs = ['jobs', 'pipelines', 'logs'];
        
        foreach ($known_slugs as $slug) {
            $page_config = apply_filters('dm_get_admin_page', null, $slug);
            if ($page_config !== null) {
                $registered_pages[$slug] = $page_config;
            }
        }
        
        // Only create Data Machine menu if pages are available
        if (empty($registered_pages)) {
            return;
        }
        
        // Sort pages by position, then alphabetically by menu_title
        uasort($registered_pages, function($a, $b) {
            $pos_a = $a['position'] ?? 50; // Default position 50
            $pos_b = $b['position'] ?? 50;
            
            if ($pos_a === $pos_b) {
                // Same position - sort alphabetically by menu_title
                $title_a = $a['menu_title'] ?? $a['page_title'] ?? '';
                $title_b = $b['menu_title'] ?? $b['page_title'] ?? '';
                return strcasecmp($title_a, $title_b);
            }
            
            return $pos_a <=> $pos_b;
        });
        
        // Use first sorted page as top-level menu
        $first_page = reset($registered_pages);
        $first_slug = key($registered_pages);
        
        $main_menu_hook = add_menu_page(
            __('Data Machine', 'data-machine'),
            __('Data Machine', 'data-machine'),
            $first_page['capability'] ?? 'manage_options',
            'dm-' . $first_slug,
            '', // No callback - main menu is just container
            'dashicons-database-view',
            30
        );
        
        // Store hook suffix and page config for first page
        $this->store_hook_suffix($first_slug, $main_menu_hook);
        $this->store_page_config($first_slug, $first_page);
        
        // Add first page as submenu with its proper title
        $first_submenu_hook = add_submenu_page(
            'dm-' . $first_slug,
            $first_page['page_title'] ?? $first_page['menu_title'] ?? ucfirst($first_slug),
            $first_page['menu_title'] ?? ucfirst($first_slug),
            $first_page['capability'] ?? 'manage_options',
            'dm-' . $first_slug,
            function() use ($first_page, $first_slug) {
                $this->render_admin_page_content($first_page, $first_slug);
            }
        );
        
        // Add remaining pages as submenus (already sorted)
        $remaining_pages = array_slice($registered_pages, 1, null, true);
        foreach ($remaining_pages as $slug => $page_config) {
            $hook_suffix = add_submenu_page(
                'dm-' . $first_slug,
                $page_config['page_title'] ?? $page_config['menu_title'] ?? ucfirst($slug),
                $page_config['menu_title'] ?? ucfirst($slug),
                $page_config['capability'] ?? 'manage_options',
                'dm-' . $slug,
                function() use ($page_config, $slug) {
                    $this->render_admin_page_content($page_config, $slug);
                }
            );
            
            // Store hook suffixes and page config for asset loading
            $this->store_hook_suffix($slug, $hook_suffix);
            $this->store_page_config($slug, $page_config);
        }
    }
    
    
    /**
     * Store hook suffix for dynamic asset loading.
     * 
     * Works with any registered page via parameter-based discovery.
     *
     * @param string $page_slug Page slug
     * @param string $hook_suffix WordPress hook suffix
     */
    private function store_hook_suffix($page_slug, $hook_suffix) {
        $this->page_hook_suffixes[$page_slug] = $hook_suffix;
    }

    /**
     * Store page configuration for unified rendering.
     *
     * @param string $page_slug Page slug
     * @param array $page_config Page configuration
     */
    private function store_page_config($page_slug, $page_config) {
        $this->page_configs[$page_slug] = $page_config;
    }

    /**
     * Render admin page content using unified configuration.
     *
     * @param array $page_config Page configuration
     * @param string $page_slug Page slug
     */
    private function render_admin_page_content($page_config, $page_slug) {
        // Direct template rendering using standardized template name pattern
        $content = apply_filters('dm_render_template', '', "page/{$page_slug}-page", [
            'page_slug' => $page_slug,
            'page_config' => $page_config
        ]);
        
        if (!empty($content)) {
            echo $content;
        } else {
            // Default empty state
            echo '<div class="wrap"><h1>' . esc_html($page_config['page_title'] ?? ucfirst($page_slug)) . '</h1>';
            echo '<p>' . esc_html__('Page content not configured.', 'data-machine') . '</p></div>';
        }
    }

    /**
     * Enqueue admin assets via dynamic page detection.
     * 
     * Uses parameter-based asset discovery aligned with filter-based architecture.
     *
     * @since    NEXT_VERSION
     * @param    string    $hook_suffix    The current admin page hook.
     */
    public function enqueue_admin_assets( $hook_suffix ) {
        // Find matching page slug for this hook suffix
        $current_page_slug = null;
        foreach ($this->page_hook_suffixes as $slug => $stored_hook) {
            if ($stored_hook === $hook_suffix) {
                $current_page_slug = $slug;
                break;
            }
        }
        
        if (!$current_page_slug) {
            return; // No registered page for this hook
        }
        
        // Get page assets from unified configuration
        $page_config = $this->page_configs[$current_page_slug] ?? [];
        $page_assets = $page_config['assets'] ?? [];
        
        // No fallback - unified system only
        
        if (!empty($page_assets['css']) || !empty($page_assets['js'])) {
            $this->enqueue_page_assets($page_assets, $current_page_slug);
        }
    }





    /**
     * Enqueue assets for a specific page using parameter-based configuration.
     *
     * @param array  $assets Page asset configuration
     * @param string $page_slug Page slug for context
     */
    private function enqueue_page_assets($assets, $page_slug) {
        $plugin_base_path = DATA_MACHINE_PATH;
        $plugin_base_url = plugins_url('/', 'data-machine/data-machine.php');
        
        // Enqueue CSS files
        if (!empty($assets['css'])) {
            foreach ($assets['css'] as $handle => $css_config) {
                $css_path = $plugin_base_path . $css_config['file'];
                $css_url = $plugin_base_url . $css_config['file'];
                $css_version = file_exists($css_path) ? filemtime($css_path) : $this->version;
                
                wp_enqueue_style(
                    $handle,
                    $css_url,
                    $css_config['deps'] ?? [],
                    $css_version,
                    $css_config['media'] ?? 'all'
                );
            }
        }
        
        // Enqueue JS files
        if (!empty($assets['js'])) {
            foreach ($assets['js'] as $handle => $js_config) {
                $js_path = $plugin_base_path . $js_config['file'];
                $js_url = $plugin_base_url . $js_config['file'];
                $js_version = file_exists($js_path) ? filemtime($js_path) : $this->version;
                
                wp_enqueue_script(
                    $handle,
                    $js_url,
                    $js_config['deps'] ?? ['jquery'],
                    $js_version,
                    $js_config['in_footer'] ?? true
                );
                
                // Add localization if provided
                if (!empty($js_config['localize'])) {
                    wp_localize_script($handle, $js_config['localize']['object'], $js_config['localize']['data']);
                }
            }
        }
    }

} // End class