<?php
/**
 * Plugin initialization class
 * 
 * Handles all plugin initialization, hooks, and setup
 */

class Trustpilot_Init {
    
    /**
     * Initialize the plugin
     */
    public static function init() {
        // Initialize post types
        add_action('init', array('Trustpilot_CPT', 'register_post_types'));
        
        // Initialize admin interface
        if (is_admin()) {
            new Trustpilot_Admin();
        }
        
        // Initialize API endpoints
        Trustpilot_API::init();
        
        // Set up cron jobs
        add_action('wp', array(__CLASS__, 'setup_cron'));
        add_action('trustpilot_scrape_cron', array(__CLASS__, 'run_scheduled_scraping'));
        add_filter('cron_schedules', array(__CLASS__, 'add_cron_interval'));
    }
    
    /**
     * Plugin activation hook
     */
    public static function activate() {
        // Register post types and taxonomies
        Trustpilot_CPT::register_post_types();
        
        // Set up default options if they don't exist
        if (!get_option('trustpilot_review_limit')) {
            add_option('trustpilot_review_limit', EDIBLE_TP_DEFAULT_REVIEW_LIMIT);
        }
        if (!get_option('trustpilot_scraping_frequency')) {
            add_option('trustpilot_scraping_frequency', EDIBLE_TP_DEFAULT_SCRAPING_FREQUENCY);
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
    public static function deactivate() {
        wp_clear_scheduled_hook('trustpilot_scrape_cron');
    }
    
    /**
     * Add custom cron interval for 15 minutes
     */
    public static function add_cron_interval($schedules) {
        $schedules['fifteen_minutes'] = array(
            'interval' => 900, // 15 minutes in seconds
            'display'  => 'Every 15 Minutes'
        );
        return $schedules;
    }
    
    /**
     * Set up the cron job if not already scheduled
     */
    public static function setup_cron() {
        // Get the scraping frequency setting
        $scraping_frequency_hours = get_option('trustpilot_scraping_frequency', EDIBLE_TP_DEFAULT_SCRAPING_FREQUENCY);
        
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
    public static function run_scheduled_scraping() {
        // Use business manager to handle WordPress-specific logic
        $business_manager = new Trustpilot_Business_Manager();
        $results = $business_manager->scrape_all_active_businesses();
        
        // Log results for debugging
        error_log('Trustpilot Scraping Results: ' . json_encode($results));
    }
} 