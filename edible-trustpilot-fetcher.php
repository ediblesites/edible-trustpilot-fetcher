<?php
/**
 * Plugin Name: Edible Trustpilot Fetcher
 * Description: Scrape and display Trustpilot reviews on WordPress sites
 * Version: 1.0.3
 * Author: Edible Sites
 * Author URI: https://ediblesites.com
 * Plugin URI: https://github.com/ediblesites/edible-trustpilot-fetcher
 * Text Domain: edible-trustpilot-fetcher
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 8.1
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Network: false
 * GitHub Plugin URI: ediblesites/edible-trustpilot-fetcher
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('EDIBLE_TP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('EDIBLE_TP_PLUGIN_PATH', plugin_dir_path(__FILE__));

// Includes
require_once EDIBLE_TP_PLUGIN_PATH . 'includes/class-cpt.php';
require_once EDIBLE_TP_PLUGIN_PATH . 'includes/class-scraper.php';
require_once EDIBLE_TP_PLUGIN_PATH . 'includes/class-admin.php';
require_once EDIBLE_TP_PLUGIN_PATH . 'includes/class-business-manager.php';
require_once EDIBLE_TP_PLUGIN_PATH . 'includes/class-api.php';

// Plugin activation/deactivation hooks
register_activation_hook(__FILE__, 'trustpilot_activate');
register_deactivation_hook(__FILE__, 'trustpilot_cleanup_cron');

// Initialize the plugin
add_action('init', array('Trustpilot_CPT', 'register_post_types'));

// Initialize admin interface
if (is_admin()) {
    new Trustpilot_Admin();
}

// Remove "Add New" submenu items for post types
add_action('admin_menu', function() {
    remove_submenu_page('edit.php?post_type=tp_businesses', 'post-new.php?post_type=tp_businesses');
    remove_submenu_page('edit.php?post_type=tp_reviews', 'post-new.php?post_type=tp_reviews');
}, 999);

// Initialize API endpoints
Trustpilot_API::init();

// Cron job setup and execution
add_action('wp', 'trustpilot_setup_cron');
add_action('trustpilot_scrape_cron', 'trustpilot_run_scheduled_scraping');
add_filter('cron_schedules', 'trustpilot_add_cron_interval');

/**
 * Plugin activation hook
 */
function trustpilot_activate() {
    // Register post types and taxonomies
    Trustpilot_CPT::register_post_types();
    
    // Set up default options if they don't exist
    if (!get_option('trustpilot_review_limit')) {
        add_option('trustpilot_review_limit', 5);
    }
    if (!get_option('trustpilot_scraping_frequency')) {
        add_option('trustpilot_scraping_frequency', 24);
    }
    if (!get_option('trustpilot_rate_limit')) {
        add_option('trustpilot_rate_limit', 5);
    }
    
    // Set up cron job
    if (!wp_next_scheduled('trustpilot_scrape_cron')) {
        wp_schedule_event(time(), 'hourly', 'trustpilot_scrape_cron');
    }
    
    // Flush rewrite rules for custom post types
    flush_rewrite_rules();
}

/**
 * Clean up cron job on plugin deactivation
 */
function trustpilot_cleanup_cron() {
    wp_clear_scheduled_hook('trustpilot_scrape_cron');
}

/**
 * Add custom cron interval for 15 minutes
 */
function trustpilot_add_cron_interval($schedules) {
    $schedules['fifteen_minutes'] = array(
        'interval' => 900, // 15 minutes in seconds
        'display'  => 'Every 15 Minutes'
    );
    return $schedules;
}

/**
 * Set up the cron job if not already scheduled
 */
function trustpilot_setup_cron() {
    // Get the scraping frequency setting
    $scraping_frequency_hours = get_option('trustpilot_scraping_frequency', 24);
    
    // Clear existing cron if frequency changed
    $existing_cron = wp_next_scheduled('trustpilot_scrape_cron');
    if ($existing_cron) {
        wp_clear_scheduled_hook('trustpilot_scrape_cron');
    }
    
    // Schedule new cron based on frequency
    if ($scraping_frequency_hours >= 24) {
        // For 24+ hours, schedule daily
        wp_schedule_event(time(), 'daily', 'trustpilot_scrape_cron');
    } elseif ($scraping_frequency_hours >= 1) {
        // For 1-23 hours, schedule hourly
        wp_schedule_event(time(), 'hourly', 'trustpilot_scrape_cron');
    } else {
        // For less than 1 hour, schedule every 15 minutes
        wp_schedule_event(time(), 'fifteen_minutes', 'trustpilot_scrape_cron');
    }
}

/**
 * Run scheduled scraping of active businesses
 */
function trustpilot_run_scheduled_scraping() {
    // Use business manager to handle WordPress-specific logic
    $business_manager = new Trustpilot_Business_Manager();
    $results = $business_manager->scrape_all_active_businesses();
    
    // Log results for debugging
    error_log('Trustpilot Scraping Results: ' . json_encode($results));
} 