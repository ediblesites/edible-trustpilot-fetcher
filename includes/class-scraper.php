<?php
/**
 * Trustpilot Scraper Class
 * Handles scraping of Trustpilot business pages and review data
 */

class Trustpilot_Scraper {

    private $user_agent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36';

    public function __construct() {
        // Constructor
    }

    /**
     * Scrape business data from Trustpilot URL
     * 
     * @param string $business_url Trustpilot business URL
     * @return array|WP_Error Business data or error
     */
    public function scrape_business($business_url) {
        // Validate URL
        if (!$this->validate_trustpilot_url($business_url)) {
            return new WP_Error('invalid_url', 'Invalid Trustpilot URL provided');
        }

        // Fetch the page
        $html = $this->fetch_page($business_url);
        if (is_wp_error($html)) {
            return $html;
        }

        // Extract business data
        $business_data = $this->extract_business_data($html, $business_url);
        if (is_wp_error($business_data)) {
            return $business_data;
        }

        // Extract individual reviews
        $reviews = $this->extract_reviews($html);
        $business_data['reviews'] = $reviews;

        return $business_data;
    }

    /**
     * Validate Trustpilot URL
     * 
     * @param string $url URL to validate
     * @return bool True if valid Trustpilot URL
     */
    private function validate_trustpilot_url($url) {
        $parsed_url = parse_url($url);
        return isset($parsed_url['host']) && 
               (strpos($parsed_url['host'], 'trustpilot.com') !== false) &&
               filter_var($url, FILTER_VALIDATE_URL);
    }

    /**
     * Fetch page content
     * 
     * @param string $url URL to fetch
     * @return string|WP_Error HTML content or error
     */
    private function fetch_page($url) {
        // Initialize cURL
        $ch = curl_init();
        
        // Set the URL
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        // Handle cookies
        curl_setopt($ch, CURLOPT_COOKIEJAR, '/tmp/trustpilot_cookies.txt');
        curl_setopt($ch, CURLOPT_COOKIEFILE, '/tmp/trustpilot_cookies.txt');
        
        // Handle compressed responses
        curl_setopt($ch, CURLOPT_ENCODING, '');
        
        // Set headers
        $headers = array(
            'User-Agent: ' . $this->user_agent,
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.5',
            'Accept-Encoding: gzip, deflate',
            'Connection: keep-alive',
            'Upgrade-Insecure-Requests: 1',
            'Cache-Control: no-cache',
            'Pragma: no-cache'
        );
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        // Execute the request
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $final_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $redirect_count = curl_getinfo($ch, CURLINFO_REDIRECT_COUNT);
        
        curl_close($ch);
        
        if ($response === false || !empty($error)) {
            return new WP_Error('fetch_error', 'Failed to fetch page: ' . $error);
        }
        
        if ($http_code !== 200) {
            return new WP_Error('http_error', 'HTTP error: ' . $http_code);
        }
        
        if (empty($response)) {
            return new WP_Error('empty_response', 'Empty response received');
        }
        
        return $response;
    }

    /**
     * Extract business data from HTML
     * 
     * @param string $html HTML content
     * @param string $business_url Original business URL
     * @return array|WP_Error Business data or error
     */
    private function extract_business_data($html, $business_url) {
        // Extract structured data (JSON-LD)
        $structured_data = $this->extract_structured_data($html);
        
        // Extract business name from HTML
        $business_name = $this->extract_business_name($html);
        
        // Extract aggregate rating data
        $aggregate_data = $this->extract_aggregate_rating($structured_data);
        
        if (is_wp_error($aggregate_data)) {
            return $aggregate_data;
        }

        return array(
            'business_url' => $business_url,
            'business_name' => $business_name,
            'aggregate_rating' => $aggregate_data['rating'],
            'total_reviews' => $aggregate_data['review_count'],
            'best_rating' => $aggregate_data['best_rating'] ?? 5,
            'worst_rating' => $aggregate_data['worst_rating'] ?? 1,
            'last_scraped' => current_time('c'),
            'status' => 'active'
        );
    }

    /**
     * Extract structured data (JSON-LD) from HTML
     * 
     * @param string $html HTML content
     * @return array Array of structured data objects
     */
    private function extract_structured_data($html) {
        $structured_data = array();
        
        // Find JSON-LD scripts (strict pattern)
        preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);
        
        // If no JSON-LD scripts found, use the alternative pattern
        if (count($matches[1]) == 0) {
            preg_match_all('/<script[^>]*type="application\/ld\+json"[^>]*>(.*?)<\/script>/s', $html, $alt_matches);
            $matches[1] = $alt_matches[1];
        }
        
        foreach ($matches[1] as $index => $json_string) {
            $data = json_decode($json_string, true);
            if ($data) {
                $structured_data[] = $data;
            }
        }
        
        return $structured_data;
    }

    /**
     * Extract business name from HTML
     * 
     * @param string $html HTML content
     * @return string Business name
     */
    private function extract_business_name($html) {
        // Try to extract from title tag
        preg_match('/<title>(.*?)<\/title>/i', $html, $matches);
        if (!empty($matches[1])) {
            $title = trim($matches[1]);
            // Clean up title (remove "Trustpilot" suffix if present)
            $title = preg_replace('/\s*-\s*Trustpilot.*$/i', '', $title);
            return $title;
        }
        
        // Fallback: try to extract from h1
        preg_match('/<h1[^>]*>(.*?)<\/h1>/i', $html, $matches);
        if (!empty($matches[1])) {
            return trim(strip_tags($matches[1]));
        }
        
        return 'Unknown Business';
    }

    /**
     * Extract aggregate rating from structured data
     * 
     * @param array $structured_data Array of structured data objects
     * @return array|WP_Error Aggregate rating data or error
     */
    private function extract_aggregate_rating($structured_data) {
        foreach ($structured_data as $index => $data) {
            // If this object has an @graph, search inside it
            if (isset($data['@graph']) && is_array($data['@graph'])) {
                foreach ($data['@graph'] as $gidx => $gobj) {
                    // Look for AggregateRating at this level
                    if (isset($gobj['@type']) && $gobj['@type'] === 'AggregateRating') {
                        return array(
                            'rating' => floatval($gobj['ratingValue'] ?? 0),
                            'review_count' => intval($gobj['reviewCount'] ?? 0),
                            'best_rating' => intval($gobj['bestRating'] ?? 5),
                            'worst_rating' => intval($gobj['worstRating'] ?? 1)
                        );
                    }
                    // Look for nested aggregateRating
                    if (isset($gobj['aggregateRating']) && isset($gobj['aggregateRating']['@type']) && $gobj['aggregateRating']['@type'] === 'AggregateRating') {
                        $agg = $gobj['aggregateRating'];
                        return array(
                            'rating' => floatval($agg['ratingValue'] ?? 0),
                            'review_count' => intval($agg['reviewCount'] ?? 0),
                            'best_rating' => intval($agg['bestRating'] ?? 5),
                            'worst_rating' => intval($agg['worstRating'] ?? 1)
                        );
                    }
                }
            }
            // Look for AggregateRating schema at root
            if (isset($data['@type']) && $data['@type'] === 'AggregateRating') {
                return array(
                    'rating' => floatval($data['ratingValue'] ?? 0),
                    'review_count' => intval($data['reviewCount'] ?? 0),
                    'best_rating' => intval($data['bestRating'] ?? 5),
                    'worst_rating' => intval($data['worstRating'] ?? 1)
                );
            }
            // Also check for nested AggregateRating
            if (isset($data['aggregateRating']) && isset($data['aggregateRating']['@type']) && $data['aggregateRating']['@type'] === 'AggregateRating') {
                $agg = $data['aggregateRating'];
                return array(
                    'rating' => floatval($agg['ratingValue'] ?? 0),
                    'review_count' => intval($agg['reviewCount'] ?? 0),
                    'best_rating' => intval($agg['bestRating'] ?? 5),
                    'worst_rating' => intval($agg['worstRating'] ?? 1)
                );
            }
        }
        
        return new WP_Error('no_aggregate_rating', 'No aggregate rating data found in structured data');
    }

    /**
     * Extract individual reviews from HTML
     * 
     * @param string $html HTML content
     * @return array Array of review data
     */
    private function extract_reviews($html) {
        $reviews = array();
        
        // Extract structured data (JSON-LD) - reuse existing method
        $structured_data = $this->extract_structured_data($html);
        
        // Look for reviews in the structured data
        foreach ($structured_data as $data) {
            // Check if this object has reviews
            if (isset($data['@graph']) && is_array($data['@graph'])) {
                foreach ($data['@graph'] as $obj) {
                    if (isset($obj['@type']) && $obj['@type'] === 'Review') {
                        $review = array(
                            'review_id' => $obj['@id'] ?? uniqid('review_'),
                            'title' => $obj['name'] ?? '',
                            'content' => $obj['reviewBody'] ?? '',
                            'rating' => isset($obj['reviewRating']) ? intval($obj['reviewRating']['ratingValue']) : 0,
                            'author' => isset($obj['author']) ? $obj['author']['name'] : '',
                            'date' => $obj['datePublished'] ?? ''
                        );
                        
                        if (!empty($review['content'])) {
                            $reviews[] = $review;
                        }
                    }
                }
            }
            
            // Also check for reviews at root level
            if (isset($data['@type']) && $data['@type'] === 'Review') {
                $review = array(
                    'review_id' => $data['@id'] ?? uniqid('review_'),
                    'title' => $data['name'] ?? '',
                    'content' => $data['reviewBody'] ?? '',
                    'rating' => isset($data['reviewRating']) ? intval($data['reviewRating']['ratingValue']) : 0,
                    'author' => isset($data['author']) ? $data['author']['name'] : '',
                    'date' => $data['datePublished'] ?? ''
                );
                
                if (!empty($review['content'])) {
                    $reviews[] = $review;
                }
            }
        }
        
        return $reviews;
    }

    /**
     * Test scraping functionality
     * 
     * @param string $test_url URL to test
     * @return array Test results
     */
    public function test_scraping($test_url) {
        $results = array(
            'success' => false,
            'data' => null,
            'error' => null,
            'execution_time' => 0
        );
        
        $start_time = microtime(true);
        
        try {
            $data = $this->scrape_business($test_url);
            
            if (is_wp_error($data)) {
                $results['error'] = $data->get_error_message();
            } else {
                $results['success'] = true;
                $results['data'] = $data;
            }
        } catch (Exception $e) {
            $results['error'] = 'Exception: ' . $e->getMessage();
        }
        
        $results['execution_time'] = microtime(true) - $start_time;
        
        return $results;
    }
} 