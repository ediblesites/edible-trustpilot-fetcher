<?php
/**
 * Plugin Name: Edible Trustpilot Fetcher
 * Description: Scrape and display Trustpilot reviews on WordPress sites
 * Version: 1.0.9
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
define('EDIBLE_TP_DEFAULT_SCRAPING_FREQUENCY', 72);
define('EDIBLE_TP_DEFAULT_REVIEW_LIMIT', 5);

// Includes
require_once EDIBLE_TP_PLUGIN_PATH . 'includes/class-cpt.php';
require_once EDIBLE_TP_PLUGIN_PATH . 'includes/class-scraper.php';
require_once EDIBLE_TP_PLUGIN_PATH . 'includes/class-admin.php';
require_once EDIBLE_TP_PLUGIN_PATH . 'includes/class-business-manager.php';
require_once EDIBLE_TP_PLUGIN_PATH . 'includes/class-api.php';
require_once EDIBLE_TP_PLUGIN_PATH . 'includes/class-init.php';

// Plugin activation/deactivation hooks
register_activation_hook(__FILE__, array('Trustpilot_Init', 'activate'));
register_deactivation_hook(__FILE__, array('Trustpilot_Init', 'deactivate'));

// Initialize the plugin
Trustpilot_Init::init(); 