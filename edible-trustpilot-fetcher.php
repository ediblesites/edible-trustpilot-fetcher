<?php
/**
 * Plugin Name: Edible Trustpilot Fetcher
 * Description: Scrape and display Trustpilot reviews on WordPress sites
 * Version: 1.0.0
 * Author: Edible
 * Text Domain: edible-trustpilot-fetcher
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('EDIBLE_TP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('EDIBLE_TP_PLUGIN_PATH', plugin_dir_path(__FILE__));

// Include the CPT class
require_once EDIBLE_TP_PLUGIN_PATH . 'includes/class-trustpilot-cpt.php';

// Include the scraper class
require_once EDIBLE_TP_PLUGIN_PATH . 'includes/class-trustpilot-scraper.php';

// Include the admin class
require_once EDIBLE_TP_PLUGIN_PATH . 'includes/class-trustpilot-admin.php';

// Include the business manager class
require_once EDIBLE_TP_PLUGIN_PATH . 'includes/class-trustpilot-business-manager.php';

// Include the API class
require_once EDIBLE_TP_PLUGIN_PATH . 'includes/class-trustpilot-api.php';

// Initialize the plugin
add_action('init', array('Trustpilot_CPT', 'register_post_types'));

// Initialize admin interface
if (is_admin()) {
    new Trustpilot_Admin();
}

// Initialize API endpoints
Trustpilot_API::init();

// Set up cron job for scheduled scraping
add_action('wp', 'trustpilot_setup_cron');
add_action('trustpilot_scrape_cron', 'trustpilot_run_scheduled_scraping');
add_filter('cron_schedules', 'trustpilot_add_cron_interval');

// Clean up cron on plugin deactivation
register_deactivation_hook(__FILE__, 'trustpilot_cleanup_cron');

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
 * Clean up cron job on plugin deactivation
 */
function trustpilot_cleanup_cron() {
    wp_clear_scheduled_hook('trustpilot_scrape_cron');
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