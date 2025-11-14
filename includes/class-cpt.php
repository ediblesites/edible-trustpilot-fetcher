<?php
/**
 * Custom Post Types for Trustpilot Fetcher
 */

class Trustpilot_CPT {

    public function __construct() {
        // Constructor can be empty now
    }

    /**
     * Register custom post types
     */
    public static function register_post_types() {
        // Register tp_businesses CPT
        $business_labels = array(
            'name' => 'Trustpilot Businesses',
            'singular_name' => 'Trustpilot Business',
            'add_new' => 'Add New',
            'add_new_item' => 'Add New Trustpilot Business',
            'edit_item' => 'Edit Trustpilot Business',
            'new_item' => 'New Trustpilot Business',
            'view_item' => 'View Trustpilot Business',
            'view_items' => 'View Trustpilot Businesses',
            'search_items' => 'Search Trustpilot Businesses',
            'not_found' => 'No trustpilot businesses found.',
            'not_found_in_trash' => 'No trustpilot businesses found in Trash.',
            'parent_item_colon' => 'Parent Trustpilot Business:',
            'all_items' => 'All Trustpilot Businesses',
            'archives' => 'Trustpilot Business Archives',
            'attributes' => 'Trustpilot Business Attributes',
            'insert_into_item' => 'Insert into trustpilot business',
            'uploaded_to_this_item' => 'Uploaded to this trustpilot business',
            'featured_image' => 'Featured image',
            'set_featured_image' => 'Set featured image',
            'remove_featured_image' => 'Remove featured image',
            'use_featured_image' => 'Use as featured image',
            'menu_name' => 'Trustpilot Businesses',
            'filter_items_list' => 'Filter trustpilot businesses list',
            'filter_by_date' => '',
            'items_list_navigation' => 'Trustpilot Businesses list navigation',
            'items_list' => 'Trustpilot Businesses list',
            'item_published' => 'Trustpilot Business published.',
            'item_published_privately' => 'Trustpilot Business published privately.',
            'item_reverted_to_draft' => 'Trustpilot Business reverted to draft.',
            'item_scheduled' => 'Trustpilot Business scheduled.',
            'item_updated' => 'Trustpilot Business updated.',
            'text_domain' => 'edible-trustpilot-fetcher'
        );

        $business_args = array(
            'label' => 'Trustpilot Businesses',
            'labels' => $business_labels,
            'description' => '',
            'public' => false,
            'hierarchical' => false,
            'exclude_from_search' => true,
            'publicly_queryable' => false,
            'show_ui' => true,
            'show_in_nav_menus' => true,
            'show_in_admin_bar' => true,
            'show_in_rest' => true,
            'query_var' => true,
            'can_export' => true,
            'delete_with_user' => false,
            '_slug_changed' => true,
            'has_archive' => false,
            'rest_base' => '',
            'show_in_menu' => false,
            'menu_position' => '',
            'menu_icon' => 'dashicons-building',
            'capability_type' => 'post',
            'supports' => array('title', 'editor', 'custom-fields', 'revisions'),
            'taxonomies' => array(),
            'rewrite' => array(
                'with_front' => false,
            ),
        );

        register_post_type('tp_businesses', $business_args);

        // Register tp_reviews CPT
        $review_labels = array(
            'name' => 'Trustpilot Reviews',
            'singular_name' => 'Trustpilot Review',
            'add_new' => 'Add New',
            'add_new_item' => 'Add New Trustpilot Review',
            'edit_item' => 'Edit Trustpilot Review',
            'new_item' => 'New Trustpilot Review',
            'view_item' => 'View Trustpilot Review',
            'view_items' => 'View Trustpilot Reviews',
            'search_items' => 'Search Trustpilot Reviews',
            'not_found' => 'No trustpilot reviews found.',
            'not_found_in_trash' => 'No trustpilot reviews found in Trash.',
            'parent_item_colon' => 'Parent Trustpilot Review:',
            'all_items' => 'All Trustpilot Reviews',
            'archives' => 'Trustpilot Review Archives',
            'attributes' => 'Trustpilot Review Attributes',
            'insert_into_item' => 'Insert into trustpilot review',
            'uploaded_to_this_item' => 'Uploaded to this trustpilot review',
            'featured_image' => 'Featured image',
            'set_featured_image' => 'Set featured image',
            'remove_featured_image' => 'Remove featured image',
            'use_featured_image' => 'Use as featured image',
            'menu_name' => 'Trustpilot Reviews',
            'filter_items_list' => 'Filter trustpilot reviews list',
            'filter_by_date' => '',
            'items_list_navigation' => 'Trustpilot Reviews list navigation',
            'items_list' => 'Trustpilot Reviews list',
            'item_published' => 'Trustpilot Review published.',
            'item_published_privately' => 'Trustpilot Review published privately.',
            'item_reverted_to_draft' => 'Trustpilot Review reverted to draft.',
            'item_scheduled' => 'Trustpilot Review scheduled.',
            'item_updated' => 'Trustpilot Review updated.',
            'text_domain' => 'edible-trustpilot-fetcher'
        );

        $review_args = array(
            'label' => 'Trustpilot Reviews',
            'labels' => $review_labels,
            'description' => '',
            'public' => false,
            'hierarchical' => false,
            'exclude_from_search' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_nav_menus' => true,
            'show_in_admin_bar' => true,
            'show_in_rest' => true,
            'query_var' => true,
            'can_export' => true,
            'delete_with_user' => false,
            '_slug_changed' => true,
            'has_archive' => false,
            'rest_base' => '',
            'show_in_menu' => false,
            'menu_position' => '',
            'menu_icon' => 'dashicons-star-filled',
            'capability_type' => 'post',
            'supports' => array('title', 'editor', 'custom-fields', 'revisions', 'excerpt'),
            'taxonomies' => array(),
            'rewrite' => array(
                'with_front' => false,
            ),
        );

        register_post_type('tp_reviews', $review_args);

        // Register custom meta fields for tp_reviews
        self::register_review_meta_fields();
        
        // Register custom meta fields for tp_businesses
        self::register_business_meta_fields();
        
        // Register taxonomy for linking businesses to reviews
        self::register_business_taxonomy();
        
        // Add admin columns and filters
        add_action('manage_tp_businesses_posts_columns', array(__CLASS__, 'add_business_admin_columns'));
        add_action('manage_tp_businesses_posts_custom_column', array(__CLASS__, 'populate_business_admin_columns'), 10, 2);
        add_action('manage_tp_reviews_posts_columns', array(__CLASS__, 'add_review_admin_columns'));
        add_action('manage_tp_reviews_posts_custom_column', array(__CLASS__, 'populate_review_admin_columns'), 10, 2);
        
        // Hook to clean up reviews when business is deleted
        add_action('before_delete_post', array(__CLASS__, 'cleanup_business_reviews'));
        add_action('wp_trash_post', array(__CLASS__, 'cleanup_business_reviews'));
    }

    /**
     * Register custom meta fields for tp_businesses
     */
    public static function register_business_meta_fields() {
        // business_url - Unique Trustpilot URL for deduplication
        register_meta('post', 'business_url', array(
            'type' => 'string',
            'description' => 'Unique Trustpilot URL for this business',
            'single' => true,
            'show_in_rest' => true
        ));

        // business_name - Name of the business
        register_meta('post', 'business_name', array(
            'type' => 'string',
            'description' => 'Name of the business',
            'single' => true,
            'show_in_rest' => true
        ));

        // aggregate_rating - Overall business rating
        register_meta('post', 'aggregate_rating', array(
            'type' => 'number',
            'description' => 'Overall business rating (0-5)',
            'single' => true,
            'show_in_rest' => true
        ));

        // total_reviews - Total number of reviews
        register_meta('post', 'total_reviews', array(
            'type' => 'integer',
            'description' => 'Total number of reviews',
            'single' => true,
            'show_in_rest' => true
        ));

        // last_scraped - Timestamp of last scrape
        register_meta('post', 'last_scraped', array(
            'type' => 'string',
            'description' => 'Timestamp of last scrape',
            'single' => true,
            'show_in_rest' => true
        ));

        // business_slug - Clean slug extracted from Trustpilot URL
        register_meta('post', 'business_slug', array(
            'type' => 'string',
            'description' => 'Clean business slug extracted from Trustpilot URL (e.g., "microsoft" from "www.microsoft.com")',
            'single' => true,
            'show_in_rest' => true
        ));
    }

    /**
     * Register custom meta fields for tp_reviews
     */
    public static function register_review_meta_fields() {
        // review_id - Unique Trustpilot review ID
        register_meta('post', 'review_id', array(
            'type' => 'string',
            'description' => 'Unique Trustpilot review ID',
            'single' => true,
            'show_in_rest' => true
        ));

        // rating - Star rating (1-5)
        register_meta('post', 'rating', array(
            'type' => 'integer',
            'description' => 'Star rating (1-5)',
            'single' => true,
            'show_in_rest' => true
        ));
    }

    /**
     * Register taxonomy for linking businesses to reviews
     */
    public static function register_business_taxonomy() {
        $labels = array(
            'name' => 'Businesses',
            'singular_name' => 'Business',
            'search_items' => 'Search Businesses',
            'all_items' => 'All Businesses',
            'parent_item' => 'Parent Business',
            'parent_item_colon' => 'Parent Business:',
            'edit_item' => 'Edit Business',
            'update_item' => 'Update Business',
            'add_new_item' => 'Add New Business',
            'new_item_name' => 'New Business Name',
            'menu_name' => 'Businesses',
        );

        $args = array(
            'hierarchical' => false,
            'labels' => $labels,
            'show_ui' => true,
            'show_admin_column' => false,
            'query_var' => true,
            'rewrite' => array('slug' => 'business'),
            'show_in_rest' => true,
        );

        register_taxonomy('tp_business', array('tp_reviews'), $args);
    }

    /**
     * Add admin columns for businesses
     */
    public static function add_business_admin_columns($columns) {
        $new_columns = array();
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'title') {
                $new_columns['business_url'] = 'Trustpilot URL';
                $new_columns['reviews_link'] = 'Reviews';
                $new_columns['last_scraped'] = 'Last Scraped';
            }
        }
        return $new_columns;
    }

    /**
     * Populate business admin columns
     */
    public static function populate_business_admin_columns($column, $post_id) {
        switch ($column) {
            case 'business_url':
                $url = get_post_meta($post_id, 'business_url', true);
                if ($url) {
                    echo '<a href="' . esc_url($url) . '" target="_blank">' . esc_html($url) . '</a>';
                }
                break;
            case 'reviews_link':
                // Get the business URL to extract the domain for taxonomy filtering
                $business_url = get_post_meta($post_id, 'business_url', true);
                if ($business_url) {
                    // Use business manager to extract domain
                    $business_manager = new Trustpilot_Business_Manager();
                    $business_domain = $business_manager->extract_business_domain($business_url);
                    
                    if ($business_domain) {
                        $reviews_url = admin_url('edit.php?post_type=tp_reviews&tp_business=' . urlencode($business_domain));
                        echo '<a href="' . esc_url($reviews_url) . '">View Reviews</a>';
                    } else {
                        echo '<em>No domain found</em>';
                    }
                } else {
                    echo '<em>No URL</em>';
                }
                break;
            case 'last_scraped':
                $last_scraped = get_post_meta($post_id, 'last_scraped', true);
                if ($last_scraped) {
                    echo esc_html($last_scraped);
                } else {
                    echo '<em>Never</em>';
                }
                break;
        }
    }

    /**
     * Add admin columns for reviews
     */
    public static function add_review_admin_columns($columns) {
        $new_columns = array();
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'title') {
                $new_columns['business'] = 'Business';
                $new_columns['rating'] = 'Rating';
                $new_columns['review_content'] = 'Review Content';
            }
        }
        return $new_columns;
    }

    /**
     * Populate review admin columns
     */
    public static function populate_review_admin_columns($column, $post_id) {
        switch ($column) {
            case 'business':
                $terms = get_the_terms($post_id, 'tp_business');
                if ($terms && !is_wp_error($terms)) {
                    $term = $terms[0];
                    $filter_url = admin_url('edit.php?post_type=tp_reviews&tp_business=' . urlencode($term->slug));
                    echo '<a href="' . esc_url($filter_url) . '">' . esc_html($term->name) . '</a>';
                }
                break;
            case 'rating':
                $rating = get_post_meta($post_id, 'rating', true);
                if ($rating) {
                    echo esc_html($rating) . ' ★';
                } else {
                    echo 'N/A';
                }
                break;
            case 'review_content':
                $review = get_post($post_id);
                if ($review && !empty($review->post_content)) {
                    // Truncate long reviews and add ellipsis
                    $content = wp_strip_all_tags($review->post_content);
                    if (strlen($content) > 200) {
                        $content = substr($content, 0, 200) . '...';
                    }
                    echo '<div style="max-width: 400px; word-wrap: break-word;">' . esc_html($content) . '</div>';
                } else {
                    echo '<em>No content</em>';
                }
                break;
        }
    }

    /**
     * Get active businesses for scraping
     * 
     * @return array Array of business post objects
     */
    public static function get_active_businesses() {
        return get_posts(array(
            'post_type' => 'tp_businesses',
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ));
    }

    /**
     * Clean up reviews and taxonomy when a business is deleted
     * 
     * @param int $post_id The post ID being deleted
     */
    public static function cleanup_business_reviews($post_id) {
        $post = get_post($post_id);
        
        // Only process business posts
        if ($post && $post->post_type === 'tp_businesses') {
            $business_url = get_post_meta($post_id, 'business_url', true);
            if ($business_url) {
                $business_manager = new Trustpilot_Business_Manager();
                $business_domain = $business_manager->extract_business_domain($business_url);
                
                if ($business_domain) {
                    // Use the existing method to delete reviews and taxonomy
                    $business_manager->delete_reviews_and_term($business_domain);
                }
            }
        }
    }
} 
