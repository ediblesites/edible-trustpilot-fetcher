<?php
/**
 * Business Manager for Trustpilot Fetcher
 * Handles WordPress-specific business operations
 */

class Trustpilot_Business_Manager {

    private $scraper;

    public function __construct() {
        $this->scraper = new Trustpilot_Scraper();
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
        $scraping_frequency_hours = get_option('trustpilot_scraping_frequency', 24);
        $scraping_frequency_seconds = $scraping_frequency_hours * 3600;
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
                $hours_remaining = ceil(($scraping_frequency_seconds - $time_since_last_scrape) / 3600);
                $results['skipped_scrapes']++;
                $results['errors'][] = "Business '{$business->post_title}' skipped (scraped {$hours_remaining} hours ago, due in " . ceil($scraping_frequency_seconds / 3600) . " hours)";
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
                update_post_meta($business->ID, 'business_name', $scrape_result['business_name']);
                update_post_meta($business->ID, 'aggregate_rating', $scrape_result['aggregate_rating']);
                update_post_meta($business->ID, 'total_reviews', $scrape_result['total_reviews']);
                update_post_meta($business->ID, 'last_scraped', $scrape_result['last_scraped']);
                
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
        $existing = get_posts(array(
            'post_type' => 'tp_businesses',
            'meta_query' => array(
                array(
                    'key' => 'business_url',
                    'value' => $business_url,
                    'compare' => '='
                )
            ),
            'post_status' => 'any',
            'numberposts' => 1
        ));

        if (!empty($existing)) {
            $business = $existing[0];
            
            // Check frequency unless forced
            if (!$force_scrape) {
                $scraping_frequency_hours = get_option('trustpilot_scraping_frequency', 24);
                $scraping_frequency_seconds = $scraping_frequency_hours * 3600;
                $current_time = current_time('timestamp');
                
                $last_scraped = get_post_meta($business->ID, 'last_scraped', true);
                
                if ($last_scraped) {
                    $last_scraped_timestamp = strtotime($last_scraped);
                    $time_since_last_scrape = $current_time - $last_scraped_timestamp;
                    
                    if ($time_since_last_scrape < $scraping_frequency_seconds) {
                        $hours_remaining = ceil(($scraping_frequency_seconds - $time_since_last_scrape) / 3600);
                        return new WP_Error(
                            'too_soon', 
                            "Business '{$business->post_title}' was scraped {$hours_remaining} hours ago. Next scrape due in " . ceil($scraping_frequency_seconds / 3600) . " hours."
                        );
                    }
                }
            }
        }

        return $this->scraper->scrape_business($business_url);
    }

    /**
     * Force scrape a single business (ignores frequency setting)
     * 
     * @param string $business_url Trustpilot URL to scrape
     * @return array|WP_Error Business data or error
     */
    public function force_scrape_single_business($business_url) {
        return $this->scrape_single_business($business_url, true);
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
                // Extract domain name from Trustpilot URL for taxonomy term
                $parsed_url = parse_url($business_url);
                $path_parts = explode('/', trim($parsed_url['path'] ?? '', '/'));
                $business_domain = $path_parts[1] ?? ''; // /review/domain.com -> domain.com
                
                if ($business_domain) {
                    // Get all reviews associated with this business via taxonomy
                    $reviews = get_posts(array(
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

                    // Delete all associated reviews
                    foreach ($reviews as $review) {
                        $deleted = wp_delete_post($review->ID, true);
                        if ($deleted) {
                            $results['reviews_deleted']++;
                        } else {
                            $results['errors'][] = "Failed to delete review ID {$review->ID}";
                        }
                    }

                    // Delete the taxonomy term if no reviews remain
                    $term = get_term_by('slug', $business_domain, 'tp_business');
                    if ($term && !is_wp_error($term)) {
                        wp_delete_term($term->term_id, 'tp_business');
                    }
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

            if (empty($existing)) {
                $results['message'] = 'Business not found';
                return $results;
            }

            $results['found'] = true;
            $business = $existing[0];

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
     * @param string $title Business title
     * @param string $trustpilot_url Trustpilot URL
     * @return array Results of creation operation
     */
    public function create_business($title, $trustpilot_url) {
        $results = array(
            'success' => false,
            'message' => '',
            'post_id' => null,
            'reviews_scraped' => 0,
            'errors' => array()
        );

        try {
            // Check for existing business and delete if found
            $existing = get_posts(array(
                'post_type' => 'tp_businesses',
                'meta_query' => array(
                    array(
                        'key' => 'business_url',
                        'value' => $trustpilot_url,
                        'compare' => '='
                    )
                ),
                'post_status' => 'any'
            ));

            if (!empty($existing)) {
                $existing_post = $existing[0];
                $results['message'] .= "Found existing business (ID: {$existing_post->ID}), deleting... ";
                
                // Get the business URL to extract domain for taxonomy term
                $existing_url = get_post_meta($existing_post->ID, 'business_url', true);
                if ($existing_url) {
                    // Extract domain name from Trustpilot URL for taxonomy term
                    $parsed_url = parse_url($existing_url);
                    $path_parts = explode('/', trim($parsed_url['path'] ?? '', '/'));
                    $business_domain = $path_parts[1] ?? ''; // /review/domain.com -> domain.com
                    
                    if ($business_domain) {
                        // Get all reviews associated with this business via taxonomy
                        $reviews = get_posts(array(
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
                        
                        foreach ($reviews as $review) {
                            wp_delete_post($review->ID, true);
                        }
                        
                        // Delete the taxonomy term if no reviews remain
                        $term = get_term_by('slug', $business_domain, 'tp_business');
                        if ($term && !is_wp_error($term)) {
                            wp_delete_term($term->term_id, 'tp_business');
                        }
                        
                        $results['message'] .= "Deleted {$existing_post->ID} and " . count($reviews) . " reviews. ";
                    }
                }
                
                // Delete the business
                wp_delete_post($existing_post->ID, true);
            }

            // Scrape once to get all data
            $scrape_result = $this->scraper->scrape_business($trustpilot_url);
            
            if (is_wp_error($scrape_result)) {
                throw new Exception('Failed to scrape business: ' . $scrape_result->get_error_message());
            }

            // Extract business domain for taxonomy
            $parsed_url = parse_url($trustpilot_url);
            $path_parts = explode('/', trim($parsed_url['path'] ?? '', '/'));
            $business_domain = $path_parts[1] ?? ''; // /review/domain.com -> domain.com

            // Create new business post
            $business_title = $title;
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
                throw new Exception('Failed to create business post: ' . $post_id->get_error_message());
            }

            // Save business URL and update with scraped data
            update_post_meta($post_id, 'business_url', $trustpilot_url);
            $this->update_business_data($post_id, $scrape_result);
            $results['post_id'] = $post_id;

            // Create reviews from the same scraped data
            $reviews_result = $this->create_reviews_from_data($business_domain, $scrape_result);
            $results['reviews_scraped'] = $reviews_result['reviews_saved'];
            
            if ($reviews_result['success']) {
                $results['success'] = true;
                $results['message'] .= 'Business created and ' . $results['reviews_scraped'] . ' reviews scraped successfully';
            } else {
                $results['message'] .= 'Business created but review creation failed: ' . $reviews_result['message'];
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
            $review_limit = get_option('trustpilot_review_limit', 5);
            
            // Limit the number of reviews to process
            $reviews = array_slice($reviews, 0, $review_limit);
            
            $results['message'] = "Processing " . count($reviews) . " reviews (limited to {$review_limit})";

            // Create or get the taxonomy term for this business
            $term = get_term_by('slug', $business_domain, 'tp_business');
            if (!$term) {
                $term_result = wp_insert_term($business_domain, 'tp_business');
                if (is_wp_error($term_result)) {
                    $results['message'] = 'Failed to create taxonomy term: ' . $term_result->get_error_message();
                    return $results;
                }
                $term = get_term($term_result['term_id'], 'tp_business');
            }
            
            if (!$term || is_wp_error($term)) {
                $results['message'] = 'Failed to get or create taxonomy term';
                return $results;
            }

            foreach ($reviews as $index => $review_data) {
                $review_post_data = array(
                    'post_title' => $review_data['title'] ?: 'Review by ' . $review_data['author'],
                    'post_content' => $review_data['content'],
                    'post_status' => 'publish',
                    'post_type' => 'tp_reviews',
                    'post_date' => $review_data['date'] ?: current_time('mysql')
                );
                
                $review_id = wp_insert_post($review_post_data);
                
                if (!is_wp_error($review_id)) {
                    // Save review metadata
                    update_post_meta($review_id, 'review_id', $review_data['review_id']);
                    update_post_meta($review_id, 'rating', $review_data['rating']);
                    update_post_meta($review_id, 'author', $review_data['author']);
                    update_post_meta($review_id, 'review_date', $review_data['date']);
                    
                    // Link review to business via taxonomy using the pre-created term
                    $result = wp_set_object_terms($review_id, $term->term_id, 'tp_business');
                    if (is_wp_error($result)) {
                        $results['errors'][] = 'Failed to link review to taxonomy: ' . $result->get_error_message();
                    }
                    
                    $results['reviews_saved']++;
                } else {
                    $results['errors'][] = 'Failed to save review: ' . $review_id->get_error_message();
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
} 