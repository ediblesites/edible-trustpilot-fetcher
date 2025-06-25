<?php
/**
 * Admin interface for Trustpilot Fetcher
 */

class Trustpilot_Admin {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_menu', array($this, 'rename_first_submenu'), 999);
        add_action('admin_init', array($this, 'init_settings'));
        add_action('wp_ajax_add_trustpilot_business', array($this, 'handle_add_business'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    /**
     * Add admin menu items
     */
    public function add_admin_menu() {
        // Main menu (defaults to All Businesses)
        add_menu_page(
            'All Businesses',
            'Trustpilot Review Fetcher',
            'manage_options',
            'edit.php?post_type=tp_businesses',
            null,
            'dashicons-star-filled',
            30
        );

        // Add Business submenu
        add_submenu_page(
            'edit.php?post_type=tp_businesses',
            'Add a Business',
            'Add a Business',
            'manage_options',
            'add-trustpilot-business',
            array($this, 'add_business_page')
        );

        // All Reviews submenu
        add_submenu_page(
            'edit.php?post_type=tp_businesses',
            'All Reviews',
            'All Reviews',
            'manage_options',
            'edit.php?post_type=tp_reviews'
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

        // Action Scheduler Status page
        add_submenu_page(
            'edit.php?post_type=tp_businesses',
            'Scraping Status',
            'Scraping Status',
            'manage_options',
            'trustpilot-scraping-status',
            array($this, 'scraping_status_page')
        );
    }

    /**
     * Rename the first submenu item to "All Businesses"
     */
    public function rename_first_submenu() {
        global $submenu;
        
        if (isset($submenu['edit.php?post_type=tp_businesses'])) {
            // Find and rename the first submenu item
            foreach ($submenu['edit.php?post_type=tp_businesses'] as $key => $item) {
                if ($item[2] === 'edit.php?post_type=tp_businesses') {
                    $submenu['edit.php?post_type=tp_businesses'][$key][0] = 'All Businesses';
                    break;
                }
            }
        }
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
     * Scraping Status page
     */
    public function scraping_status_page() {
        if (!class_exists('ActionScheduler_Store')) {
            echo '<div class="wrap"><h1>Scraping Status</h1><p>Action Scheduler is not available.</p></div>';
            return;
        }

        // Handle manual queue processing
        if (isset($_POST['process_queue']) && wp_verify_nonce($_POST['_wpnonce'], 'process_queue')) {
            $this->process_action_scheduler_queue();
        }

        $store = ActionScheduler_Store::instance();
        $pending_actions = $store->query_actions(array(
            'group' => 'trustpilot-scraping',
            'status' => ActionScheduler_Store::STATUS_PENDING,
            'per_page' => 50
        ));

        $failed_actions = $store->query_actions(array(
            'group' => 'trustpilot-scraping',
            'status' => ActionScheduler_Store::STATUS_FAILED,
            'per_page' => 50
        ));

        ?>
        <div class="wrap">
            <h1>Scraping Status</h1>
            
            <div class="card">
                <h2>Action Scheduler Status</h2>
                <p><strong>Pending Actions:</strong> <?php echo count($pending_actions); ?></p>
                <p><strong>Failed Actions:</strong> <?php echo count($failed_actions); ?></p>
                
                <form method="post">
                    <?php wp_nonce_field('process_queue'); ?>
                    <input type="submit" name="process_queue" class="button button-primary" value="Process Queue Now">
                </form>
            </div>

            <?php if (!empty($pending_actions)): ?>
            <div class="card">
                <h2>Pending Scraping Jobs</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Action</th>
                            <th>Business ID</th>
                            <th>Scheduled For</th>
                            <th>Attempts</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_actions as $action_id): 
                            $action = $store->fetch_action($action_id);
                            $args = $action->get_args();
                            $business_id = $args[0] ?? 'Unknown';
                            $business_title = get_the_title($business_id);
                        ?>
                        <tr>
                            <td><?php echo esc_html($action->get_hook()); ?></td>
                            <td>
                                <?php echo esc_html($business_id); ?>
                                <?php if ($business_title && $business_title !== 'Auto Draft'): ?>
                                    - <?php echo esc_html($business_title); ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html(date('Y-m-d H:i:s', $action->get_schedule()->get_date()->getTimestamp())); ?></td>
                            <td><?php echo esc_html($action->get_attempt_count()); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <?php if (!empty($failed_actions)): ?>
            <div class="card">
                <h2>Failed Scraping Jobs</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Action</th>
                            <th>Business ID</th>
                            <th>Last Attempt</th>
                            <th>Attempts</th>
                            <th>Error</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($failed_actions as $action_id): 
                            $action = $store->fetch_action($action_id);
                            $args = $action->get_args();
                            $business_id = $args[0] ?? 'Unknown';
                            $business_title = get_the_title($business_id);
                            $logs = $action->get_logs();
                            $last_log = end($logs);
                            $error_message = $last_log ? $last_log->get_message() : 'Unknown error';
                        ?>
                        <tr>
                            <td><?php echo esc_html($action->get_hook()); ?></td>
                            <td>
                                <?php echo esc_html($business_id); ?>
                                <?php if ($business_title && $business_title !== 'Auto Draft'): ?>
                                    - <?php echo esc_html($business_title); ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html(date('Y-m-d H:i:s', $action->get_schedule()->get_date()->getTimestamp())); ?></td>
                            <td><?php echo esc_html($action->get_attempt_count()); ?></td>
                            <td><?php echo esc_html($error_message); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Process Action Scheduler queue manually
     */
    private function process_action_scheduler_queue() {
        if (!class_exists('ActionScheduler_QueueRunner')) {
            return;
        }

        $runner = ActionScheduler_QueueRunner::instance();
        $processed = $runner->run(50); // Process up to 50 actions

        echo '<div class="notice notice-success"><p>Processed ' . $processed . ' actions from the queue.</p></div>';
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
        }
    }
} 