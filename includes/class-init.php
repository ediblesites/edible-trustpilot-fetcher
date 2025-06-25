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
        
        // Set up Action Scheduler hooks for business scraping
        add_action('trustpilot_update_business_action', array('Trustpilot_Business_Manager', 'update_business_job'), 10, 1);
        add_action('trustpilot_save_review_action', array('Trustpilot_Business_Manager', 'save_single_review_job'), 10, 2);
        
        // Set up WordPress cron for scheduler
        add_action('trustpilot_scheduler_wakeup', array('Trustpilot_Business_Manager', 'check_and_schedule_due_businesses'));
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
        
        // Schedule WordPress cron for scheduler (reliable, can't get lost)
        if (!wp_next_scheduled('trustpilot_scheduler_wakeup')) {
            wp_schedule_event(time(), 'hourly', 'trustpilot_scheduler_wakeup');
        }
        
        // Run initial scheduler cycle
        Trustpilot_Business_Manager::check_and_schedule_due_businesses();
        
        // Flush rewrite rules for custom post types
        flush_rewrite_rules();
    }
    
    /**
     * Clean up on plugin deactivation
     */
    public static function deactivate() {
        // Clear WordPress cron
        wp_clear_scheduled_hook('trustpilot_scheduler_wakeup');
        
        // Clear all Trustpilot-related Action Scheduler actions
        if (class_exists('ActionScheduler_Store')) {
            $store = ActionScheduler_Store::instance();
            $store->cancel_actions_by_group('trustpilot-scraping');
        }
    }
} 