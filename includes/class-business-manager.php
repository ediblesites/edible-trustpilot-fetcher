<?php
/**
 * Business Manager for Trustpilot Fetcher
 * Handles WordPress-specific business operations
 */

class Trustpilot_Business_Manager {

    private $scraper;

    // Constants
    private const SECONDS_PER_HOUR = 3600;
    private const DEFAULT_REVIEW_LIMIT = 5;
    private const DEFAULT_SCRAPING_FREQUENCY = 24;

    public function __construct() {
        $this->scraper = new Trustpilot_Scraper();
    }

    /**
     * Extract business domain from Trustpilot URL
     * 
     * @param string $trustpilot_url Trustpilot URL
     * @return string Business domain
     */
    public function extract_business_domain($trustpilot_url) {
        $parsed_url = parse_url($trustpilot_url);
        $path_parts = explode('/', trim($parsed_url['path'] ?? '', '/'));
        return $path_parts[1] ?? '';
    }

    /**
     * Check if business is due for scraping based on frequency setting
     * 
     * @param int $business_id Business post ID
     * @param bool $force_scrape Whether to ignore frequency setting
     * @return bool|WP_Error True if due for scraping, false if not, WP_Error if too soon
     */
    private function is_business_due_for_scraping($business_id, $force_scrape = false) {
        if ($force_scrape) {
            return true;
        }

        $scraping_frequency_hours = get_option('trustpilot_scraping_frequency', self::DEFAULT_SCRAPING_FREQUENCY);
        $scraping_frequency_seconds = $scraping_frequency_hours * self::SECONDS_PER_HOUR;
        $current_time = current_time('timestamp');
        
        $last_scraped = get_post_meta($business_id, 'last_scraped', true);
        
        if (!$last_scraped) {
            return true; // Never scraped, so due
        }

        $last_scraped_timestamp = strtotime($last_scraped);
        $time_since_last_scrape = $current_time - $last_scraped_timestamp;
        
        if ($time_since_last_scrape < $scraping_frequency_seconds) {
            $hours_remaining = ceil(($scraping_frequency_seconds - $time_since_last_scrape) / self::SECONDS_PER_HOUR);
            $business = get_post($business_id);
            return new WP_Error(
                'too_soon', 
                "Business '{$business->post_title}' was scraped {$hours_remaining} hours ago. Next scrape due in " . ceil($scraping_frequency_seconds / self::SECONDS_PER_HOUR) . " hours."
            );
        }

        return true;
    }

    /**
     * Get reviews associated with a business domain
     * 
     * @param string $business_domain Business domain
     * @return array Array of review posts
     */
    private function get_reviews_by_domain($business_domain) {
        return get_posts(array(
            'post_type' => 'tp_reviews',
            'tax_query' => array(
                array(
                    'taxonomy' => 'tp_business',
                    'field' => 'slug',
                    'terms' => $business_domain
                )
            ),
            'post_status' => 'any',
            'numberposts' => -1
        ));
    }

    /**
     * Delete reviews and taxonomy term for a business domain
     * 
     * @param string $business_domain Business domain
     * @return int Number of reviews deleted
     */
    private function delete_reviews_and_term($business_domain) {
        $reviews = $this->get_reviews_by_domain($business_domain);
        $deleted_count = 0;
        
        foreach ($reviews as $review) {
            if (wp_delete_post($review->ID, true)) {
                $deleted_count++;
            }
        }
        
        // Delete the taxonomy term if no reviews remain
        $term = get_term_by('slug', $business_domain, 'tp_business');
        if ($term && !is_wp_error($term)) {
            wp_delete_term($term->term_id, 'tp_business');
        }
        
        return $deleted_count;
    }

    /**
     * Validate business creation parameters
     * 
     * @param string $trustpilot_url Trustpilot URL
     * @return array Validation result
     */
    private function validate_business_creation($trustpilot_url) {
        if (empty($trustpilot_url)) {
            return array(
                'valid' => false,
                'message' => 'Trustpilot URL is required'
            );
        }

        if (!filter_var($trustpilot_url, FILTER_VALIDATE_URL)) {
            return array(
                'valid' => false,
                'message' => 'Invalid Trustpilot URL format'
            );
        }

        if (strpos($trustpilot_url, 'trustpilot.com') === false) {
            return array(
                'valid' => false,
                'message' => 'URL must be a Trustpilot URL'
            );
        }

        return array('valid' => true);
    }

    /**
     * Find business by Trustpilot URL
     * 
     * @param string $trustpilot_url Trustpilot URL
     * @return WP_Post|null Business post or null if not found
     */
    private function find_business_by_url($trustpilot_url) {
        $existing = get_posts(array(
            'post_type' => 'tp_businesses',
            'meta_query' => array(
                array(
                    'key' => 'business_url',
                    'value' => $trustpilot_url,
                    'compare' => '='
                )
            ),
            'post_status' => 'any',
            'numberposts' => 1
        ));

        return !empty($existing) ? $existing[0] : null;
    }

    /**
     * Check if business already exists
     * 
     * @param string $trustpilot_url Trustpilot URL
     * @return bool True if exists, false otherwise
     */
    private function business_exists($trustpilot_url) {
        return $this->find_business_by_url($trustpilot_url) !== null;
    }

    /**
     * Create business post with metadata
     * 
     * @param string $title Business title
     * @param string $trustpilot_url Trustpilot URL
     * @param array $scrape_result Scraped business data
     * @return int|WP_Error Post ID or error
     */
    private function create_business_post($title, $trustpilot_url, $scrape_result) {
        // Determine business title
        $business_title = $title;
        if (empty($business_title)) {
            $business_title = 'Business from Trustpilot';
        }
        
        if (!empty($scrape_result['business_name'])) {
            // Clean business name by removing everything after the bar
            $business_title = trim(explode('|', $scrape_result['business_name'])[0]);
        }

        $post_data = array(
            'post_title' => $business_title,
            'post_content' => '',
            'post_status' => 'publish',
            'post_type' => 'tp_businesses'
        );

        $post_id = wp_insert_post($post_data);
        if (is_wp_error($post_id)) {
            return $post_id;
        }

        // Save business URL and update with scraped data
        update_post_meta($post_id, 'business_url', $trustpilot_url);
        $this->update_business_data($post_id, $scrape_result);

        return $post_id;
    }

    /**
     * Create taxonomy term for business domain
     * 
     * @param string $business_domain Business domain
     * @return WP_Term|WP_Error Taxonomy term or error
     */
    private function create_taxonomy_term($business_domain) {
        $term = get_term_by('slug', $business_domain, 'tp_business');
        if (!$term) {
            $term_result = wp_insert_term($business_domain, 'tp_business');
            if (is_wp_error($term_result)) {
                return $term_result;
            }
            $term = get_term($term_result['term_id'], 'tp_business');
        }
        
        if (!$term || is_wp_error($term)) {
            return new WP_Error('taxonomy_error', 'Failed to get or create taxonomy term');
        }

        return $term;
    }

    /**
     * Create a single review post
     * 
     * @param array $review_data Review data
     * @param WP_Term $term Taxonomy term
     * @return int|WP_Error Review post ID or error
     */
    private function create_single_review($review_data, $term) {
        $review_post_data = array(
            'post_title' => $review_data['title'] ?: 'Review by ' . $review_data['author'],
            'post_content' => $review_data['content'],
            'post_status' => 'publish',
            'post_type' => 'tp_reviews',
            'post_date' => $review_data['date'] ?: current_time('mysql')
        );
        
        $review_id = wp_insert_post($review_post_data);
        
        if (is_wp_error($review_id)) {
            return $review_id;
        }

        // Save review metadata
        update_post_meta($review_id, 'review_id', $review_data['review_id']);
        update_post_meta($review_id, 'rating', $review_data['rating']);
        update_post_meta($review_id, 'author', $review_data['author']);
        update_post_meta($review_id, 'review_date', $review_data['date']);
        
        // Link review to business via taxonomy
        $result = wp_set_object_terms($review_id, $term->term_id, 'tp_business');
        if (is_wp_error($result)) {
            return $result;
        }

        return $review_id;
    }

    /**
     * Scrape all active businesses that are due for scraping
     * 
     * @return array Results of scraping operation
     */
    public function scrape_all_active_businesses() {
        $results = array(
            'total_businesses' => 0,
            'due_for_scraping' => 0,
            'successful_scrapes' => 0,
            'failed_scrapes' => 0,
            'skipped_scrapes' => 0,
            'errors' => array()
        );

        // Get only published (active) businesses
        $active_businesses = Trustpilot_CPT::get_active_businesses();
        $results['total_businesses'] = count($active_businesses);

        // Get scraping frequency setting
        $scraping_frequency_hours = get_option('trustpilot_scraping_frequency', self::DEFAULT_SCRAPING_FREQUENCY);
        $scraping_frequency_seconds = $scraping_frequency_hours * self::SECONDS_PER_HOUR;
        $current_time = current_time('timestamp');

        foreach ($active_businesses as $business) {
            $business_url = get_post_meta($business->ID, 'business_url', true);
            
            if (empty($business_url)) {
                $results['errors'][] = "Business ID {$business->ID} has no Trustpilot URL";
                $results['failed_scrapes']++;
                continue;
            }

            // Check if business is due for scraping
            $last_scraped = get_post_meta($business->ID, 'last_scraped', true);
            $time_since_last_scrape = 0;
            
            if ($last_scraped) {
                $last_scraped_timestamp = strtotime($last_scraped);
                $time_since_last_scrape = $current_time - $last_scraped_timestamp;
            }

            // If business was scraped recently, skip it
            if ($last_scraped && $time_since_last_scrape < $scraping_frequency_seconds) {
                $hours_remaining = ceil(($scraping_frequency_seconds - $time_since_last_scrape) / self::SECONDS_PER_HOUR);
                $results['skipped_scrapes']++;
                $results['errors'][] = "Business '{$business->post_title}' skipped (scraped {$hours_remaining} hours ago, due in " . ceil($scraping_frequency_seconds / self::SECONDS_PER_HOUR) . " hours)";
                continue;
            }

            $results['due_for_scraping']++;

            // Scrape the business using the standalone scraper
            $scrape_result = $this->scraper->scrape_business($business_url);
            
            if (is_wp_error($scrape_result)) {
                $results['errors'][] = "Business '{$business->post_title}': " . $scrape_result->get_error_message();
                $results['failed_scrapes']++;
            } else {
                // Update business with new data
                $this->update_business_data($business->ID, $scrape_result);
                
                $results['successful_scrapes']++;
            }
        }

        return $results;
    }

    /**
     * Scrape a single business by URL (respects frequency setting)
     * 
     * @param string $business_url Trustpilot URL to scrape
     * @param bool $force_scrape Whether to ignore frequency setting
     * @return array|WP_Error Business data or error
     */
    public function scrape_single_business($business_url, $force_scrape = false) {
        // Find the business by URL
        $business = $this->find_business_by_url($business_url);

        if ($business) {
            // Check frequency unless forced
            $due_check = $this->is_business_due_for_scraping($business->ID, $force_scrape);
            if (is_wp_error($due_check)) {
                return $due_check;
            }
        }

        return $this->scraper->scrape_business($business_url);
    }

    /**
     * Update business data in WordPress
     * 
     * @param int $business_id WordPress post ID
     * @param array $business_data Scraped business data
     * @return bool Success status
     */
    public function update_business_data($business_id, $business_data) {
        if (is_wp_error($business_data)) {
            return false;
        }

        update_post_meta($business_id, 'business_name', $business_data['business_name']);
        update_post_meta($business_id, 'aggregate_rating', $business_data['aggregate_rating']);
        update_post_meta($business_id, 'total_reviews', $business_data['total_reviews']);
        update_post_meta($business_id, 'last_scraped', $business_data['last_scraped']);

        return true;
    }

    /**
     * Delete a business and all its associated reviews
     * 
     * @param int $business_id WordPress post ID
     * @return array Results of deletion operation
     */
    public function delete_business_and_reviews($business_id) {
        $results = array(
            'business_deleted' => false,
            'reviews_deleted' => 0,
            'errors' => array()
        );

        try {
            // Get the business post to verify it exists
            $business = get_post($business_id);
            if (!$business || $business->post_type !== 'tp_businesses') {
                throw new Exception("Business with ID $business_id not found");
            }

            // Get the business URL to extract domain for taxonomy term
            $business_url = get_post_meta($business_id, 'business_url', true);
            if ($business_url) {
                $business_domain = $this->extract_business_domain($business_url);
                
                if ($business_domain) {
                    $results['reviews_deleted'] = $this->delete_reviews_and_term($business_domain);
                }
            }

            // Delete the business post
            $business_deleted = wp_delete_post($business_id, true);
            if ($business_deleted) {
                $results['business_deleted'] = true;
            } else {
                $results['errors'][] = "Failed to delete business ID $business_id";
            }

        } catch (Exception $e) {
            $results['errors'][] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Delete a business by Trustpilot URL
     * 
     * @param string $trustpilot_url The Trustpilot URL to find and delete
     * @return array Results of deletion operation
     */
    public function delete_business_by_url($trustpilot_url) {
        $results = array(
            'success' => false,
            'message' => '',
            'found' => false,
            'business_deleted' => false,
            'reviews_deleted' => 0,
            'errors' => array()
        );

        try {
            // Find the business by URL
            $business = $this->find_business_by_url($trustpilot_url);

            if (!$business) {
                $results['message'] = 'Business not found';
                return $results;
            }

            $results['found'] = true;

            // Delete the business and its reviews
            $delete_results = $this->delete_business_and_reviews($business->ID);
            
            $results['business_deleted'] = $delete_results['business_deleted'];
            $results['reviews_deleted'] = $delete_results['reviews_deleted'];
            $results['errors'] = $delete_results['errors'];

            if ($results['business_deleted']) {
                $results['success'] = true;
                $results['message'] = "Business '{$business->post_title}' and {$results['reviews_deleted']} reviews deleted successfully";
            } else {
                $results['message'] = 'Failed to delete business';
            }

        } catch (Exception $e) {
            $results['errors'][] = $e->getMessage();
            $results['message'] = 'Error: ' . $e->getMessage();
        }

        return $results;
    }

    /**
     * Create a new business
     * 
     * @param string $trustpilot_url Trustpilot URL
     * @return array Results of creation operation
     */
    public function create_business($trustpilot_url) {
        $results = array(
            'success' => false,
            'message' => '',
            'post_id' => null,
            'reviews_scraped' => 0,
            'errors' => array()
        );

        try {
            // Validate input parameters
            $validation = $this->validate_business_creation($trustpilot_url);
            if (!$validation['valid']) {
                $results['message'] = $validation['message'];
                return $results;
            }

            // Check if business already exists
            if ($this->business_exists($trustpilot_url)) {
                $results['message'] = 'Business with this Trustpilot URL already exists';
                return $results;
            }

            // Scrape once to get all data
            $scrape_result = $this->scraper->scrape_business($trustpilot_url);
            
            if (is_wp_error($scrape_result)) {
                throw new Exception('Failed to scrape business: ' . $scrape_result->get_error_message());
            }

            // Extract business domain for taxonomy
            $business_domain = $this->extract_business_domain($trustpilot_url);

            // Create business post (title will be extracted from scraped data)
            $post_id = $this->create_business_post('', $trustpilot_url, $scrape_result);
            if (is_wp_error($post_id)) {
                throw new Exception('Failed to create business post: ' . $post_id->get_error_message());
            }

            $results['post_id'] = $post_id;

            // Create reviews from the same scraped data
            $reviews_result = $this->create_reviews_from_data($business_domain, $scrape_result);
            $results['reviews_scraped'] = $reviews_result['reviews_saved'];
            
            if ($reviews_result['success']) {
                $results['success'] = true;
                $results['message'] = 'Business created and ' . $results['reviews_scraped'] . ' reviews scraped successfully';
            } else {
                $results['message'] = 'Business created but review creation failed: ' . $reviews_result['message'];
            }

        } catch (Exception $e) {
            $results['errors'][] = $e->getMessage();
            $results['message'] = 'Error: ' . $e->getMessage();
        }

        return $results;
    }

    /**
     * Create reviews from the same scraped data
     * 
     * @param string $business_domain Business domain for taxonomy slug
     * @param array $scrape_result Scraped business data
     * @return array Results of review creation operation
     */
    public function create_reviews_from_data($business_domain, $scrape_result) {
        $results = array(
            'success' => false,
            'message' => '',
            'reviews_saved' => 0,
            'errors' => array()
        );

        try {
            $reviews = $scrape_result['reviews'] ?? array();
            
            if (empty($reviews)) {
                $results['message'] = 'No reviews found to save';
                return $results;
            }

            // Get the review limit from settings
            $review_limit = get_option('trustpilot_review_limit', self::DEFAULT_REVIEW_LIMIT);
            
            // Limit the number of reviews to process
            $reviews = array_slice($reviews, 0, $review_limit);
            
            $results['message'] = "Processing " . count($reviews) . " reviews (limited to {$review_limit})";

            // Create taxonomy term
            $term = $this->create_taxonomy_term($business_domain);
            if (is_wp_error($term)) {
                $results['message'] = 'Failed to create taxonomy term: ' . $term->get_error_message();
                return $results;
            }

            // Create individual reviews
            foreach ($reviews as $review_data) {
                $review_id = $this->create_single_review($review_data, $term);
                
                if (is_wp_error($review_id)) {
                    $results['errors'][] = 'Failed to save review: ' . $review_id->get_error_message();
                } else {
                    $results['reviews_saved']++;
                }
            }

            if ($results['reviews_saved'] > 0) {
                $results['success'] = true;
                $results['message'] = "Successfully saved {$results['reviews_saved']} reviews (limited to {$review_limit})";
            } else {
                $results['message'] = 'No reviews were saved';
            }

        } catch (Exception $e) {
            $results['errors'][] = $e->getMessage();
            $results['message'] = 'Error: ' . $e->getMessage();
        }

        return $results;
    }

    /**
     * Get review count for a business domain
     * 
     * @param string $business_domain Business domain
     * @return int Number of reviews
     */
    public function get_review_count_by_domain($business_domain) {
        $reviews = get_posts(array(
            'post_type' => 'tp_reviews',
            'tax_query' => array(
                array(
                    'taxonomy' => 'tp_business',
                    'field' => 'slug',
                    'terms' => $business_domain
                )
            ),
            'post_status' => 'publish',
            'numberposts' => -1
        ));
        
        return count($reviews);
    }

    /**
     * Get next scrape time for a business
     * 
     * @param int $business_id Business post ID
     * @return array Next scrape information
     */
    public function get_next_scrape_time($business_id) {
        $last_scraped = get_post_meta($business_id, 'last_scraped', true);
        
        if (!$last_scraped) {
            return array(
                'next_scrape' => 'Due now',
                'hours_remaining' => 0,
                'is_due' => true
            );
        }

        $scraping_frequency_hours = get_option('trustpilot_scraping_frequency', self::DEFAULT_SCRAPING_FREQUENCY);
        $scraping_frequency_seconds = $scraping_frequency_hours * self::SECONDS_PER_HOUR;
        $last_scraped_timestamp = strtotime($last_scraped);
        $next_scrape_timestamp = $last_scraped_timestamp + $scraping_frequency_seconds;
        $next_scrape_date = date('Y-m-d H:i:s', $next_scrape_timestamp);
        
        $current_time = current_time('timestamp');
        $is_due = $current_time >= $next_scrape_timestamp;
        
        if ($is_due) {
            return array(
                'next_scrape' => 'Due now',
                'hours_remaining' => 0,
                'is_due' => true
            );
        } else {
            $hours_remaining = ceil(($next_scrape_timestamp - $current_time) / self::SECONDS_PER_HOUR);
            return array(
                'next_scrape' => $next_scrape_date,
                'hours_remaining' => $hours_remaining,
                'is_due' => false
            );
        }
    }

    /**
     * Get business statistics for dashboard
     * 
     * @return array Business statistics
     */
    public function get_business_statistics() {
        $businesses = Trustpilot_CPT::get_active_businesses();
        $total_businesses = count($businesses);
        $total_reviews = 0;
        $business_stats = array();

        foreach ($businesses as $business) {
            $business_url = get_post_meta($business->ID, 'business_url', true);
            $business_domain = $this->extract_business_domain($business_url);
            $review_count = $this->get_review_count_by_domain($business_domain);
            $next_scrape = $this->get_next_scrape_time($business->ID);
            
            $total_reviews += $review_count;
            
            $business_stats[] = array(
                'id' => $business->ID,
                'title' => $business->post_title,
                'url' => $business_url,
                'domain' => $business_domain,
                'review_count' => $review_count,
                'last_scraped' => get_post_meta($business->ID, 'last_scraped', true),
                'next_scrape' => $next_scrape
            );
        }

        return array(
            'total_businesses' => $total_businesses,
            'total_reviews' => $total_reviews,
            'businesses' => $business_stats
        );
    }
} 