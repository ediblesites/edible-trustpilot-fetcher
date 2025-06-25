<?php
/**
 * Admin interface for Trustpilot Fetcher
 */

class Trustpilot_Admin {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'init_settings'));
        add_action('wp_ajax_add_trustpilot_business', array($this, 'handle_add_business'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    /**
     * Add admin menu items
     */
    public function add_admin_menu() {
        // Dashboard overview page
        add_submenu_page(
            'edit.php?post_type=tp_businesses',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'trustpilot-dashboard',
            array($this, 'dashboard_page')
        );

        // Add Business submenu under Trustpilot Businesses
        add_submenu_page(
            'edit.php?post_type=tp_businesses',
            'Add Business',
            'Add Business',
            'manage_options',
            'add-trustpilot-business',
            array($this, 'add_business_page')
        );

        // Settings page
        add_submenu_page(
            'edit.php?post_type=tp_businesses',
            'Settings',
            'Settings',
            'manage_options',
            'trustpilot-settings',
            array($this, 'settings_page')
        );
    }

    /**
     * Initialize settings
     */
    public function init_settings() {
        register_setting('trustpilot_settings', 'trustpilot_scraping_frequency', array(
            'type' => 'integer',
            'default' => 24,
            'sanitize_callback' => 'intval'
        ));

        register_setting('trustpilot_settings', 'trustpilot_review_limit', array(
            'type' => 'integer',
            'default' => 5,
            'sanitize_callback' => 'intval'
        ));

        register_setting('trustpilot_settings', 'trustpilot_rate_limit', array(
            'type' => 'integer',
            'default' => 5,
            'sanitize_callback' => 'intval'
        ));

        add_settings_section(
            'trustpilot_scraping_section',
            'Scraping Settings',
            array($this, 'scraping_section_callback'),
            'trustpilot_settings'
        );

        add_settings_field(
            'trustpilot_scraping_frequency',
            'Scraping Frequency (hours)',
            array($this, 'scraping_frequency_callback'),
            'trustpilot_settings',
            'trustpilot_scraping_section'
        );

        add_settings_field(
            'trustpilot_review_limit',
            'Maximum Reviews per Business',
            array($this, 'review_limit_callback'),
            'trustpilot_settings',
            'trustpilot_scraping_section'
        );

        add_settings_field(
            'trustpilot_rate_limit',
            'Rate Limit (seconds between requests)',
            array($this, 'rate_limit_callback'),
            'trustpilot_settings',
            'trustpilot_scraping_section'
        );
    }

    /**
     * Dashboard page
     */
    public function dashboard_page() {
        $business_manager = new Trustpilot_Business_Manager();
        $stats = $business_manager->get_business_statistics();
        $review_limit = get_option('trustpilot_review_limit', 5);
        ?>
        <div class="wrap">
            <h1>Trustpilot Fetcher Dashboard</h1>
            
            <div class="trustpilot-stats">
                <h2>Overview</h2>
                <p><strong>Total Businesses:</strong> <?php echo $stats['total_businesses']; ?></p>
                <p><strong>Total Reviews:</strong> <?php echo $stats['total_reviews']; ?></p>
            </div>

            <?php if (!empty($stats['businesses'])): ?>
                <h2>Businesses</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Business Name</th>
                            <th>Trustpilot URL</th>
                            <th>Reviews</th>
                            <th>Last Scraped</th>
                            <th>Next Scrape</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stats['businesses'] as $business): ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($business['title']); ?></strong>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url($business['url']); ?>" target="_blank">
                                        <?php echo esc_html($business['url']); ?>
                                    </a>
                                </td>
                                <td>
                                    <?php echo $business['review_count']; ?> / <?php echo $review_limit; ?>
                                </td>
                                <td>
                                    <?php 
                                    if ($business['last_scraped']) {
                                        echo esc_html($business['last_scraped']);
                                    } else {
                                        echo 'Never';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    if ($business['next_scrape']['is_due']) {
                                        echo '<span style="color: #d63638; font-weight: bold;">Due now</span>';
                                    } else {
                                        echo esc_html($business['next_scrape']['next_scrape']) . '<br><small>(' . $business['next_scrape']['hours_remaining'] . ' hours)</small>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <a href="<?php echo get_edit_post_link($business['id']); ?>" class="button button-small">
                                        Edit
                                    </a>
                                    <a href="<?php echo get_delete_post_link($business['id']); ?>" class="button button-small button-link-delete">
                                        Delete
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="notice notice-info">
                    <p>No businesses have been added yet. <a href="<?php echo admin_url('edit.php?post_type=tp_businesses&page=add-trustpilot-business'); ?>">Add your first business</a> to get started.</p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Add Business page
     */
    public function add_business_page() {
        ?>
        <div class="wrap">
            <h1>Add Trustpilot Business</h1>
            
            <form id="add-business-form" method="post">
                <?php wp_nonce_field('add_trustpilot_business', 'trustpilot_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="business_url">Trustpilot URL</label>
                        </th>
                        <td>
                            <input type="url" id="business_url" name="business_url" class="regular-text" required>
                            <p class="description">Enter the full Trustpilot review URL (e.g., https://www.trustpilot.com/review/www.microsoft.com)</p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="submit" id="submit" class="button button-primary" value="Add Business & Start Scraping">
                </p>
            </form>
            
            <div id="form-message"></div>
        </div>
        <?php
    }

    /**
     * Settings page
     */
    public function settings_page() {
        ?>
        <div class="wrap">
            <h1>Trustpilot Fetcher Settings</h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('trustpilot_settings');
                do_settings_sections('trustpilot_settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Settings section callback
     */
    public function scraping_section_callback() {
        echo '<p>Configure how the plugin scrapes Trustpilot reviews.</p>';
    }

    /**
     * Scraping frequency field callback
     */
    public function scraping_frequency_callback() {
        $value = get_option('trustpilot_scraping_frequency', 24);
        echo '<input type="number" name="trustpilot_scraping_frequency" value="' . esc_attr($value) . '" min="1" /> hours';
        echo '<p class="description">How often to automatically scrape reviews (minimum 1 hour)</p>';
    }

    /**
     * Review limit field callback
     */
    public function review_limit_callback() {
        $value = get_option('trustpilot_review_limit', 5);
        echo '<input type="number" name="trustpilot_review_limit" value="' . esc_attr($value) . '" min="1" max="1000" /> reviews';
        echo '<p class="description">Maximum number of reviews to store per business</p>';
    }

    /**
     * Rate limit field callback
     */
    public function rate_limit_callback() {
        $value = get_option('trustpilot_rate_limit', 5);
        echo '<input type="number" name="trustpilot_rate_limit" value="' . esc_attr($value) . '" min="1" max="60" /> seconds';
        echo '<p class="description">Delay between requests to avoid being blocked</p>';
    }

    /**
     * Handle AJAX add business request
     */
    public function handle_add_business() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'add_trustpilot_business')) {
            wp_die('Security check failed');
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $business_url = esc_url_raw($_POST['business_url']);

        // Validate inputs
        if (empty($business_url)) {
            wp_send_json_error('Please enter a Trustpilot URL');
        }

        // Use business manager to handle all business logic
        $business_manager = new Trustpilot_Business_Manager();
        $result = $business_manager->create_business($business_url);

        if ($result['success']) {
            wp_send_json_success(array(
                'message' => $result['message'],
                'post_id' => $result['post_id']
            ));
        } else {
            wp_send_json_error($result['message']);
        }
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        // Load on all Trustpilot plugin pages
        if (strpos($hook, 'tp_businesses') !== false || strpos($hook, 'trustpilot') !== false) {
            wp_enqueue_script('trustpilot-admin', plugin_dir_url(__FILE__) . '../assets/js/admin.js', array('jquery'), '1.0.0', true);
            wp_localize_script('trustpilot-admin', 'trustpilot_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('add_trustpilot_business')
            ));
            
            // Add custom CSS
            wp_add_inline_style('wp-admin', '
                .trustpilot-stats {
                    background: #fff;
                    border: 1px solid #ccd0d4;
                    border-radius: 4px;
                    padding: 20px;
                    margin: 20px 0;
                }
                .trustpilot-stats h2 {
                    margin-top: 0;
                    color: #23282d;
                }
                .trustpilot-stats p {
                    margin: 10px 0;
                    font-size: 14px;
                }
                .trustpilot-stats strong {
                    color: #0073aa;
                }
                .wp-list-table th {
                    font-weight: 600;
                }
                .wp-list-table td {
                    vertical-align: middle;
                }
                .button-link-delete {
                    color: #a00 !important;
                }
                .button-link-delete:hover {
                    color: #dc3232 !important;
                }
            ');
        }
    }
} 